<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\ClassFileGenerator;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\GenerationRequest;
use Studiometa\Foehn\Console\Stubs\PostTypeStub;
use Studiometa\Foehn\Console\WpCli;

use function Tempest\Support\str;

#[AsCliCommand(name: 'make:post-type', description: 'Create a new custom post type class', longDescription: <<<'DOC'
    ## OPTIONS

    <name>
    : The post type slug (e.g., 'project', 'team-member')

    [--class=<class>]
    : Custom class name (defaults to PascalCase of name)

    [--singular=<singular>]
    : Singular label (defaults to humanized name)

    [--plural=<plural>]
    : Plural label (defaults to singular + 's')

    [--force]
    : Overwrite existing file

    [--dry-run]
    : Show what would be created without creating

    ## EXAMPLES

        # Create a simple post type
        wp foehn make:post-type project

        # Create with custom labels
        wp foehn make:post-type team-member --singular="Team Member" --plural="Team Members"

        # Create with custom class name
        wp foehn make:post-type event --class=CalendarEvent

        # Preview what would be created
        wp foehn make:post-type project --dry-run
    DOC)]
final class MakePostTypeCommand implements CliCommandInterface
{
    public function __construct(
        private readonly WpCli $cli,
        private readonly ClassFileGenerator $generator,
    ) {}

    public function __invoke(array $args, array $assocArgs): void
    {
        $name = $args[0] ?? null;

        if ($name === null) {
            $this->cli->error('Please provide a post type name.');

            return;
        }

        $className = $assocArgs['class'] ?? str($name)->pascal()->toString() . 'Post';
        $singular = $assocArgs['singular'] ?? str($name)->replace('-', ' ')->title()->toString();
        $plural = $assocArgs['plural'] ?? $singular . 's';
        $force = ($assocArgs['force'] ?? null) !== null;
        $dryRun = ($assocArgs['dry-run'] ?? null) !== null;

        $file = $this->generator->generate(new GenerationRequest(
            stub: PostTypeStub::class,
            subdirectory: 'PostTypes',
            className: $className,
            attributeArguments: [
                'name' => $name,
                'singular' => $singular,
                'plural' => $plural,
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

        $this->cli->success("Post type created: {$this->cli->getRelativePath($file->path)}");
        $this->cli->line('');
        $this->cli->log("Don't forget to create your Twig template at:");
        $this->cli->log("  templates/single-{$name}.twig");
        $this->cli->log("  templates/archive-{$name}.twig (if archive is enabled)");
    }
}
