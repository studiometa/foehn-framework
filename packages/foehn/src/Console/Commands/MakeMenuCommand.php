<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\GenerationRequest;
use Studiometa\Foehn\Console\Scaffolder;
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
    : Write nothing. Show the file that would be written, or report that it exists

    [--format=<format>]
    : Output format. text (default) or json. json prints the written files, their status and the next steps

    ## EXAMPLES

        # Create a header menu
        wp foehn make:menu HeaderMenu

        # Create with custom location
        wp foehn make:menu PrimaryNav --location=primary --description="Primary Navigation"

        # Create a footer menu
        wp foehn make:menu FooterMenu --description="Footer Navigation Links"

        # Preview what would be created
        wp foehn make:menu HeaderMenu --dry-run
    DOC)]
final class MakeMenuCommand implements CliCommandInterface
{
    public function __construct(
        private readonly WpCli $cli,
        private readonly Scaffolder $scaffolder,
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

        $this->scaffolder->scaffold(
            new GenerationRequest(
                stub: MenuStub::class,
                subdirectory: 'Menus',
                className: $className,
                attributeArguments: [
                    'location' => $location,
                    'description' => $description,
                ],
                bodyReplacements: [
                    'get' => ["'dummy-menu'" => "'{$location}'"],
                ],
                replacements: ['DummyMenu' => $className],
            ),
            $assocArgs,
            'Menu created',
            [
                "Location: {$location}",
                "Description: {$description}",
                "Use in Twig: {% set menu = {$className}::get() %}, then loop over menu.items.",
            ],
        );
    }
}
