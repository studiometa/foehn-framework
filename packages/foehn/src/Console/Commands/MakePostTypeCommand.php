<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\GenerationRequest;
use Studiometa\Foehn\Console\Scaffolder;
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
    : Write nothing. Show the file that would be written, or report that it exists

    [--format=<format>]
    : Output format. text (default) or json. json prints the written files, their status and the next steps

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
        private readonly Scaffolder $scaffolder,
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

        $this->scaffolder->scaffold(
            new GenerationRequest(
                stub: PostTypeStub::class,
                subdirectory: 'PostTypes',
                className: $className,
                attributeArguments: [
                    'name' => $name,
                    'singular' => $singular,
                    'plural' => $plural,
                ],
            ),
            $assocArgs,
            'Post type created',
            [
                "Create the Twig templates: templates/single-{$name}.twig, templates/archive-{$name}.twig (if the archive is enabled)",
            ],
        );
    }
}
