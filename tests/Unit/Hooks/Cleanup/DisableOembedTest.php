<?php

declare(strict_types=1);

use Studiometa\WPTempest\Hooks\Cleanup\DisableOembed;

beforeEach(fn() => wp_stub_reset());

describe('DisableOembed', function () {
    it('removes oEmbed discovery links', function () {
        $hooks = new DisableOembed();
        $hooks->disableOembedDiscovery();

        $calls = wp_stub_get_calls('remove_action');

        expect($calls)->toHaveCount(1);
        expect($calls[0]['args']['hook'])->toBe('wp_head');
        expect($calls[0]['args']['callback'])->toBe('wp_oembed_add_discovery_links');
    });

    it('has AsAction attribute on init hook', function () {
        $method = new ReflectionMethod(DisableOembed::class, 'disableOembedDiscovery');
        $attributes = $method->getAttributes(\Studiometa\WPTempest\Attributes\AsAction::class);

        expect($attributes)->toHaveCount(1);
        expect($attributes[0]->newInstance()->hook)->toBe('init');
    });
});
