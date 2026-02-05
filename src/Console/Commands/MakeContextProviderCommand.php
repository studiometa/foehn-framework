<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\GeneratesFiles;
use Studiometa\Foehn\Console\Stubs\ContextProviderStub;
use Studiometa\Foehn\Console\WpCli;

use function Tempest\Support\str;

#[AsCliCommand(name: 'make:context-provider', description: 'Create a new context provider class', longDescription: <<<'DOC'
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
        wp tempest make:context-provider header

        # Create a provider for multiple templates
        wp tempest make:context-provider post --templates=single-post,archive-post

        # Create a global provider
        wp tempest make:context-provider global --templates=*

        # Preview what would be created
        wp tempest make:context-provider header --dry-run
    DOC)]
final class MakeContextProviderCommand implements CliCommandInterface
{
    use GeneratesFiles;

    public function __construct(
        private readonly WpCli $cli,
    ) {}

    public function __invoke(array $args, array $assocArgs): void
    {
        $name = $args[0] ?? null;

        if ($name === null) {
            $this->cli->error('Please provide a provider name.');

            return;
        }

        $className = $assocArgs['class'] ?? str($name)->pascal()->toString() . 'ContextProvider';
        $templates = isset($assocArgs['templates'])
            ? array_map('trim', explode(',', $assocArgs['templates']))
            : [$name, $name . '-*'];
        $force = isset($assocArgs['force']);
        $dryRun = isset($assocArgs['dry-run']);

        $targetPath = $this->getTargetPath('ContextProviders', $className);

        if (!$dryRun && !$this->shouldGenerate($targetPath, $force)) {
            return;
        }

        // Format templates array for replacement
        $templatesCode = "['" . implode("', '", $templates) . "']";

        $content = $this->generateClassFile(
            stubClass: ContextProviderStub::class,
            targetPath: $targetPath,
            replacements: [
                "['dummy-template', 'dummy-template-*']" => $templatesCode,
            ],
            dryRun: $dryRun,
        );

        if ($dryRun) {
            $this->displayDryRun($targetPath, (string) $content);

            return;
        }

        $this->cli->success("Context provider created: {$this->cli->getRelativePath($targetPath)}");
    }
}
