<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Console\Commands;

use Studiometa\WPTempest\Attributes\AsCliCommand;
use Studiometa\WPTempest\Console\CliCommandInterface;
use Studiometa\WPTempest\Console\GeneratesFiles;
use Studiometa\WPTempest\Console\Stubs\ViewComposerStub;
use Studiometa\WPTempest\Console\WpCli;

use function Tempest\Support\str;

#[AsCliCommand(name: 'make:view-composer', description: 'Create a new view composer class', longDescription: <<<'DOC'
    ## OPTIONS

    <name>
    : The composer name (e.g., 'header', 'single-post')

    [--class=<class>]
    : Custom class name (defaults to PascalCase of name + Composer)

    [--templates=<templates>]
    : Comma-separated template patterns to match (defaults to name and name-*)

    [--force]
    : Overwrite existing file

    ## EXAMPLES

        # Create a composer for header template
        wp tempest make:view-composer header

        # Create a composer for multiple templates
        wp tempest make:view-composer post --templates=single-post,archive-post

        # Create a global composer
        wp tempest make:view-composer global --templates=*
    DOC)]
final class MakeViewComposerCommand implements CliCommandInterface
{
    use GeneratesFiles;

    public function __construct(
        private readonly WpCli $cli,
    ) {}

    public function __invoke(array $args, array $assocArgs): void
    {
        $name = $args[0] ?? null;

        if ($name === null) {
            $this->cli->error('Please provide a composer name.');

            return;
        }

        $className = $assocArgs['class'] ?? str($name)->studly()->toString() . 'Composer';
        $templates = isset($assocArgs['templates'])
            ? array_map('trim', explode(',', $assocArgs['templates']))
            : [$name, $name . '-*'];
        $force = isset($assocArgs['force']);

        $targetPath = $this->getTargetPath('ViewComposers', $className);

        if (!$this->shouldGenerate($targetPath, $force)) {
            return;
        }

        // Format templates array for replacement
        $templatesCode = "['" . implode("', '", $templates) . "']";

        $this->generateClassFile(stubClass: ViewComposerStub::class, targetPath: $targetPath, replacements: [
            "['dummy-template', 'dummy-template-*']" => $templatesCode,
        ]);

        $this->cli->success("View composer created: {$this->cli->getRelativePath($targetPath)}");
    }
}
