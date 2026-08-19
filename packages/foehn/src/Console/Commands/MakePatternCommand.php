<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\ClassFileGenerator;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\GenerationRequest;
use Studiometa\Foehn\Console\Stubs\BlockPatternStub;
use Studiometa\Foehn\Console\WpCli;

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
    : Show what would be created without creating

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
        private readonly ClassFileGenerator $generator,
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
        $force = ($assocArgs['force'] ?? null) !== null;
        $dryRun = ($assocArgs['dry-run'] ?? null) !== null;

        $fullPatternName = $namespace . '/' . $name;
        $file = $this->generator->generate(new GenerationRequest(
            stub: BlockPatternStub::class,
            subdirectory: 'Patterns',
            className: $className,
            attributeArguments: [
                'name' => $fullPatternName,
                'title' => $title,
                'description' => $description,
                'categories' => $categories,
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

        $this->cli->success("Block pattern created: {$this->cli->getRelativePath($file->path)}");
        $this->cli->line('');
        $this->cli->log("Don't forget to create your Twig template at:");
        $this->cli->log("  templates/patterns/{$name}.twig");
    }
}
