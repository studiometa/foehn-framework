<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\ClassFileGenerator;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\GenerationRequest;
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
    public function __construct(
        private readonly WpCli $cli,
        private readonly ClassFileGenerator $generator,
    ) {}

    public function __invoke(array $args, array $assocArgs): void
    {
        $name = $args[0] ?? null;

        if ($name === null) {
            $this->cli->error('Please provide a model name.');

            return;
        }

        $className = str($name)->pascal()->toString();
        $withPostType = ($assocArgs['post-type'] ?? null) !== null;
        $slug = $assocArgs['slug'] ?? str($name)->kebab()->toString();
        $singular = $assocArgs['singular'] ?? str($name)->replace('-', ' ')->title()->toString();
        $plural = $assocArgs['plural'] ?? $singular . 's';
        $force = ($assocArgs['force'] ?? null) !== null;
        $dryRun = ($assocArgs['dry-run'] ?? null) !== null;

        // A model that registers its own post type starts from the post type stub
        $request = $withPostType
            ? new GenerationRequest(
                stub: PostTypeStub::class,
                subdirectory: 'Models',
                className: $className,
                attributeArguments: [
                    'name' => $slug,
                    'singular' => $singular,
                    'plural' => $plural,
                ],
            )
            : new GenerationRequest(
                stub: ModelStub::class,
                subdirectory: 'Models',
                className: $className,
                replacements: ['DummyModel' => $className],
            );

        $file = $this->generator->generate($request);

        if ($dryRun) {
            $this->cli->previewGeneratedFile($file);

            return;
        }

        if (!$this->generator->write($file, $force)) {
            $this->cli->reportFileExists($file);

            return;
        }

        $this->cli->success("Model created: {$this->cli->getRelativePath($file->path)}");

        if ($withPostType) {
            $this->cli->line('');
            $this->cli->log("Don't forget to create your Twig template at:");
            $this->cli->log("  templates/single-{$slug}.twig");
            $this->cli->log("  templates/archive-{$slug}.twig (if archive is enabled)");
        }
    }
}
