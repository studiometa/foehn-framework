<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\GeneratesFiles;
use Studiometa\Foehn\Console\Stubs\ModelStub;
use Studiometa\Foehn\Console\Stubs\PostTypeStub;
use Studiometa\Foehn\Console\WpCli;

use function Tempest\Support\str;

#[AsCliCommand(name: 'make:model', description: 'Create a new Timber model class', longDescription: <<<'DOC'
    ## OPTIONS

    <name>
    : The model name (e.g., 'Product', 'Event')

    [--post-type]
    : Include #[AsPostType] attribute to register a custom post type

    [--slug=<slug>]
    : Custom post type slug (defaults to kebab-case of name)

    [--singular=<singular>]
    : Singular label (defaults to name)

    [--plural=<plural>]
    : Plural label (defaults to singular + 's')

    [--force]
    : Overwrite existing file

    [--dry-run]
    : Show what would be created without creating

    ## EXAMPLES

        # Create a simple Timber model
        wp tempest make:model Product

        # Create a model with post type registration
        wp tempest make:model Product --post-type

        # Create with custom labels
        wp tempest make:model TeamMember --post-type --singular="Team Member" --plural="Team Members"

        # Preview what would be created
        wp tempest make:model Product --post-type --dry-run
    DOC)]
final class MakeModelCommand implements CliCommandInterface
{
    use GeneratesFiles;

    public function __construct(
        private readonly WpCli $cli,
    ) {}

    public function __invoke(array $args, array $assocArgs): void
    {
        $name = $args[0] ?? null;

        if ($name === null) {
            $this->cli->error('Please provide a model name.');

            return;
        }

        $className = str($name)->pascal()->toString();
        $withPostType = isset($assocArgs['post-type']);
        $slug = $assocArgs['slug'] ?? str($name)->kebab()->toString();
        $singular = $assocArgs['singular'] ?? str($name)->replace('-', ' ')->title()->toString();
        $plural = $assocArgs['plural'] ?? $singular . 's';
        $force = isset($assocArgs['force']);
        $dryRun = isset($assocArgs['dry-run']);

        $targetPath = $this->getTargetPath('Models', $className);

        if (!$dryRun && !$this->shouldGenerate($targetPath, $force)) {
            return;
        }

        // Choose stub based on whether post type is included
        $stubClass = $withPostType ? PostTypeStub::class : ModelStub::class;

        $replacements = $withPostType
            ? [
                'dummy-post-type' => $slug,
                'Dummy Singular' => $singular,
                'Dummy Plural' => $plural,
            ]
            : [
                'DummyModel' => $className,
            ];

        $content = $this->generateClassFile(
            stubClass: $stubClass,
            targetPath: $targetPath,
            replacements: $replacements,
            dryRun: $dryRun,
        );

        if ($dryRun) {
            $this->displayDryRun($targetPath, (string) $content);

            return;
        }

        $this->cli->success("Model created: {$this->cli->getRelativePath($targetPath)}");

        if ($withPostType) {
            $this->cli->line('');
            $this->cli->log("Don't forget to create your Twig template at:");
            $this->cli->log("  templates/single-{$slug}.twig");
            $this->cli->log("  templates/archive-{$slug}.twig (if archive is enabled)");
        }
    }
}
