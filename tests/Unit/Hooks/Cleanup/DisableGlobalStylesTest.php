<?php

declare(strict_types=1);

use Studiometa\Foehn\Hooks\Cleanup\DisableGlobalStyles;

beforeEach(fn() => wp_stub_reset());

describe('DisableGlobalStyles', function () {
    it('removes global styles actions', function () {
        $hooks = new DisableGlobalStyles();
        $hooks->disableGlobalStyles();

        $calls = wp_stub_get_calls('remove_action');
        $removed = array_map(fn(array $call) => $call['args']['hook'] . ':' . $call['args']['callback'], $calls);

        expect($removed)->toContain('wp_enqueue_scripts:wp_enqueue_global_styles');
        expect($removed)->toContain('wp_footer:wp_enqueue_global_styles');
        expect($removed)->toContain('wp_body_open:wp_global_styles_render_svg_filters');
        expect($removed)->toContain('wp_enqueue_scripts:wp_enqueue_global_styles_custom_css');
    });

    it('removes wp_footer global styles with priority 1', function () {
        $hooks = new DisableGlobalStyles();
        $hooks->disableGlobalStyles();

        $calls = wp_stub_get_calls('remove_action');
        $footerCall = array_values(array_filter(
            $calls,
            fn(array $call) => (
                $call['args']['hook'] === 'wp_footer'
                && $call['args']['callback'] === 'wp_enqueue_global_styles'
            ),
        ));

        expect($footerCall)->toHaveCount(1);
        expect($footerCall[0]['args']['priority'])->toBe(1);
    });

    it('has AsAction attribute on init hook', function () {
        $method = new ReflectionMethod(DisableGlobalStyles::class, 'disableGlobalStyles');
        $attributes = $method->getAttributes(\Studiometa\Foehn\Attributes\AsAction::class);

        expect($attributes)->toHaveCount(1);
        expect($attributes[0]->newInstance()->hook)->toBe('init');
    });
});
