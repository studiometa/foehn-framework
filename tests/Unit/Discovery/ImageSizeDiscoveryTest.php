<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\ImageSizeDiscovery;
use Tests\Fixtures\ImageSizeFixture;
use Tests\Fixtures\ImageSizeWithNameFixture;
use Tests\Fixtures\NoAttributeFixture;

beforeEach(function () {
    $this->discovery = new ImageSizeDiscovery();
});

describe('ImageSizeDiscovery', function () {
    it('discovers image size attributes on classes', function () {
        $this->discovery->discover(new ReflectionClass(ImageSizeFixture::class));

        $items = $this->discovery->getItems();

        expect($items)->toHaveCount(1);
        expect($items[0]['className'])->toBe(ImageSizeFixture::class);
        expect($items[0]['width'])->toBe(1200);
        expect($items[0]['height'])->toBe(630);
        expect($items[0]['crop'])->toBeTrue();
    });

    it('derives name from class name when not specified', function () {
        $this->discovery->discover(new ReflectionClass(ImageSizeFixture::class));

        $items = $this->discovery->getItems();

        // ImageSizeFixture -> image_size_fixture (removes "Fixture" suffix? No, just converts)
        // Actually: ImageSizeFixture -> image_size_fixture
        expect($items[0]['name'])->toBe('image_size_fixture');
    });

    it('uses explicit name when provided', function () {
        $this->discovery->discover(new ReflectionClass(ImageSizeWithNameFixture::class));

        $items = $this->discovery->getItems();

        expect($items[0]['name'])->toBe('hero_banner');
    });

    it('ignores classes without image size attribute', function () {
        $this->discovery->discover(new ReflectionClass(NoAttributeFixture::class));

        expect($this->discovery->getItems())->toBeEmpty();
    });

    it('reports hasItems correctly', function () {
        expect($this->discovery->hasItems())->toBeFalse();

        $this->discovery->discover(new ReflectionClass(ImageSizeFixture::class));

        expect($this->discovery->hasItems())->toBeTrue();
    });
});

describe('ImageSizeDiscovery name derivation', function () {
    it('converts PascalCase to snake_case', function () {
        $discovery = new ImageSizeDiscovery();
        $method = new ReflectionMethod($discovery, 'deriveNameFromClass');

        expect($method->invoke($discovery, 'HeroImage'))->toBe('hero');
        expect($method->invoke($discovery, 'ThumbnailLarge'))->toBe('thumbnail_large');
        expect($method->invoke($discovery, 'SocialShareImage'))->toBe('social_share');
        expect($method->invoke($discovery, 'CardSize'))->toBe('card');
        expect($method->invoke($discovery, 'MyCustomImageSize'))->toBe('my_custom');
    });
});
