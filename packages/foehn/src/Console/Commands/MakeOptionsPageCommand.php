<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\GeneratesFiles;
use Studiometa\Foehn\Console\Stubs\OptionsPageStub;
use Studiometa\Foehn\Console\WpCli;

use function Tempest\Support\str;

#[AsCliCommand(name: 'make:options-page', description: 'Create a new ACF options page class', longDescription: <<<'DOC'
    ## OPTIONS

    <name>
    : The options page name (e.g., 'ThemeSettings', 'FooterOptions')

    [--title=<title>]
    : Page title (defaults to humanized name)

    [--menu-title=<menu-title>]
    : Menu title (defaults to title)

    [--parent=<parent>]
    : Parent menu slug for submenu (e.g., 'theme-settings')

    [--icon=<icon>]
    : Dashicon class for top-level menu (defaults to 'dashicons-admin-generic')

    [--force]
    : Overwrite existing file

    [--dry-run]
    : Show what would be created without creating

    ## EXAMPLES

        # Create a top-level options page
        wp tempest make:options-page ThemeSettings

        # Create a submenu options page
        wp tempest make:options-page FooterSettings --parent=theme-settings

        # Create with custom icon
        wp tempest make:options-page SocialSettings --icon=dashicons-share

        # Preview what would be created
        wp tempest make:options-page ThemeSettings --dry-run
    DOC)]
final class MakeOptionsPageCommand implements CliCommandInterface
{
    use GeneratesFiles;

    public function __construct(
        private readonly WpCli $cli,
    ) {}

    public function __invoke(array $args, array $assocArgs): void
    {
        $name = $args[0] ?? null;

        if ($name === null) {
            $this->cli->error('Please provide an options page name.');

            return;
        }

        $className = str($name)->pascal()->toString();
        $slug = str($name)->kebab()->toString();
        $title = $assocArgs['title'] ?? str($name)->replace(['-', '_'], ' ')->title()->toString();
        $menuTitle = $assocArgs['menu-title'] ?? $title;
        $parent = $assocArgs['parent'] ?? '';
        $icon = $assocArgs['icon'] ?? 'dashicons-admin-generic';
        $force = ($assocArgs['force'] ?? null) !== null;
        $dryRun = ($assocArgs['dry-run'] ?? null) !== null;

        $targetPath = $this->getTargetPath('Fields/Options', $className);

        if (!$dryRun && !$this->shouldGenerate($targetPath, $force)) {
            return;
        }

        $content = $this->generateClassFile(
            stubClass: OptionsPageStub::class,
            targetPath: $targetPath,
            replacements: [
                'dummy_options' => str($name)->snake()->toString(),
                "pageTitle: 'Dummy Options'" => "pageTitle: '{$title}'",
                "menuTitle: 'Dummy Options'" => "menuTitle: '{$menuTitle}'",
                "menuSlug: 'dummy-options'" => "menuSlug: '{$slug}'",
                "parentSlug: ''" => "parentSlug: '{$parent}'",
                "iconUrl: 'dashicons-admin-generic'" => "iconUrl: '{$icon}'",
            ],
            dryRun: $dryRun,
        );

        if ($dryRun) {
            $this->displayDryRun($targetPath, (string) $content);

            return;
        }

        $this->cli->success("Options page created: {$this->cli->getRelativePath($targetPath)}");
        $this->cli->line('');
        $this->cli->log('Edit the fields() method to define your ACF fields using FieldsBuilder.');
        $this->cli->line('');
        $this->cli->log('Access options in templates with:');
        $this->cli->log("  {{ fn('get_field', 'field_name', 'option') }}");
        $this->cli->log('');
        $this->cli->log('Or use the static helper:');
        $this->cli->log("  {$className}::get('field_name')");
    }
}
