<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\GenerationRequest;
use Studiometa\Foehn\Console\Scaffolder;
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
    : Overwrite an existing file. Also lets the command run in the production environment

    [--dry-run]
    : Write nothing. Show the file that would be written, or report that it exists

    [--format=<format>]
    : Output format. text (default) or json. json prints the written files, their status and the next steps

    ## EXAMPLES

        # Create a top-level options page
        wp foehn-acf make:options-page ThemeSettings

        # Create a submenu options page
        wp foehn-acf make:options-page FooterSettings --parent=theme-settings

        # Create with custom icon
        wp foehn-acf make:options-page SocialSettings --icon=dashicons-share

        # Preview what would be created
        wp foehn-acf make:options-page ThemeSettings --dry-run
    DOC, writes: true)]
final class MakeOptionsPageCommand implements CliCommandInterface
{
    public function __construct(
        private readonly WpCli $cli,
        private readonly Scaffolder $scaffolder,
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

        $this->scaffolder->scaffold(
            new GenerationRequest(
                stub: OptionsPageStub::class,
                subdirectory: 'Fields/Options',
                className: $className,
                attributeArguments: [
                    'pageTitle' => $title,
                    'menuTitle' => $menuTitle,
                    'menuSlug' => $slug,
                    // An empty parent means a top level page, which ACF expects as null
                    'parentSlug' => $parent === '' ? null : $parent,
                    'iconUrl' => $icon,
                ],
                // The builder is seeded with the option group name inside fields()
                bodyReplacements: [
                    'fields' => ["'dummy_options'" => "'" . str($name)->snake()->toString() . "'"],
                ],
            ),
            $assocArgs,
            'Options page created',
            [
                'Define the ACF fields in fields() with FieldsBuilder.',
                "Read an option in Twig with {{ fn('get_field', 'field_name', 'option') }}, or in PHP with {$className}::get('field_name').",
            ],
        );
    }
}
