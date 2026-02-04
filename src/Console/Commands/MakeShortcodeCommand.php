<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Console\Commands;

use Studiometa\WPTempest\Attributes\AsCliCommand;
use Studiometa\WPTempest\Console\CliCommandInterface;
use Studiometa\WPTempest\Console\GeneratesFiles;
use Studiometa\WPTempest\Console\Stubs\ShortcodeStub;
use Studiometa\WPTempest\Console\WpCli;

use function Tempest\Support\str;

#[AsCliCommand(name: 'make:shortcode', description: 'Create a new shortcode handler class', longDescription: <<<'DOC'
    ## OPTIONS

    <tag>
    : The shortcode tag (e.g., 'button', 'gallery')

    [--class=<class>]
    : Custom class name (defaults to PascalCase of tag + Shortcode)

    [--force]
    : Overwrite existing file

    ## EXAMPLES

        # Create a simple shortcode
        wp tempest make:shortcode button

        # Create with custom class name
        wp tempest make:shortcode my-gallery --class=GalleryShortcode
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

        $className = $assocArgs['class'] ?? str($tag)->studly()->toString() . 'Shortcode';
        $force = isset($assocArgs['force']);

        $targetPath = $this->getTargetPath('Shortcodes', $className);

        if (!$this->shouldGenerate($targetPath, $force)) {
            return;
        }

        $this->generateClassFile(stubClass: ShortcodeStub::class, targetPath: $targetPath, replacements: [
            'dummy-shortcode' => $tag,
        ]);

        $this->cli->success("Shortcode created: {$this->cli->getRelativePath($targetPath)}");
        $this->cli->line('');
        $this->cli->log("Usage: [{$tag}] or [{$tag} attr=\"value\"]Content[/{$tag}]");
    }
}
