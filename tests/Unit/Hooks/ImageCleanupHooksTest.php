<?php

declare(strict_types=1);

use Studiometa\WPTempest\Hooks\ImageCleanupHooks;

describe('ImageCleanupHooks', function () {
    it('removes medium_large, 1536x1536 and 2048x2048 sizes', function () {
        $hooks = new ImageCleanupHooks();

        $sizes = ['thumbnail', 'medium', 'medium_large', 'large', '1536x1536', '2048x2048'];
        $result = $hooks->removeUnnecessarySizes($sizes);

        expect($result)->toBe(['thumbnail', 'medium', 'large']);
    });

    it('returns all sizes when none match', function () {
        $hooks = new ImageCleanupHooks();

        $sizes = ['thumbnail', 'medium', 'large', 'custom-size'];
        $result = $hooks->removeUnnecessarySizes($sizes);

        expect($result)->toBe(['thumbnail', 'medium', 'large', 'custom-size']);
    });

    it('handles empty sizes array', function () {
        $hooks = new ImageCleanupHooks();

        expect($hooks->removeUnnecessarySizes([]))->toBe([]);
    });

    it('has correct filter attribute', function () {
        $reflection = new ReflectionClass(ImageCleanupHooks::class);
        $method = $reflection->getMethod('removeUnnecessarySizes');

        $attributes = $method->getAttributes(\Studiometa\WPTempest\Attributes\AsFilter::class);

        expect($attributes)->toHaveCount(1);
        expect($attributes[0]->newInstance()->hook)->toBe('intermediate_image_sizes');
    });

    it('is a final class', function () {
        $reflection = new ReflectionClass(ImageCleanupHooks::class);

        expect($reflection->isFinal())->toBeTrue();
    });
});
