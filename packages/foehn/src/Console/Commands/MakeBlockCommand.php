<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\ClassFileGenerator;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\GenerationRequest;
use Studiometa\Foehn\Console\Stubs\BlockStub;
use Studiometa\Foehn\Console\Stubs\InteractiveBlockStub;
use Studiometa\Foehn\Console\WpCli;

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

    [--dry-run]
    : Show what would be created without creating

    ## EXAMPLES

        # Create a simple block
        wp tempest make:block hero

        # Create with custom title
        wp tempest make:block featured-posts --title="Featured Posts"

        # Create an interactive block
        wp tempest make:block counter --interactive

        # Create with custom namespace
        wp tempest make:block card --namespace=theme-blocks

        # Preview what would be created
        wp tempest make:block hero --dry-run
    DOC)]
final class MakeBlockCommand implements CliCommandInterface
{
    public function __construct(
        private readonly WpCli $cli,
        private readonly ClassFileGenerator $generator,
    ) {}

    public function __invoke(array $args, array $assocArgs): void
    {
        $name = $args[0] ?? null;

        if ($name === null) {
            $this->cli->error('Please provide a block name.');

            return;
        }

        $className = $assocArgs['class'] ?? str($name)->pascal()->toString() . 'Block';
        $title = $assocArgs['title'] ?? str($name)->replace('-', ' ')->title()->toString();
        $category = $assocArgs['category'] ?? 'theme';
        $namespace = $assocArgs['namespace'] ?? 'theme';
        $interactive = ($assocArgs['interactive'] ?? null) !== null;
        $force = ($assocArgs['force'] ?? null) !== null;
        $dryRun = ($assocArgs['dry-run'] ?? null) !== null;

        $fullBlockName = $namespace . '/' . $name;
        $file = $this->generator->generate(new GenerationRequest(
            stub: $interactive ? InteractiveBlockStub::class : BlockStub::class,
            subdirectory: 'Blocks',
            className: $className,
            attributeArguments: [
                'name' => $fullBlockName,
                'title' => $title,
                'category' => $category,
            ],
        ));

        if ($dryRun) {
            $this->cli->previewGeneratedFile($file);

            return;
        }

        if (!$this->generator->write($file, $force)) {
            $this->cli->reportFileExists($file);

            return;
        }

        $this->cli->success("Block created: {$this->cli->getRelativePath($file->path)}");
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
