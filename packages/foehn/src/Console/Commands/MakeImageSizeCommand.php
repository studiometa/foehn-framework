<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\ClassFileGenerator;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\GenerationRequest;
use Studiometa\Foehn\Console\Stubs\ImageSizeStub;
use Studiometa\Foehn\Console\WpCli;

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
    : Show what would be created without creating

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
        private readonly ClassFileGenerator $generator,
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
        $force = ($assocArgs['force'] ?? null) !== null;
        $dryRun = ($assocArgs['dry-run'] ?? null) !== null;

        $file = $this->generator->generate(new GenerationRequest(
            stub: ImageSizeStub::class,
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
        ));

        if ($dryRun) {
            $this->cli->previewGeneratedFile($file);

            return;
        }

        if (!$this->generator->write($file, $force)) {
            $this->cli->reportFileExists($file);

            return;
        }

        $this->cli->success("Image size created: {$this->cli->getRelativePath($file->path)}");
        $this->cli->line('');
        $this->cli->log('Image size registered:');
        $this->cli->log("  Name: {$sizeName}");
        $this->cli->log("  Dimensions: {$width}x{$height}");
        $this->cli->log('  Crop: ' . ($crop ? 'Yes' : 'No'));
        $this->cli->line('');
        $this->cli->log('Use in templates:');
        $this->cli->log("  {{ post.thumbnail.src('{$sizeName}') }}");
        $this->cli->line('');
        $this->cli->log('Or with the helper:');
        $this->cli->log("  {{ {$className}::url(image_id) }}");
        $this->cli->line('');
        $this->cli->log($this->cli->colorize(
            '%YNote:%n Run "wp media regenerate" to generate this size for existing images.',
        ));
    }
}
