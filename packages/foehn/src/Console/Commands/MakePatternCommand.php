<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\GenerationRequest;
use Studiometa\Foehn\Console\Scaffolder;
use Studiometa\Foehn\Console\Stubs\BlockPatternStub;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\Discovery\BlockPatternDiscovery;

use function Tempest\Support\str;

#[AsCliCommand(name: 'make:pattern', description: 'Create a new block pattern class', longDescription: <<<'DOC'
    ## OPTIONS

    <name>
    : The pattern name without namespace (e.g., 'hero-section', 'cta-banner')

    [--class=<class>]
    : Custom class name (defaults to PascalCase of name + Pattern)

    [--title=<title>]
    : Pattern title (defaults to humanized name)

    [--description=<description>]
    : Pattern description

    [--categories=<categories>]
    : Comma-separated list of pattern categories (defaults to 'featured')

    [--namespace=<namespace>]
    : Pattern namespace (defaults to 'theme')

    [--force]
    : Overwrite existing file

    [--dry-run]
    : Write nothing. Show the file that would be written, or report that it exists

    [--format=<format>]
    : Output format. text (default) or json. json prints the written files, their status and the next steps

    ## EXAMPLES

        # Create a simple pattern
        wp foehn make:pattern hero-section

        # Create with custom title and description
        wp foehn make:pattern cta-banner --title="Call to Action" --description="A banner with a call to action button"

        # Create with multiple categories
        wp foehn make:pattern pricing-table --categories=featured,commerce

        # Preview what would be created
        wp foehn make:pattern hero-section --dry-run
    DOC)]
final class MakePatternCommand implements CliCommandInterface
{
    public function __construct(
        private readonly WpCli $cli,
        private readonly Scaffolder $scaffolder,
    ) {}

    public function __invoke(array $args, array $assocArgs): void
    {
        $name = $args[0] ?? null;

        if ($name === null) {
            $this->cli->error('Please provide a pattern name.');

            return;
        }

        $className = $assocArgs['class'] ?? str($name)->pascal()->toString() . 'Pattern';
        $title = $assocArgs['title'] ?? str($name)->replace('-', ' ')->title()->toString();
        $description = $assocArgs['description'] ?? 'A custom block pattern.';
        $categories = ($assocArgs['categories'] ?? null) !== null
            ? array_map('trim', explode(',', $assocArgs['categories']))
            : ['featured'];
        $namespace = $assocArgs['namespace'] ?? 'theme';

        $fullPatternName = $namespace . '/' . $name;
        $this->scaffolder->scaffold(
            new GenerationRequest(
                stub: BlockPatternStub::class,
                discovery: BlockPatternDiscovery::class,
                subdirectory: 'Patterns',
                className: $className,
                attributeArguments: [
                    'name' => $fullPatternName,
                    'title' => $title,
                    'description' => $description,
                    'categories' => $categories,
                ],
            ),
            $assocArgs,
            'Block pattern created',
            ["Create the Twig template: templates/patterns/{$name}.twig"],
        );
    }
}
