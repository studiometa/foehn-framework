<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\GenerationRequest;
use Studiometa\Foehn\Console\Scaffolder;
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
    : Write nothing. Show the file that would be written, or report that it exists

    [--format=<format>]
    : Output format. text (default) or json. json prints the written files, their status and the next steps

    ## EXAMPLES

        # Create a simple Timber model
        wp foehn make:model Product

        # Create a model with post type registration
        wp foehn make:model Product --post-type

        # Create with custom labels
        wp foehn make:model TeamMember --post-type --singular="Team Member" --plural="Team Members"

        # Preview what would be created
        wp foehn make:model Product --post-type --dry-run
    DOC)]
final class MakeModelCommand implements CliCommandInterface
{
    public function __construct(
        private readonly WpCli $cli,
        private readonly Scaffolder $scaffolder,
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

        $this->scaffolder->scaffold(
            $request,
            $assocArgs,
            'Model created',
            $withPostType
                ? [
                    "Create the Twig templates: templates/single-{$slug}.twig, templates/archive-{$slug}.twig (if the archive is enabled)",
                ]
                : [],
        );
    }
}
