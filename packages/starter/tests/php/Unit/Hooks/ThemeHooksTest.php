<?php

declare(strict_types=1);

use App\Hooks\ThemeHooks;
use Studiometa\Foehn\Attributes\AsAction;
use Studiometa\Foehn\Attributes\AsFilter;

describe('ThemeHooks', function () {
    it('has AsAction on setupTheme for after_setup_theme', function () {
        $ref = new ReflectionMethod(ThemeHooks::class, 'setupTheme');
        $attrs = $ref->getAttributes(AsAction::class);

        expect($attrs)->toHaveCount(1);
        expect($attrs[0]->newInstance()->hook)->toBe('after_setup_theme');
    });

    it('has AsFilter on excerptLength for excerpt_length', function () {
        $ref = new ReflectionMethod(ThemeHooks::class, 'excerptLength');
        $attrs = $ref->getAttributes(AsFilter::class);

        expect($attrs)->toHaveCount(1);
        expect($attrs[0]->newInstance()->hook)->toBe('excerpt_length');
    });

    it('excerptLength returns 30', function () {
        expect(new ThemeHooks()->excerptLength())->toBe(30);
    });

    it('has AsFilter on excerptMore for excerpt_more', function () {
        $ref = new ReflectionMethod(ThemeHooks::class, 'excerptMore');
        $attrs = $ref->getAttributes(AsFilter::class);

        expect($attrs)->toHaveCount(1);
        expect($attrs[0]->newInstance()->hook)->toBe('excerpt_more');
    });

    it('excerptMore returns ellipsis', function () {
        expect(new ThemeHooks()->excerptMore())->toBe('…');
    });
});
