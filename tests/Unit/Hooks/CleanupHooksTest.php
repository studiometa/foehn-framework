<?php

declare(strict_types=1);

use Studiometa\WPTempest\Hooks\CleanupHooks;

beforeEach(fn() => wp_stub_reset());

describe('CleanupHooks', function () {
    it('removes unnecessary wp_head actions on cleanup', function () {
        $hooks = new CleanupHooks();
        $hooks->cleanup();

        $calls = wp_stub_get_calls('remove_action');

        // Collect all hook+callback pairs
        $removed = array_map(
            fn(array $call) => $call['args']['hook'] . ':' . $call['args']['callback'],
            $calls,
        );

        expect($removed)->toContain('wp_head:wp_generator');
        expect($removed)->toContain('wp_head:print_emoji_detection_script');
        expect($removed)->toContain('wp_print_styles:print_emoji_styles');
        expect($removed)->toContain('admin_print_scripts:print_emoji_detection_script');
        expect($removed)->toContain('admin_print_styles:print_emoji_styles');
        expect($removed)->toContain('wp_head:rest_output_link_wp_head');
        expect($removed)->toContain('wp_head:wp_oembed_add_discovery_links');
        expect($removed)->toContain('wp_head:feed_links');
        expect($removed)->toContain('wp_head:feed_links_extra');
        expect($removed)->toContain('wp_head:wlwmanifest_link');
        expect($removed)->toContain('wp_head:rsd_link');
        expect($removed)->toContain('wp_head:wp_shortlink_wp_head');
    });

    it('respects priority when removing actions', function () {
        $hooks = new CleanupHooks();
        $hooks->cleanup();

        $calls = wp_stub_get_calls('remove_action');

        // Find feed_links removal — should have priority 2
        $feedLinksCall = array_values(array_filter(
            $calls,
            fn(array $call) => $call['args']['callback'] === 'feed_links',
        ));

        expect($feedLinksCall)->toHaveCount(1);
        expect($feedLinksCall[0]['args']['priority'])->toBe(2);

        // Find feed_links_extra removal — should have priority 3
        $feedLinksExtraCall = array_values(array_filter(
            $calls,
            fn(array $call) => $call['args']['callback'] === 'feed_links_extra',
        ));

        expect($feedLinksExtraCall)->toHaveCount(1);
        expect($feedLinksExtraCall[0]['args']['priority'])->toBe(3);

        // Find print_emoji_detection_script in wp_head — should have priority 7
        $emojiCall = array_values(array_filter(
            $calls,
            fn(array $call) => $call['args']['hook'] === 'wp_head'
                && $call['args']['callback'] === 'print_emoji_detection_script',
        ));

        expect($emojiCall)->toHaveCount(1);
        expect($emojiCall[0]['args']['priority'])->toBe(7);
    });

    it('cleans empty paragraphs from content', function () {
        $hooks = new CleanupHooks();

        expect($hooks->cleanEmptyParagraphs('<p>&nbsp;</p>'))->toBe('');
        expect($hooks->cleanEmptyParagraphs('<p> </p>'))->toBe('');
        expect($hooks->cleanEmptyParagraphs('<p>  &nbsp;  </p>'))->toBe('');
        expect($hooks->cleanEmptyParagraphs('<p>Hello</p>'))->toBe('<p>Hello</p>');
        expect($hooks->cleanEmptyParagraphs('<p>Hello</p><p>&nbsp;</p><p>World</p>'))
            ->toBe('<p>Hello</p><p>World</p>');
    });

    it('removes archive title prefix', function () {
        $hooks = new CleanupHooks();

        expect($hooks->cleanArchiveTitlePrefix('Category: News'))->toBe('News');
        expect($hooks->cleanArchiveTitlePrefix('Tag: PHP'))->toBe('PHP');
        expect($hooks->cleanArchiveTitlePrefix('Archives: 2024'))->toBe('2024');
        expect($hooks->cleanArchiveTitlePrefix('Simple Title'))->toBe('Simple Title');
    });
});
