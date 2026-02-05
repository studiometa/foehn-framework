<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\GeneratesFiles;
use Studiometa\Foehn\Console\Stubs\TemplateControllerStub;
use Studiometa\Foehn\Console\WpCli;

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
    : Overwrite existing file

    [--dry-run]
    : Show what would be created without creating

    ## EXAMPLES

        # Create a single post controller
        wp tempest make:controller single

        # Create a controller for multiple templates
        wp tempest make:controller posts --templates=single-post,archive-post

        # Create a custom page controller
        wp tempest make:controller page-contact --class=ContactController

        # Preview what would be created
        wp tempest make:controller single --dry-run
    DOC)]
final class MakeControllerCommand implements CliCommandInterface
{
    use GeneratesFiles;

    public function __construct(
        private readonly WpCli $cli,
    ) {}

    public function __invoke(array $args, array $assocArgs): void
    {
        $name = $args[0] ?? null;

        if ($name === null) {
            $this->cli->error('Please provide a controller name.');

            return;
        }

        $className = $assocArgs['class'] ?? str($name)->pascal()->toString() . 'Controller';
        $templates = isset($assocArgs['templates'])
            ? array_map('trim', explode(',', $assocArgs['templates']))
            : [$name];
        $force = isset($assocArgs['force']);
        $dryRun = isset($assocArgs['dry-run']);

        $targetPath = $this->getTargetPath('Controllers', $className);

        if (!$dryRun && !$this->shouldGenerate($targetPath, $force)) {
            return;
        }

        // Format templates for replacement (single string or array)
        $templatesCode = count($templates) === 1 ? "'{$templates[0]}'" : "['" . implode("', '", $templates) . "']";

        // Get template file name for the Twig template
        $templateName = $templates[0];

        $content = $this->generateClassFile(
            stubClass: TemplateControllerStub::class,
            targetPath: $targetPath,
            replacements: [
                "'dummy-template'" => $templatesCode,
                'dummy-template.twig' => "{$templateName}.twig",
            ],
            dryRun: $dryRun,
        );

        if ($dryRun) {
            $this->displayDryRun($targetPath, (string) $content);

            return;
        }

        $this->cli->success("Controller created: {$this->cli->getRelativePath($targetPath)}");
        $this->cli->line('');
        $this->cli->log("Don't forget to create your Twig template at:");
        $this->cli->log("  templates/{$templateName}.twig");
    }
}
