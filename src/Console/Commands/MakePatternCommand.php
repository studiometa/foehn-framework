<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\GeneratesFiles;
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

    ## EXAMPLES

        # Create a simple pattern
        wp tempest make:pattern hero-section

        # Create with custom title and description
        wp tempest make:pattern cta-banner --title="Call to Action" --description="A banner with a call to action button"

        # Create with multiple categories
        wp tempest make:pattern pricing-table --categories=featured,commerce
    DOC)]
final class MakePatternCommand implements CliCommandInterface
{
    use GeneratesFiles;

    public function __construct(
        private readonly WpCli $cli,
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
        $categories = isset($assocArgs['categories'])
            ? array_map('trim', explode(',', $assocArgs['categories']))
            : ['featured'];
        $namespace = $assocArgs['namespace'] ?? 'theme';
        $force = isset($assocArgs['force']);

        $fullPatternName = $namespace . '/' . $name;
        $targetPath = $this->getTargetPath('Patterns', $className);

        if (!$this->shouldGenerate($targetPath, $force)) {
            return;
        }

        // Format categories array for replacement
        $categoriesCode = "['" . implode("', '", $categories) . "']";

        $this->generateClassFile(stubClass: BlockPatternStub::class, targetPath: $targetPath, replacements: [
            'theme/dummy-pattern' => $fullPatternName,
            'Dummy Pattern' => $title,
            'A custom block pattern.' => $description,
            "['featured']" => $categoriesCode,
        ]);

        $this->cli->success("Block pattern created: {$this->cli->getRelativePath($targetPath)}");
        $this->cli->line('');
        $this->cli->log("Don't forget to create your Twig template at:");
        $this->cli->log("  templates/patterns/{$name}.twig");
    }
}
