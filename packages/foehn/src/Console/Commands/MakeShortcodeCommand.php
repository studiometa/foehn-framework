<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\GenerationRequest;
use Studiometa\Foehn\Console\Scaffolder;
use Studiometa\Foehn\Console\Stubs\ShortcodeStub;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\Discovery\ShortcodeDiscovery;

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
    : Write nothing. Show the file that would be written, or report that it exists

    [--format=<format>]
    : Output format. text (default) or json. json prints the written files, their status and the next steps

    ## EXAMPLES

        # Create a simple shortcode
        wp foehn make:shortcode button

        # Create with custom class name
        wp foehn make:shortcode my-gallery --class=GalleryShortcode

        # Preview what would be created
        wp foehn make:shortcode button --dry-run
    DOC)]
final class MakeShortcodeCommand implements CliCommandInterface
{
    public function __construct(
        private readonly WpCli $cli,
        private readonly Scaffolder $scaffolder,
    ) {}

    public function __invoke(array $args, array $assocArgs): void
    {
        $tag = $args[0] ?? null;

        if ($tag === null) {
            $this->cli->error('Please provide a shortcode tag.');

            return;
        }

        $className = $assocArgs['class'] ?? str($tag)->pascal()->toString() . 'Shortcode';

        $this->scaffolder->scaffold(
            new GenerationRequest(
                stub: ShortcodeStub::class,
                discovery: ShortcodeDiscovery::class,
                subdirectory: 'Shortcodes',
                className: $className,
                // The tag lives on a method attribute and in the usage docblock,
                // so it is substituted by name rather than rewritten structurally.
                replacements: ['dummy-shortcode' => $tag],
            ),
            $assocArgs,
            'Shortcode created',
            ["Usage: [{$tag}] or [{$tag} attr=\"value\"]Content[/{$tag}]"],
        );
    }
}
