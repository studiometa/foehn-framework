<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\ClassFileGenerator;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\GenerationRequest;
use Studiometa\Foehn\Console\Stubs\ContextProviderStub;
use Studiometa\Foehn\Console\WpCli;

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
        : Overwrite existing file

        [--dry-run]
        : Show what would be created without creating

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
)]
final class MakeContextProviderCommand implements CliCommandInterface
{
    public function __construct(
        private readonly WpCli $cli,
        private readonly ClassFileGenerator $generator,
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
        $force = ($assocArgs['force'] ?? null) !== null;
        $dryRun = ($assocArgs['dry-run'] ?? null) !== null;

        $file = $this->generator->generate(new GenerationRequest(
            stub: ContextProviderStub::class,
            subdirectory: 'ContextProviders',
            className: $className,
            attributeArguments: ['templates' => $templates],
        ));

        if ($dryRun) {
            $this->cli->previewGeneratedFile($file);

            return;
        }

        if (!$this->generator->write($file, $force)) {
            $this->cli->reportFileExists($file);

            return;
        }

        $this->cli->success("Context provider created: {$this->cli->getRelativePath($file->path)}");
    }
}
