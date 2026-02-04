<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Console\Commands;

use Studiometa\WPTempest\Attributes\AsCliCommand;
use Studiometa\WPTempest\Console\CliCommandInterface;
use Studiometa\WPTempest\Console\GeneratesFiles;
use Studiometa\WPTempest\Console\Stubs\TemplateControllerStub;
use Studiometa\WPTempest\Console\WpCli;

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

    ## EXAMPLES

        # Create a single post controller
        wp tempest make:controller single

        # Create a controller for multiple templates
        wp tempest make:controller posts --templates=single-post,archive-post

        # Create a custom page controller
        wp tempest make:controller page-contact --class=ContactController
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

        $targetPath = $this->getTargetPath('Controllers', $className);

        if (!$this->shouldGenerate($targetPath, $force)) {
            return;
        }

        // Format templates for replacement (single string or array)
        $templatesCode = count($templates) === 1 ? "'{$templates[0]}'" : "['" . implode("', '", $templates) . "']";

        // Get template file name for the Twig template
        $templateName = $templates[0];

        $this->generateClassFile(stubClass: TemplateControllerStub::class, targetPath: $targetPath, replacements: [
            "'dummy-template'" => $templatesCode,
            'dummy-template.twig' => "{$templateName}.twig",
        ]);

        $this->cli->success("Controller created: {$this->cli->getRelativePath($targetPath)}");
        $this->cli->line('');
        $this->cli->log("Don't forget to create your Twig template at:");
        $this->cli->log("  templates/{$templateName}.twig");
    }
}
