<?php

declare(strict_types=1);

use Studiometa\Foehn\Hooks\Cleanup\DisableBlockStyles;

beforeEach(fn() => wp_stub_reset());

describe('DisableBlockStyles', function () {
    it('dequeues block library styles', function () {
        $hooks = new DisableBlockStyles();
        $hooks->disableBlockStyles();

        $calls = wp_stub_get_calls('wp_dequeue_style');
        $handles = array_map(fn(array $call) => $call['args']['handle'], $calls);

        expect($handles)->toContain('wp-block-library');
        expect($handles)->toContain('wp-block-library-theme');
        expect($handles)->toContain('classic-theme-styles');
    });

    it('has AsAction attribute on wp_enqueue_scripts hook', function () {
        $method = new ReflectionMethod(DisableBlockStyles::class, 'disableBlockStyles');
        $attributes = $method->getAttributes(\Studiometa\Foehn\Attributes\AsAction::class);

        expect($attributes)->toHaveCount(1);
        expect($attributes[0]->newInstance()->hook)->toBe('wp_enqueue_scripts');
    });

    it('runs at priority 100 to ensure styles are already enqueued', function () {
        $method = new ReflectionMethod(DisableBlockStyles::class, 'disableBlockStyles');
        $attributes = $method->getAttributes(\Studiometa\Foehn\Attributes\AsAction::class);

        expect($attributes[0]->newInstance()->priority)->toBe(100);
    });
});
