<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\ImageSizeDiscovery;
use Tests\Fixtures\ImageSizeFixture;
use Studiometa\Foehn\Discovery\DiscoveryLocation;

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    $this->discovery = new ImageSizeDiscovery();
    $this->addedImageSizes = [];
    $this->themeSupports = [];

    // Mock add_image_size
    if (!function_exists('add_image_size')) {
        function add_image_size(string $name, int $width, int $height, bool $crop): void
        {
            global $testAddedImageSizes;
            $testAddedImageSizes[] = compact('name', 'width', 'height', 'crop');
        }
    }

    // Mock add_theme_support
    if (!function_exists('add_theme_support')) {
        function add_theme_support(string $feature): void
        {
            global $testThemeSupports;
            $testThemeSupports[] = $feature;
        }
    }

    global $testAddedImageSizes, $testThemeSupports;
    $testAddedImageSizes = [];
    $testThemeSupports = [];
});

describe('ImageSizeDiscovery::apply', function () {
    it('registers discovered image sizes with WordPress', function () {
        global $testAddedImageSizes;

        $this->discovery->discover($this->location, new ReflectionClass(ImageSizeFixture::class));
        $this->discovery->apply();

        expect($testAddedImageSizes)->toHaveCount(1);
        expect($testAddedImageSizes[0]['name'])->toBe('image_size_fixture');
        expect($testAddedImageSizes[0]['width'])->toBe(1200);
        expect($testAddedImageSizes[0]['height'])->toBe(630);
        expect($testAddedImageSizes[0]['crop'])->toBeTrue();
    });

    it('enables post-thumbnails theme support when image sizes are discovered', function () {
        global $testThemeSupports;

        $this->discovery->discover($this->location, new ReflectionClass(ImageSizeFixture::class));
        $this->discovery->apply();

        expect($testThemeSupports)->toContain('post-thumbnails');
    });

    it('does not enable theme support when no image sizes discovered', function () {
        global $testThemeSupports;

        $this->discovery->apply();

        expect($testThemeSupports)->toBeEmpty();
    });
});
