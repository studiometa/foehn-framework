<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\GeneratesFiles;
use Studiometa\Foehn\Console\Stubs\MenuStub;
use Studiometa\Foehn\Console\WpCli;

use function Tempest\Support\str;

#[AsCliCommand(name: 'make:menu', description: 'Create a new navigation menu class', longDescription: <<<'DOC'
    ## OPTIONS

    <name>
    : The menu class name (e.g., 'HeaderMenu', 'FooterMenu')

    [--location=<location>]
    : Menu location identifier (defaults to kebab-case of name)

    [--description=<description>]
    : Menu description shown in admin (defaults to humanized name)

    [--force]
    : Overwrite existing file

    [--dry-run]
    : Show what would be created without creating

    ## EXAMPLES

        # Create a header menu
        wp tempest make:menu HeaderMenu

        # Create with custom location
        wp tempest make:menu PrimaryNav --location=primary --description="Primary Navigation"

        # Create a footer menu
        wp tempest make:menu FooterMenu --description="Footer Navigation Links"

        # Preview what would be created
        wp tempest make:menu HeaderMenu --dry-run
    DOC)]
final class MakeMenuCommand implements CliCommandInterface
{
    use GeneratesFiles;

    public function __construct(
        private readonly WpCli $cli,
    ) {}

    public function __invoke(array $args, array $assocArgs): void
    {
        $name = $args[0] ?? null;

        if ($name === null) {
            $this->cli->error('Please provide a menu class name.');

            return;
        }

        $className = str($name)->pascal()->toString();
        $location = $assocArgs['location'] ?? str($name)->replace('Menu', '')->kebab()->toString();
        $description = $assocArgs['description'] ?? str($name)->replace(['-', '_'], ' ')->title()->toString();
        $force = isset($assocArgs['force']);
        $dryRun = isset($assocArgs['dry-run']);

        $targetPath = $this->getTargetPath('Menus', $className);

        if (!$dryRun && !$this->shouldGenerate($targetPath, $force)) {
            return;
        }

        $content = $this->generateClassFile(
            stubClass: MenuStub::class,
            targetPath: $targetPath,
            replacements: [
                "'dummy-menu'" => "'{$location}'",
                "description: 'Dummy Menu'" => "description: '{$description}'",
                'DummyMenu' => $className,
            ],
            dryRun: $dryRun,
        );

        if ($dryRun) {
            $this->displayDryRun($targetPath, (string) $content);

            return;
        }

        $this->cli->success("Menu created: {$this->cli->getRelativePath($targetPath)}");
        $this->cli->line('');
        $this->cli->log('Menu location registered:');
        $this->cli->log("  Location: {$location}");
        $this->cli->log("  Description: {$description}");
        $this->cli->line('');
        $this->cli->log('Use in templates:');
        $this->cli->log("  {% set menu = {$className}::get() %}");
        $this->cli->log('  {% for item in menu.items %}');
        $this->cli->log('    <a href="{{ item.link }}">{{ item.title }}</a>');
        $this->cli->log('  {% endfor %}');
    }
}
