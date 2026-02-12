<?php

declare(strict_types=1);

use App\Hooks\ThemeHooks;

// add_theme_support is defined in ImageSizeDiscoveryApplyTest using
// the $testThemeSupports global. We reuse the same global here.
if (!function_exists('add_theme_support')) {
    function add_theme_support(string $feature): void
    {
        global $testThemeSupports;
        $testThemeSupports[] = $feature;
    }
}

beforeEach(function () {
    global $testThemeSupports;
    $testThemeSupports = [];
});

describe('Starter ThemeHooks', function () {
    it('sets excerpt length to 30', function () {
        expect(new ThemeHooks()->excerptLength())->toBe(30);
    });

    it('sets excerpt more to ellipsis', function () {
        expect(new ThemeHooks()->excerptMore())->toBe('…');
    });

    it('registers theme supports on setupTheme', function () {
        global $testThemeSupports;

        new ThemeHooks()->setupTheme();

        expect($testThemeSupports)->toContain('post-thumbnails');
        expect($testThemeSupports)->toContain('title-tag');
        expect($testThemeSupports)->toContain('html5');
        expect($testThemeSupports)->toContain('responsive-embeds');
        expect($testThemeSupports)->toContain('wp-block-styles');
        expect($testThemeSupports)->toContain('editor-styles');
    });
});
