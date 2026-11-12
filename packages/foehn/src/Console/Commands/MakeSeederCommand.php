<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\GenerationRequest;
use Studiometa\Foehn\Console\Scaffolder;
use Studiometa\Foehn\Console\Stubs\SeederStub;
use Studiometa\Foehn\Console\WpCli;

use function Tempest\Support\str;

#[AsCliCommand(name: 'make:seeder', description: 'Create a new seeder class', longDescription: <<<'DOC'
    ## DESCRIPTION

    Creates a class with #[AsSeeder] in `Seeders/`. `wp foehn seed` runs it.
    Sample content, menus and the options a site needs to show them go in a
    seeder, so the site can be built again from an empty database.

    The generated run() writes one post with Upsert::post(), which updates the
    post that has the same type and slug. Keep run() idempotent when you edit it.

    ## OPTIONS

    <name>
    : The seeder name (e.g., 'events', 'menus'). "Seeder" is added to the class name

    [--class=<class>]
    : Custom class name (defaults to PascalCase of name + Seeder)

    [--order=<order>]
    : Run order. Lower runs first (defaults to 10)

    [--description=<description>]
    : What the seeder writes, shown by wp foehn inspect (defaults to the humanized name)

    [--force]
    : Overwrite existing file

    [--dry-run]
    : Write nothing. Show the file that would be written, or report that it exists

    [--format=<format>]
    : Output format. text (default) or json. json prints the written files, their status and the next steps

    ## EXAMPLES

        # Create a seeder for sample events
        wp foehn make:seeder events --description="Sample events"

        # Create a seeder that runs after the others
        wp foehn make:seeder menus --order=50

        # Preview what would be created
        wp foehn make:seeder events --dry-run --format=json
    DOC)]
final class MakeSeederCommand implements CliCommandInterface
{
    public function __construct(
        private readonly WpCli $cli,
        private readonly Scaffolder $scaffolder,
    ) {}

    public function __invoke(array $args, array $assocArgs): void
    {
        $name = $args[0] ?? null;

        if ($name === null) {
            $this->cli->error('Please provide a seeder name.');

            return;
        }

        $base = str($name)->pascal()->stripEnd('Seeder');
        $className = $assocArgs['class'] ?? $base->toString() . 'Seeder';
        $order = (int) ($assocArgs['order'] ?? 10);
        $title = $base->snake(' ')->upperFirst()->toString();
        $description = $assocArgs['description'] ?? $title;
        $slug = $base->kebab()->toString();

        $this->scaffolder->scaffold(
            new GenerationRequest(
                stub: SeederStub::class,
                subdirectory: 'Seeders',
                className: $className,
                attributeArguments: ['order' => $order, 'description' => $description],
                bodyReplacements: [
                    'run' => ["'dummy-post'" => "'{$slug}'", "'Dummy post'" => var_export($title, true)],
                ],
                replacements: ['DummySeeder' => $className],
            ),
            $assocArgs,
            'Seeder created',
            [
                "Edit {$className}::run() to write the content. Keep it idempotent: find each post, term, attachment or menu before you create it.",
                "Run it: wp foehn seed --class={$className}",
                'Run every seeder: wp foehn seed',
            ],
        );
    }
}
