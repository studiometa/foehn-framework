<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsImageSize;
use Studiometa\Foehn\Discovery\ImageSizeDiscovery;
use Tests\Fixtures\ImageSizeFixture;
use Tests\Fixtures\ImageSizeWithNameFixture;
use Tests\Fixtures\NoAttributeFixture;

beforeEach(function () {
    $this->location = testDiscoveryLocation();
    $this->discovery = new ImageSizeDiscovery();
});

describe('ImageSizeDiscovery', function () {
    it('discovers image size attributes on classes', function () {
        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(ImageSizeFixture::class));

        $items = iterator_to_array($this->discovery->getItems());

        expect($items)->toHaveCount(1);
        expect($items[0]['className'])->toBe(ImageSizeFixture::class);
        expect($items[0]['attribute']->width)->toBe(1200);
        expect($items[0]['attribute']->height)->toBe(630);
        expect($items[0]['attribute']->crop)->toBeTrue();
    });

    it('derives name from class name when not specified', function () {
        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(ImageSizeFixture::class));

        $items = iterator_to_array($this->discovery->getItems());

        // The name is derived at apply time, so the attribute itself carries none.
        // ImageSizeDiscoveryApplyTest asserts the derived 'image_size_fixture'.
        expect($items[0]['attribute']->name)->toBeNull();
    });

    it('uses explicit name when provided', function () {
        $this->discovery->discover(
            $this->location,
            new \Tempest\Reflection\ClassReflector(ImageSizeWithNameFixture::class),
        );

        $items = iterator_to_array($this->discovery->getItems());

        expect($items[0]['attribute']->name)->toBe('hero_banner');
    });

    it('ignores classes without image size attribute', function () {
        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(NoAttributeFixture::class));

        expect($this->discovery->getItems())->toHaveCount(0);
    });

    it('reports hasItems correctly', function () {
        expect($this->discovery->getItems())->toHaveCount(0);

        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(ImageSizeFixture::class));

        expect($this->discovery->getItems())->not->toHaveCount(0);
    });
});

describe('ImageSizeDiscovery name derivation', function () {
    it('converts PascalCase to snake_case', function () {
        $size = new AsImageSize(width: 100);
        $derive = static fn(string $class): string => ImageSizeDiscovery::sizeName($size, $class);

        expect($derive('App\\ImageSizes\\HeroImage'))
            ->toBe('hero')
            ->and($derive('App\\ImageSizes\\ThumbnailLarge'))
            ->toBe('thumbnail_large')
            ->and($derive('App\\ImageSizes\\SocialShareImage'))
            ->toBe('social_share')
            ->and($derive('App\\ImageSizes\\CardSize'))
            ->toBe('card')
            ->and($derive('App\\ImageSizes\\MyCustomImageSize'))
            ->toBe('my_custom');
    });

    it('keeps a name the attribute declares', function () {
        expect(ImageSizeDiscovery::sizeName(new AsImageSize(width: 100, name: 'og'), 'App\\HeroImage'))->toBe('og');
    });

    it('derives from a class in the global namespace', function () {
        $size = new AsImageSize(width: 100);
        $derive = static fn(string $class): string => ImageSizeDiscovery::sizeName($size, $class);

        expect($derive('HeroImage'))->toBe('hero');
    });
});
