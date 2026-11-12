<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\GenerationRequest;
use Studiometa\Foehn\Console\Scaffolder;
use Studiometa\Foehn\Console\Stubs\ContextProviderStub;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\Discovery\ContextProviderDiscovery;

use function Tempest\Support\str;

#[AsCliCommand(
    name: 'make:context-provider',
    description: 'Create a new context provider class',
    longDescription: <<<'DOC'
        ## OPTIONS

        <name>
        : The provider name (e.g., 'header', 'single-post')

        [--class=<class>]
        : Custom class name (defaults to PascalCase of name + ContextProvider)

        [--templates=<templates>]
        : Comma-separated template patterns to match (defaults to name and name-*)

        [--force]
        : Overwrite an existing file. Also lets the command run in the production environment

        [--dry-run]
        : Write nothing. Show the file that would be written, or report that it exists

        [--format=<format>]
        : Output format. text (default) or json. json prints the written files, their status and the next steps

        ## EXAMPLES

            # Create a provider for header template
            wp foehn make:context-provider header

            # Create a provider for multiple templates
            wp foehn make:context-provider post --templates=single-post,archive-post

            # Create a global provider
            wp foehn make:context-provider global --templates=*

            # Preview what would be created
            wp foehn make:context-provider header --dry-run
        DOC,
    writes: true,
)]
final class MakeContextProviderCommand implements CliCommandInterface
{
    public function __construct(
        private readonly WpCli $cli,
        private readonly Scaffolder $scaffolder,
    ) {}

    public function __invoke(array $args, array $assocArgs): void
    {
        $name = $args[0] ?? null;

        if ($name === null) {
            $this->cli->error('Please provide a provider name.');

            return;
        }

        $className = $assocArgs['class'] ?? str($name)->pascal()->toString() . 'ContextProvider';
        $templates = ($assocArgs['templates'] ?? null) !== null
            ? array_map('trim', explode(',', $assocArgs['templates']))
            : [$name, $name . '-*'];

        $this->scaffolder->scaffold(
            new GenerationRequest(
                stub: ContextProviderStub::class,
                discovery: ContextProviderDiscovery::class,
                subdirectory: 'ContextProviders',
                className: $className,
                attributeArguments: ['templates' => $templates],
            ),
            $assocArgs,
            'Context provider created',
        );
    }
}
