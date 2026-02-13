<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\GeneratesFiles;
use Studiometa\Foehn\Console\Stubs\TaxonomyStub;
use Studiometa\Foehn\Console\WpCli;

use function Tempest\Support\str;

#[AsCliCommand(name: 'make:taxonomy', description: 'Create a new taxonomy class', longDescription: <<<'DOC'
    ## OPTIONS

    <name>
    : The taxonomy slug (e.g., 'genre', 'location')

    [--class=<class>]
    : Custom class name (defaults to PascalCase of name)

    [--singular=<singular>]
    : Singular label (defaults to humanized name)

    [--plural=<plural>]
    : Plural label (defaults to singular + 's')

    [--post-types=<post-types>]
    : Comma-separated list of post types (defaults to 'post')

    [--hierarchical]
    : Make the taxonomy hierarchical (like categories)

    [--force]
    : Overwrite existing file

    [--dry-run]
    : Show what would be created without creating

    ## EXAMPLES

        # Create a simple taxonomy
        wp tempest make:taxonomy genre

        # Create taxonomy for custom post type
        wp tempest make:taxonomy project-type --post-types=project

        # Create hierarchical taxonomy
        wp tempest make:taxonomy location --hierarchical --singular="Location" --plural="Locations"

        # Preview what would be created
        wp tempest make:taxonomy genre --dry-run
    DOC)]
final class MakeTaxonomyCommand implements CliCommandInterface
{
    use GeneratesFiles;

    public function __construct(
        private readonly WpCli $cli,
    ) {}

    public function __invoke(array $args, array $assocArgs): void
    {
        $name = $args[0] ?? null;

        if ($name === null) {
            $this->cli->error('Please provide a taxonomy name.');

            return;
        }

        $className = $assocArgs['class'] ?? str($name)->pascal()->toString() . 'Term';
        $singular = $assocArgs['singular'] ?? str($name)->replace('-', ' ')->title()->toString();
        $plural = $assocArgs['plural'] ?? $singular . 's';
        $postTypes = ($assocArgs['post-types'] ?? null) !== null
            ? array_map('trim', explode(',', $assocArgs['post-types']))
            : ['post'];
        $hierarchical = ($assocArgs['hierarchical'] ?? null) !== null;
        $force = ($assocArgs['force'] ?? null) !== null;
        $dryRun = ($assocArgs['dry-run'] ?? null) !== null;

        $targetPath = $this->getTargetPath('Taxonomies', $className);

        if (!$dryRun && !$this->shouldGenerate($targetPath, $force)) {
            return;
        }

        // Format post types array for replacement
        $postTypesCode = "['" . implode("', '", $postTypes) . "']";

        $content = $this->generateClassFile(
            stubClass: TaxonomyStub::class,
            targetPath: $targetPath,
            replacements: [
                'dummy-taxonomy' => $name,
                "['post']" => $postTypesCode,
                'Dummy Singular' => $singular,
                'Dummy Plural' => $plural,
                'hierarchical: false' => 'hierarchical: ' . ($hierarchical ? 'true' : 'false'),
            ],
            dryRun: $dryRun,
        );

        if ($dryRun) {
            $this->displayDryRun($targetPath, (string) $content);

            return;
        }

        $this->cli->success("Taxonomy created: {$this->cli->getRelativePath($targetPath)}");
    }
}
