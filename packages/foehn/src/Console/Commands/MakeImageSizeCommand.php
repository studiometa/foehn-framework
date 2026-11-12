<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\GenerationRequest;
use Studiometa\Foehn\Console\Scaffolder;
use Studiometa\Foehn\Console\Stubs\ImageSizeStub;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\Discovery\ImageSizeDiscovery;

use function Tempest\Support\str;

#[AsCliCommand(name: 'make:image-size', description: 'Create a new image size class', longDescription: <<<'DOC'
    ## OPTIONS

    <name>
    : The image size class name (e.g., 'CardImage', 'HeroImage')

    [--width=<width>]
    : Width in pixels (defaults to 800, use 0 for proportional)

    [--height=<height>]
    : Height in pixels (defaults to 0 for proportional)

    [--crop]
    : Hard crop to exact dimensions

    [--size-name=<size-name>]
    : Custom size identifier (defaults to kebab-case of name)

    [--force]
    : Overwrite existing file

    [--dry-run]
    : Write nothing. Show the file that would be written, or report that it exists

    [--format=<format>]
    : Output format. text (default) or json. json prints the written files, their status and the next steps

    ## EXAMPLES

        # Create a card image size (proportional width)
        wp foehn make:image-size CardImage --width=400

        # Create a hero image with exact dimensions
        wp foehn make:image-size HeroImage --width=1920 --height=1080 --crop

        # Create a thumbnail with custom size name
        wp foehn make:image-size ProductThumb --width=300 --height=300 --crop --size-name=product-thumb

        # Preview what would be created
        wp foehn make:image-size CardImage --width=400 --dry-run
    DOC)]
final class MakeImageSizeCommand implements CliCommandInterface
{
    public function __construct(
        private readonly WpCli $cli,
        private readonly Scaffolder $scaffolder,
    ) {}

    public function __invoke(array $args, array $assocArgs): void
    {
        $name = $args[0] ?? null;

        if ($name === null) {
            $this->cli->error('Please provide an image size class name.');

            return;
        }

        $className = str($name)->pascal()->toString();
        $sizeName = $assocArgs['size-name'] ?? str($name)->replace(['Image', 'Size'], '')->kebab()->toString();
        $width = (int) ($assocArgs['width'] ?? 800);
        $height = (int) ($assocArgs['height'] ?? 0);
        $crop = ($assocArgs['crop'] ?? null) !== null;

        $this->scaffolder->scaffold(
            new GenerationRequest(
                stub: ImageSizeStub::class,
                discovery: ImageSizeDiscovery::class,
                subdirectory: 'ImageSizes',
                className: $className,
                attributeArguments: [
                    'name' => $sizeName,
                    'width' => $width,
                    'height' => $height,
                    'crop' => $crop,
                ],
                // The NAME constant and the docblock are outside the attribute.
                replacements: [
                    "'dummy-size'" => "'{$sizeName}'",
                    'DummyImageSize' => $className,
                ],
            ),
            $assocArgs,
            'Image size created',
            [
                "Name: {$sizeName}",
                "Dimensions: {$width}x{$height}",
                'Crop: ' . ($crop ? 'yes' : 'no'),
                "Use in Twig: {{ post.thumbnail.src('{$sizeName}') }}, or call {$className}::url(\$attachmentId) in PHP.",
                'Run "wp media regenerate" to generate this size for existing images.',
            ],
        );
    }
}
