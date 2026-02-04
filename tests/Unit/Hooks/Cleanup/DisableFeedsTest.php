<?php

declare(strict_types=1);

use Studiometa\Foehn\Hooks\Cleanup\DisableFeeds;

beforeEach(fn() => wp_stub_reset());

describe('DisableFeeds', function () {
    it('removes feed links with correct priorities', function () {
        $hooks = new DisableFeeds();
        $hooks->removeFeedLinks();

        $calls = wp_stub_get_calls('remove_action');

        expect($calls)->toHaveCount(2);

        // feed_links with priority 2
        expect($calls[0]['args']['hook'])->toBe('wp_head');
        expect($calls[0]['args']['callback'])->toBe('feed_links');
        expect($calls[0]['args']['priority'])->toBe(2);

        // feed_links_extra with priority 3
        expect($calls[1]['args']['hook'])->toBe('wp_head');
        expect($calls[1]['args']['callback'])->toBe('feed_links_extra');
        expect($calls[1]['args']['priority'])->toBe(3);
    });

    it('has AsAction attribute on init hook', function () {
        $method = new ReflectionMethod(DisableFeeds::class, 'removeFeedLinks');
        $attributes = $method->getAttributes(\Studiometa\Foehn\Attributes\AsAction::class);

        expect($attributes)->toHaveCount(1);
        expect($attributes[0]->newInstance()->hook)->toBe('init');
    });
});
