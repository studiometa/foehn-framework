<?php

declare(strict_types=1);

use Studiometa\Foehn\Hooks\Cleanup\CleanImageSizes;

describe('CleanImageSizes', function () {
    it('removes medium_large, 1536x1536 and 2048x2048 sizes', function () {
        $hooks = new CleanImageSizes();

        $sizes = ['thumbnail', 'medium', 'medium_large', 'large', '1536x1536', '2048x2048'];

        expect($hooks->removeUnnecessarySizes($sizes))->toBe(['thumbnail', 'medium', 'large']);
    });

    it('returns all sizes when none match', function () {
        $hooks = new CleanImageSizes();

        $sizes = ['thumbnail', 'medium', 'large', 'custom-size'];

        expect($hooks->removeUnnecessarySizes($sizes))->toBe($sizes);
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
