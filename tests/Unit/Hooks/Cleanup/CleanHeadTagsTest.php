<?php

declare(strict_types=1);

use Studiometa\Foehn\Hooks\Cleanup\CleanHeadTags;

beforeEach(fn() => wp_stub_reset());

describe('CleanHeadTags', function () {
    it('removes wlwmanifest, rsd, shortlink and REST API link', function () {
        $hooks = new CleanHeadTags();
        $hooks->cleanup();

        $calls = wp_stub_get_calls('remove_action');
        $removed = array_map(fn(array $call) => $call['args']['hook'] . ':' . $call['args']['callback'], $calls);

        expect($removed)->toContain('wp_head:wlwmanifest_link');
        expect($removed)->toContain('wp_head:rsd_link');
        expect($removed)->toContain('wp_head:wp_shortlink_wp_head');
        expect($removed)->toContain('wp_head:rest_output_link_wp_head');
    });

    it('removes exactly 4 actions', function () {
        $hooks = new CleanHeadTags();
        $hooks->cleanup();

        expect(wp_stub_get_calls('remove_action'))->toHaveCount(4);
    });

    it('has AsAction attribute on init hook', function () {
        $method = new ReflectionMethod(CleanHeadTags::class, 'cleanup');
        $attributes = $method->getAttributes(\Studiometa\Foehn\Attributes\AsAction::class);

        expect($attributes)->toHaveCount(1);
        expect($attributes[0]->newInstance()->hook)->toBe('init');
    });
});
