<?php

declare(strict_types=1);

use Studiometa\Foehn\Blocks\BlockAssets;

beforeEach(function () {
    wp_stub_reset();

    // A real directory, so "the file exists" is a real question and not a stub answer.
    $GLOBALS['wp_stub_template_directory'] = dirname(__DIR__, 2) . '/Fixtures/theme';
    $GLOBALS['wp_stub_template_directory_uri'] = 'https://example.test/wp-content/themes/theme';
});

describe('BlockAssets', function () {
    it('attaches the stylesheet and the view script a block has', function () {
        $args = BlockAssets::register('theme/callout');

        expect($args)->toBe([
            'style_handles' => ['theme-callout-style'],
            'view_script_module_ids' => ['theme/callout/view'],
        ]);
    });

    it('registers each asset with its own URL and file mtime as the version', function () {
        BlockAssets::register('theme/callout');

        $style = wp_stub_get_calls('wp_register_style')[0]['args'];
        $script = wp_stub_get_calls('wp_register_script_module')[0]['args'];

        expect($style['src'])
            ->toBe('https://example.test/wp-content/themes/theme/assets/css/blocks/callout.css');
        expect($script['src'])
            ->toBe('https://example.test/wp-content/themes/theme/assets/js/blocks/callout.js');

        $themeDir = $GLOBALS['wp_stub_template_directory'];

        expect($style['ver'])->toBe((string) filemtime($themeDir . '/assets/css/blocks/callout.css'));
        expect($script['version'])->toBe((string) filemtime($themeDir . '/assets/js/blocks/callout.js'));
    });

    it('attaches only what exists', function () {
        $args = BlockAssets::register('theme/styled-only');

        expect($args)->toBe(['style_handles' => ['theme-styled-only-style']]);
        expect(wp_stub_get_calls('wp_register_script_module'))->toBe([]);
    });

    it('registers nothing at all for a block with no assets', function () {
        expect(BlockAssets::register('theme/bare'))->toBe([]);
        expect(wp_stub_get_calls('wp_register_style'))->toBe([]);
        expect(wp_stub_get_calls('wp_register_script_module'))->toBe([]);
    });

    it('takes the file name from the block slug, not its namespace', function () {
        // A different namespace, same slug, so it resolves to the same files.
        $args = BlockAssets::register('acme/callout');

        expect($args)->toBe([
            'style_handles' => ['acme-callout-style'],
            'view_script_module_ids' => ['acme/callout/view'],
        ]);
    });

    it('handles a block name with no namespace', function () {
        expect(BlockAssets::register('callout'))->toBe([
            'style_handles' => ['callout-style'],
            'view_script_module_ids' => ['callout/view'],
        ]);
    });
});
