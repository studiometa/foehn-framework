<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\GenerationRequest;
use Studiometa\Foehn\Console\Scaffolder;
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
    : Write nothing. Show the file that would be written, or report that it exists

    [--format=<format>]
    : Output format. text (default) or json. json prints the written files, their status and the next steps

    ## EXAMPLES

        # Create a simple block
        wp foehn make:block hero

        # Create with custom title
        wp foehn make:block featured-posts --title="Featured Posts"

        # Create an interactive block
        wp foehn make:block counter --interactive

        # Create with custom namespace
        wp foehn make:block card --namespace=theme-blocks

        # Preview what would be created
        wp foehn make:block hero --dry-run
    DOC)]
final class MakeBlockCommand implements CliCommandInterface
{
    public function __construct(
        private readonly WpCli $cli,
        private readonly Scaffolder $scaffolder,
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

        $notes = ["Create the Twig template: templates/blocks/{$name}.twig"];

        if ($interactive) {
            $notes[] = "Create the Interactivity API view script: assets/scripts/blocks/{$name}/view.js";
        }

        $fullBlockName = $namespace . '/' . $name;
        $this->scaffolder->scaffold(
            new GenerationRequest(
                stub: $interactive ? InteractiveBlockStub::class : BlockStub::class,
                subdirectory: 'Blocks',
                className: $className,
                attributeArguments: [
                    'name' => $fullBlockName,
                    'title' => $title,
                    'category' => $category,
                ],
            ),
            $assocArgs,
            'Block created',
            $notes,
        );
    }
}
