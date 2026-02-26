<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsCron;
use Studiometa\Foehn\Jobs\CronInterval;

describe('AsCron', function () {
    it('can be instantiated with a CronInterval enum', function () {
        $cron = new AsCron(CronInterval::Daily);

        expect($cron->interval)->toBe(CronInterval::Daily);
        expect($cron->intervalSeconds)->toBe(86_400);
        expect($cron->group)->toBe('foehn');
        expect($cron->hook)->toBeNull();
    });

    it('supports Hourly interval', function () {
        $cron = new AsCron(CronInterval::Hourly);

        expect($cron->intervalSeconds)->toBe(3_600);
    });

    it('supports TwiceDaily interval', function () {
        $cron = new AsCron(CronInterval::TwiceDaily);

        expect($cron->intervalSeconds)->toBe(43_200);
    });

    it('supports Weekly interval', function () {
        $cron = new AsCron(CronInterval::Weekly);

        expect($cron->intervalSeconds)->toBe(604_800);
    });

    it('supports custom integer interval in seconds', function () {
        $cron = new AsCron(300);

        expect($cron->interval)->toBe(300);
        expect($cron->intervalSeconds)->toBe(300);
    });

    it('accepts a custom group', function () {
        $cron = new AsCron(CronInterval::Daily, group: 'my-plugin');

        expect($cron->group)->toBe('my-plugin');
    });

    it('accepts a custom hook name', function () {
        $cron = new AsCron(CronInterval::Daily, hook: 'my_plugin/cleanup');

        expect($cron->hook)->toBe('my_plugin/cleanup');
    });

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
