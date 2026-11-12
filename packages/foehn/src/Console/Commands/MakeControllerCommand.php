<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\GenerationRequest;
use Studiometa\Foehn\Console\Scaffolder;
use Studiometa\Foehn\Console\Stubs\TemplateControllerStub;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\Discovery\TemplateControllerDiscovery;

use function Tempest\Support\str;

#[AsCliCommand(name: 'make:controller', description: 'Create a new template controller class', longDescription: <<<'DOC'
    ## OPTIONS

    <name>
    : The controller name (e.g., 'single', 'archive-post', 'page-contact')

    [--class=<class>]
    : Custom class name (defaults to PascalCase of name + Controller)

    [--templates=<templates>]
    : Comma-separated template patterns to match (defaults to name)
      Supports wildcards: 'single-*', 'archive-*'

    [--force]
    : Overwrite an existing file. Also lets the command run in the production environment

    [--dry-run]
    : Write nothing. Show the file that would be written, or report that it exists

    [--format=<format>]
    : Output format. text (default) or json. json prints the written files, their status and the next steps

    ## EXAMPLES

        # Create a single post controller
        wp foehn make:controller single

        # Create a controller for multiple templates
        wp foehn make:controller posts --templates=single-post,archive-post

        # Create a custom page controller
        wp foehn make:controller page-contact --class=ContactController

        # Preview what would be created
        wp foehn make:controller single --dry-run
    DOC, writes: true)]
final class MakeControllerCommand implements CliCommandInterface
{
    public function __construct(
        private readonly WpCli $cli,
        private readonly Scaffolder $scaffolder,
    ) {}

    public function __invoke(array $args, array $assocArgs): void
    {
        $name = $args[0] ?? null;

        if ($name === null) {
            $this->cli->error('Please provide a controller name.');

            return;
        }

        $className = $assocArgs['class'] ?? str($name)->pascal()->toString() . 'Controller';
        $templates = ($assocArgs['templates'] ?? null) !== null
            ? array_map('trim', explode(',', $assocArgs['templates']))
            : [$name];

        // A single template stays a string, matching the attribute's own union type
        $templatesArgument = count($templates) === 1 ? $templates[0] : $templates;

        // Get template file name for the Twig template
        $templateName = $templates[0];

        $this->scaffolder->scaffold(
            new GenerationRequest(
                stub: TemplateControllerStub::class,
                discovery: TemplateControllerDiscovery::class,
                subdirectory: 'Controllers',
                className: $className,
                attributeArguments: ['templates' => $templatesArgument],
                // The stub renders one template; the attribute may match several.
                bodyReplacements: [
                    'handle' => ["'dummy-template'" => "'{$templateName}'"],
                ],
            ),
            $assocArgs,
            'Controller created',
            ["Create the Twig template: templates/{$templateName}.twig"],
        );
    }
}
