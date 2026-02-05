<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\GeneratesFiles;
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
    use GeneratesFiles;

    public function __construct(
        private readonly WpCli $cli,
    ) {}

    public function __invoke(array $args, array $assocArgs): void
    {
        $tag = $args[0] ?? null;

        if ($tag === null) {
            $this->cli->error('Please provide a shortcode tag.');

            return;
        }

        $className = $assocArgs['class'] ?? str($tag)->pascal()->toString() . 'Shortcode';
        $force = isset($assocArgs['force']);
        $dryRun = isset($assocArgs['dry-run']);

        $targetPath = $this->getTargetPath('Shortcodes', $className);

        if (!$dryRun && !$this->shouldGenerate($targetPath, $force)) {
            return;
        }

        $content = $this->generateClassFile(
            stubClass: ShortcodeStub::class,
            targetPath: $targetPath,
            replacements: [
                'dummy-shortcode' => $tag,
            ],
            dryRun: $dryRun,
        );

        if ($dryRun) {
            $this->displayDryRun($targetPath, (string) $content);

            return;
        }

        $this->cli->success("Shortcode created: {$this->cli->getRelativePath($targetPath)}");
        $this->cli->line('');
        $this->cli->log("Usage: [{$tag}] or [{$tag} attr=\"value\"]Content[/{$tag}]");
    }
}
