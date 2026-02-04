<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Console\Commands;

use Studiometa\WPTempest\Attributes\AsCliCommand;
use Studiometa\WPTempest\Console\CliCommandInterface;
use Studiometa\WPTempest\Console\GeneratesFiles;
use Studiometa\WPTempest\Console\Stubs\BlockStub;
use Studiometa\WPTempest\Console\Stubs\InteractiveBlockStub;
use Studiometa\WPTempest\Console\WpCli;

use function Tempest\Support\str;

#[AsCliCommand(name: 'make:block', description: 'Create a new native Gutenberg block class', longDescription: <<<'DOC'
    ## OPTIONS

    <name>
    : The block name without namespace (e.g., 'hero', 'card')

    [--class=<class>]
    : Custom class name (defaults to PascalCase of name + Block)

    [--title=<title>]
    : Block title (defaults to humanized name)

    [--category=<category>]
    : Block category (defaults to 'theme')

    [--namespace=<namespace>]
    : Block namespace (defaults to 'theme')

    [--interactive]
    : Create an interactive block with WordPress Interactivity API

    [--force]
    : Overwrite existing file

    ## EXAMPLES

        # Create a simple block
        wp tempest make:block hero

        # Create with custom title
        wp tempest make:block featured-posts --title="Featured Posts"

        # Create an interactive block
        wp tempest make:block counter --interactive

        # Create with custom namespace
        wp tempest make:block card --namespace=theme-blocks
    DOC)]
final class MakeBlockCommand implements CliCommandInterface
{
    use GeneratesFiles;

    public function __construct(
        private readonly WpCli $cli,
    ) {}

    public function __invoke(array $args, array $assocArgs): void
    {
        $name = $args[0] ?? null;

        if ($name === null) {
            $this->cli->error('Please provide a block name.');

            return;
        }

        $className = $assocArgs['class'] ?? str($name)->studly()->toString() . 'Block';
        $title = $assocArgs['title'] ?? str($name)->headline()->toString();
        $category = $assocArgs['category'] ?? 'theme';
        $namespace = $assocArgs['namespace'] ?? 'theme';
        $interactive = isset($assocArgs['interactive']);
        $force = isset($assocArgs['force']);

        $fullBlockName = $namespace . '/' . $name;
        $targetPath = $this->getTargetPath('Blocks', $className);

        if (!$this->shouldGenerate($targetPath, $force)) {
            return;
        }

        $stubClass = $interactive ? InteractiveBlockStub::class : BlockStub::class;
        $stubBlockName = $interactive ? 'theme/dummy-interactive-block' : 'theme/dummy-block';
        $stubTitle = $interactive ? 'Dummy Interactive Block' : 'Dummy Block';

        $this->generateClassFile(stubClass: $stubClass, targetPath: $targetPath, replacements: [
            $stubBlockName => $fullBlockName,
            $stubTitle => $title,
            "category: 'theme'" => "category: '{$category}'",
        ]);

        $this->cli->success("Block created: {$this->cli->getRelativePath($targetPath)}");
        $this->cli->line('');
        $this->cli->log("Don't forget to create your Twig template at:");
        $this->cli->log("  templates/blocks/{$name}.twig");

        if ($interactive) {
            $this->cli->line('');
            $this->cli->log('For the Interactivity API, create your view script at:');
            $this->cli->log("  assets/scripts/blocks/{$name}/view.js");
        }
    }
}
