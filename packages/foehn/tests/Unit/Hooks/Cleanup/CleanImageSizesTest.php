<?php

declare(strict_types=1);

use Studiometa\Foehn\Hooks\Cleanup\CleanImageSizes;

describe('CleanImageSizes', function () {
    it('keeps thumbnail as the only core size', function () {
        $hooks = new CleanImageSizes();

        $sizes = ['thumbnail', 'medium', 'medium_large', 'large', '1536x1536', '2048x2048'];

        expect($hooks->removeUnnecessarySizes($sizes))->toBe(['thumbnail']);
    });

    it('keeps the sizes a theme adds', function () {
        $hooks = new CleanImageSizes();

        $sizes = ['thumbnail', 'medium', 'card', 'large', 'hero'];

        expect($hooks->removeUnnecessarySizes($sizes))->toBe(['thumbnail', 'card', 'hero']);
    });

    it('handles empty sizes array', function () {
        $hooks = new CleanImageSizes();

        expect($hooks->removeUnnecessarySizes([]))->toBe([]);
    });

    it('has correct filter attribute', function () {
        $method = new ReflectionMethod(CleanImageSizes::class, 'removeUnnecessarySizes');
        $attributes = $method->getAttributes(\Studiometa\Foehn\Attributes\AsFilter::class);

        expect($attributes)->toHaveCount(1);
        expect($attributes[0]->newInstance()->hook)->toBe('intermediate_image_sizes');
    });
});
