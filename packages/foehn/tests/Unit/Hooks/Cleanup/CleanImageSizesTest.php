<?php

declare(strict_types=1);

use Studiometa\Foehn\Hooks\Cleanup\CleanImageSizes;

describe('CleanImageSizes', function () {
    beforeEach(function () {
        // Core adds these two with add_image_size() itself.
        $GLOBALS['_wp_additional_image_sizes'] = ['1536x1536' => [], '2048x2048' => []];
    });

    afterEach(function () {
        unset($GLOBALS['_wp_additional_image_sizes']);
    });

    it('keeps thumbnail as the only core size', function () {
        $hooks = new CleanImageSizes();

        $sizes = ['thumbnail', 'medium', 'medium_large', 'large', '1536x1536', '2048x2048'];

        expect($hooks->removeUnnecessarySizes($sizes))->toBe(['thumbnail']);
    });

    it('keeps a theme size that has the name of a core size', function () {
        $GLOBALS['_wp_additional_image_sizes']['large'] = ['width' => 1600];

        expect(new CleanImageSizes()->removeUnnecessarySizes(['thumbnail', 'medium', 'large']))->toBe([
            'thumbnail',
            'large',
        ]);
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
