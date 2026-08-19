<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\ClassFileGenerator;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\GenerationRequest;
use Studiometa\Foehn\Console\Stubs\ShortcodeStub;
use Studiometa\Foehn\Console\WpCli;

use function Tempest\Support\str;

#[AsCliCommand(name: 'make:shortcode', description: 'Create a new shortcode handler class', longDescription: <<<'DOC'
    ## OPTIONS

    <tag>
    : The shortcode tag (e.g., 'button', 'gallery')

    [--class=<class>]
    : Custom class name (defaults to PascalCase of tag + Shortcode)

    [--force]
    : Overwrite existing file

    [--dry-run]
    : Show what would be created without creating

    ## EXAMPLES

        # Create a simple shortcode
        wp tempest make:shortcode button

        # Create with custom class name
        wp tempest make:shortcode my-gallery --class=GalleryShortcode

        # Preview what would be created
        wp tempest make:shortcode button --dry-run
    DOC)]
final class MakeShortcodeCommand implements CliCommandInterface
{
    public function __construct(
        private readonly WpCli $cli,
        private readonly ClassFileGenerator $generator,
    ) {}

    public function __invoke(array $args, array $assocArgs): void
    {
        $tag = $args[0] ?? null;

        if ($tag === null) {
            $this->cli->error('Please provide a shortcode tag.');

            return;
        }

        $className = $assocArgs['class'] ?? str($tag)->pascal()->toString() . 'Shortcode';
        $force = ($assocArgs['force'] ?? null) !== null;
        $dryRun = ($assocArgs['dry-run'] ?? null) !== null;

        $file = $this->generator->generate(new GenerationRequest(
            stub: ShortcodeStub::class,
            subdirectory: 'Shortcodes',
            className: $className,
            // The tag lives on a method attribute and in the usage docblock,
            // so it is substituted by name rather than rewritten structurally.
            replacements: ['dummy-shortcode' => $tag],
        ));

        if ($dryRun) {
            $this->cli->previewGeneratedFile($file);

            return;
        }

        if (!$this->generator->write($file, $force)) {
            $this->cli->reportFileExists($file);

            return;
        }

        $this->cli->success("Shortcode created: {$this->cli->getRelativePath($file->path)}");
        $this->cli->line('');
        $this->cli->log("Usage: [{$tag}] or [{$tag} attr=\"value\"]Content[/{$tag}]");
    }
}
