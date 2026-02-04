<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Console\Commands;

use Studiometa\WPTempest\Attributes\AsCliCommand;
use Studiometa\WPTempest\Console\CliCommandInterface;
use Studiometa\WPTempest\Console\GeneratesFiles;
use Studiometa\WPTempest\Console\Stubs\PostTypeStub;
use Studiometa\WPTempest\Console\WpCli;

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

    ## EXAMPLES

        # Create a simple post type
        wp tempest make:post-type project

        # Create with custom labels
        wp tempest make:post-type team-member --singular="Team Member" --plural="Team Members"

        # Create with custom class name
        wp tempest make:post-type event --class=CalendarEvent
    DOC)]
final class MakePostTypeCommand implements CliCommandInterface
{
    use GeneratesFiles;

    public function __construct(
        private readonly WpCli $cli,
    ) {}

    public function __invoke(array $args, array $assocArgs): void
    {
        $name = $args[0] ?? null;

        if ($name === null) {
            $this->cli->error('Please provide a post type name.');

            return;
        }

        $className = $assocArgs['class'] ?? str($name)->studly()->toString() . 'Post';
        $singular = $assocArgs['singular'] ?? str($name)->headline()->toString();
        $plural = $assocArgs['plural'] ?? $singular . 's';
        $force = isset($assocArgs['force']);

        $targetPath = $this->getTargetPath('PostTypes', $className);

        if (!$this->shouldGenerate($targetPath, $force)) {
            return;
        }

        $this->generateClassFile(stubClass: PostTypeStub::class, targetPath: $targetPath, replacements: [
            'dummy-post-type' => $name,
            'Dummy Singular' => $singular,
            'Dummy Plural' => $plural,
        ]);

        $this->cli->success("Post type created: {$this->cli->getRelativePath($targetPath)}");
        $this->cli->line('');
        $this->cli->log("Don't forget to create your Twig template at:");
        $this->cli->log("  templates/single-{$name}.twig");
        $this->cli->log("  templates/archive-{$name}.twig (if archive is enabled)");
    }
}
