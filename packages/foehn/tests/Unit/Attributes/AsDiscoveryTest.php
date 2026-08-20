<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsDiscovery;
use Studiometa\Foehn\Discovery\CliCommandDiscovery;
use Studiometa\Foehn\Discovery\DiscoveryPhase;
use Studiometa\Foehn\Discovery\PostTypeDiscovery;
use Studiometa\Foehn\Discovery\RestRouteDiscovery;

describe('AsDiscovery', function () {
    it('defaults to the main phase', function () {
        expect(new AsDiscovery()->phase)->toBe(DiscoveryPhase::Main);
    });

    it('can be instantiated with a phase', function () {
        expect(new AsDiscovery(phase: DiscoveryPhase::Late)->phase)->toBe(DiscoveryPhase::Late);
    });

    it('is readonly', function () {
        expect(AsDiscovery::class)->toBeReadonlyClass();
    });

    it('is a class attribute', function () {
        $attributes = new ReflectionClass(AsDiscovery::class)->getAttributes(Attribute::class);

        expect($attributes)->toHaveCount(1);
        expect($attributes[0]->newInstance()->flags & Attribute::TARGET_CLASS)->toBeTruthy();
    });
});

describe('framework discovery phases', function () {
    it('declares the phase each framework discovery registers in', function ($class, $phase) {
        $attributes = new ReflectionClass($class)->getAttributes(AsDiscovery::class);

        expect($attributes)->toHaveCount(1);
        expect($attributes[0]->newInstance()->phase)->toBe($phase);
    })->with([
        // One per phase: WordPress rejects a CLI command registered at wp_loaded and
        // a post type registered before init, so these are not cosmetic.
        [CliCommandDiscovery::class, DiscoveryPhase::Early],
        [PostTypeDiscovery::class,   DiscoveryPhase::Main],
        [RestRouteDiscovery::class,  DiscoveryPhase::Late],
    ]);
});
