<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Seeders;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Attributes\AsSeeder;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\Contracts\SeederInterface;
use Tempest\Container\Container;
use Throwable;

#[AsCliCommand(name: 'seed', description: 'Write the sample content of the site', longDescription: <<<'DOC'
    ## DESCRIPTION

    Runs the seeders of the project: the classes with #[AsSeeder] that implement
    SeederInterface. They run by their `order` argument, lowest first, then by
    class name.

    Sample content, menus and the options a site needs to show them belong in a
    seeder, not only in the database: a database can be emptied or replaced, and
    the seeders write the same content again. Create one with `wp foehn
    make:seeder`.

    A seeder must be idempotent: it finds a post by slug, an attachment by meta
    or a menu by name before it creates it. So the command can run again on a
    site that has the content, and it updates the content.

    The command stops with exit status 1 at the first seeder that throws an
    exception.

    In the production environment, the command does not run unless --force or
    --dry-run is given: seeders write content that visitors see.

    ## OPTIONS

    [--class=<name>]
    : Run only this seeder. The full class name or the short class name.

    [--dry-run]
    : Run nothing. List the seeders that would run, in order.

    [--force]
    : Run in the production environment.

    ## EXAMPLES

        # Run every seeder
        wp foehn seed

        # Run one seeder
        wp foehn seed --class=EventSeeder

        # List the seeders, in the order they run
        wp foehn seed --dry-run
    DOC, writes: true)]
final readonly class SeedCommand implements CliCommandInterface
{
    public function __construct(
        private WpCli $cli,
        private SeederCatalog $catalog,
        private Container $container,
    ) {}

    /**
     * @param array<int, string> $args
     * @param array<string, mixed> $assocArgs
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        $seeders = $this->catalog->all();

        if ($seeders === []) {
            $this->cli->log('No seeders found. Create one with: wp foehn make:seeder <name>');

            return;
        }

        $class = $assocArgs['class'] ?? null;

        if ($class !== null) {
            $seeders = $this->select(is_string($class) ? $class : '', $seeders);

            if ($seeders === []) {
                return;
            }
        }

        if (($assocArgs['dry-run'] ?? null) !== null) {
            $this->cli->log('Would run, in order:');

            foreach ($seeders as $seeder) {
                $this->cli->log('  ' . self::describe($seeder['className'], $seeder['attribute']));
            }

            return;
        }

        foreach ($seeders as $seeder) {
            $className = $seeder['className'];

            $this->cli->log('Seeding: ' . self::describe($className, $seeder['attribute']));

            try {
                /** @var SeederInterface $instance */
                $instance = $this->container->get($className);
                $instance->run();
            } catch (Throwable $e) {
                $this->cli->error(sprintf('%s failed: %s', $className, $e->getMessage()));

                return;
            }
        }

        $this->cli->success(sprintf('%d %s run.', count($seeders), count($seeders) === 1 ? 'seeder' : 'seeders'));
    }

    /**
     * The one seeder `--class` names, or an error and nothing.
     *
     * @param list<array{className: class-string<SeederInterface>, attribute: AsSeeder}> $seeders
     * @return list<array{className: class-string<SeederInterface>, attribute: AsSeeder}>
     */
    private function select(string $name, array $seeders): array
    {
        $found = $this->catalog->find($name);

        if ($found === []) {
            $this->cli->error(sprintf(
                "Unknown seeder '%s'. Available seeders: %s.",
                $name,
                implode(', ', array_column($seeders, 'className')),
            ));

            return [];
        }

        if (count($found) > 1) {
            $this->cli->error(sprintf(
                "More than one seeder is named '%s'. Use the full class name: %s.",
                $name,
                implode(', ', array_column($found, 'className')),
            ));

            return [];
        }

        return $found;
    }

    private static function describe(string $className, AsSeeder $attribute): string
    {
        $line = sprintf('%d  %s', $attribute->order, $className);

        return $attribute->description === '' ? $line : "{$line} — {$attribute->description}";
    }
}
