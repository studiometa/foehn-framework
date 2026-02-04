<?php

declare(strict_types=1);

use Studiometa\WPTempest\Hooks\Cleanup\DisableEmoji;

beforeEach(fn() => wp_stub_reset());

describe('DisableEmoji', function () {
    it('removes emoji scripts and styles from front-end and admin', function () {
        $hooks = new DisableEmoji();
        $hooks->removeEmojiHooks();

        $removeActionCalls = wp_stub_get_calls('remove_action');
        $removed = array_map(
            fn(array $call) => $call['args']['hook'] . ':' . $call['args']['callback'],
            $removeActionCalls,
        );

        expect($removed)->toContain('wp_head:print_emoji_detection_script');
        expect($removed)->toContain('wp_print_styles:print_emoji_styles');
        expect($removed)->toContain('admin_print_scripts:print_emoji_detection_script');
        expect($removed)->toContain('admin_print_styles:print_emoji_styles');
    });

    it('removes emoji static filters', function () {
        $hooks = new DisableEmoji();
        $hooks->removeEmojiHooks();

        $removeFilterCalls = wp_stub_get_calls('remove_filter');
        $removed = array_map(
            fn(array $call) => $call['args']['hook'] . ':' . $call['args']['callback'],
            $removeFilterCalls,
        );

        expect($removed)->toContain('the_content_feed:wp_staticize_emoji');
        expect($removed)->toContain('comment_text_rss:wp_staticize_emoji');
        expect($removed)->toContain('wp_mail:wp_staticize_emoji_for_email');
    });

    it('respects priority 7 for emoji detection script removal', function () {
        $hooks = new DisableEmoji();
        $hooks->removeEmojiHooks();

        $calls = wp_stub_get_calls('remove_action');
        $emojiCall = array_values(array_filter(
            $calls,
            fn(array $call) => $call['args']['hook'] === 'wp_head'
                && $call['args']['callback'] === 'print_emoji_detection_script',
        ));

        expect($emojiCall)->toHaveCount(1);
        expect($emojiCall[0]['args']['priority'])->toBe(7);
    });

    it('removes emoji CDN from DNS prefetch hints', function () {
        $hooks = new DisableEmoji();

        $urls = [
            'https://fonts.googleapis.com',
            'https://s.w.org/images/core/emoji/14.0.0/72x72/',
            'https://example.com',
        ];

        $result = $hooks->removeEmojiDnsPrefetch($urls, 'dns-prefetch');

        expect($result)->toBe(['https://fonts.googleapis.com', 'https://example.com']);
    });

    it('does not filter URLs for non-dns-prefetch relation types', function () {
        $hooks = new DisableEmoji();

        $urls = ['https://s.w.org/images/core/emoji/14.0.0/72x72/'];

        expect($hooks->removeEmojiDnsPrefetch($urls, 'preconnect'))->toBe($urls);
    });

    it('removes TinyMCE emoji plugin', function () {
        $hooks = new DisableEmoji();

        $plugins = ['wpemoji', 'wordpress', 'wplink'];

        expect($hooks->removeTinyMceEmoji($plugins))->toBe(['wordpress', 'wplink']);
    });

    it('does not modify TinyMCE plugins when wpemoji is absent', function () {
        $hooks = new DisableEmoji();

        $plugins = ['wordpress', 'wplink'];

        expect($hooks->removeTinyMceEmoji($plugins))->toBe($plugins);
    });
});
