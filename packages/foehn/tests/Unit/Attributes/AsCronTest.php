<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsCron;

describe('AsCron', function () {
    it('can be instantiated with a named interval', function () {
        $cron = new AsCron('daily');

        expect($cron->interval)->toBe('daily');
        expect($cron->intervalSeconds)->toBe(86400);
        expect($cron->group)->toBe('foehn');
        expect($cron->hook)->toBeNull();
    });

    it('supports hourly interval', function () {
        $cron = new AsCron('hourly');

        expect($cron->intervalSeconds)->toBe(3600);
    });

    it('supports twicedaily interval', function () {
        $cron = new AsCron('twicedaily');

        expect($cron->intervalSeconds)->toBe(43200);
    });

    it('supports weekly interval', function () {
        $cron = new AsCron('weekly');

        expect($cron->intervalSeconds)->toBe(604800);
    });

    it('supports custom integer interval in seconds', function () {
        $cron = new AsCron(300);

        expect($cron->interval)->toBe(300);
        expect($cron->intervalSeconds)->toBe(300);
    });

    it('accepts a custom group', function () {
        $cron = new AsCron('daily', group: 'my-plugin');

        expect($cron->group)->toBe('my-plugin');
    });

    it('accepts a custom hook name', function () {
        $cron = new AsCron('daily', hook: 'my_plugin/cleanup');

        expect($cron->hook)->toBe('my_plugin/cleanup');
    });

    it('throws on invalid interval string', function () {
        new AsCron('monthly');
    })->throws(InvalidArgumentException::class, "Invalid cron interval 'monthly'");

    it('is readonly', function () {
        expect(AsCron::class)->toBeReadonly();
    });

    it('can be used as an attribute', function () {
        $reflection = new ReflectionClass(AsCron::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        expect($attributes)->toHaveCount(1);
    });

    it('targets classes only', function () {
        $reflection = new ReflectionClass(AsCron::class);
        $attribute = $reflection->getAttributes(Attribute::class)[0]->newInstance();

        expect($attribute->flags & Attribute::TARGET_CLASS)->toBeTruthy();
    });
});
