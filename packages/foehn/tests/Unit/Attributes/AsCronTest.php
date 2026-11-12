<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsCron;
use Studiometa\Foehn\Jobs\CronInterval;

describe('AsCron', function () {
    it('can be instantiated with a CronInterval enum', function () {
        $cron = new AsCron(CronInterval::Daily);

        expect($cron->schedule)->toBe(CronInterval::Daily);
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

        expect($cron->schedule)->toBe(300);
        expect($cron->intervalSeconds)->toBe(300);
        expect($cron->expression)->toBeNull();
    });

    it('accepts a cron expression', function () {
        $cron = new AsCron('0 0 * * *');

        expect($cron->schedule)->toBe('0 0 * * *');
        expect($cron->expression)->toBe('0 0 * * *');
        expect($cron->intervalSeconds)->toBeNull();
    });

    it('joins the fields of an expression with single spaces', function () {
        // The form Action Scheduler gives back, so discovery can compare the two.
        expect(new AsCron("  30  6\t* *   1-5 ")->expression)->toBe('30 6 * * 1-5');
    });

    it('accepts lists, ranges and steps', function (string $expression) {
        expect(new AsCron($expression)->expression)->toBe($expression);
    })->with([
        '*/15 * * * *',
        '0 8-18/2 * * 1-5',
        '0,30 6,18 1,15 1-6 0',
        '5/10 * * * *',
        '59 23 31 12 7',
        '0 0 29 2 *',
        '0 0 30 2,3 *',
        '0 0 31 */2 *',
    ]);

    it('rejects an invalid cron expression', function (string $expression, string $message) {
        expect(fn() => new AsCron($expression))->toThrow(InvalidArgumentException::class, $message);
    })->with([
        'too few fields' => [
            '0 0 * *',
            'it must have 5 fields (minute, hour, day of month, month, day of week), it has 4',
        ],
        'six fields' => ['0 0 * * * 2026', 'it has 6'],
        'empty' => ['', 'it has 0'],
        'minute out of range' => ['60 0 * * *', '"60" is not a valid minute'],
        'hour out of range' => ['0 24 * * *', '"24" is not a valid hour'],
        'day of month zero' => ['0 0 0 * *', '"0" is not a valid day of month'],
        'month out of range' => ['0 0 * 13 *', '"13" is not a valid month'],
        'day of week out of range' => ['0 0 * * 8', '"8" is not a valid day of week'],
        'reversed range' => ['0 5-1 * * *', '"5-1" is not a valid hour'],
        'zero step' => ['*/0 * * * *', '"*/0" is not a valid minute'],
        'names' => ['0 0 * * MON', '"MON" is not a valid day of week'],
        'macro' => ['@daily', 'it has 1'],
        'empty list item' => ['0,,5 * * * *', '"0,,5" is not a valid minute'],
        'day that never comes' => ['0 0 31 2 *', 'no month in "2" has a day in "31"'],
        'days that never come' => ['0 0 31 4,6,9-11/2 *', 'no month in "4,6,9-11/2" has a day in "31"'],
        'step beyond the days' => ['0 0 */40 * *', 'no month in "*" has a day in "*/40"'],
        'step beyond the months' => ['0 0 * */13 *', 'no month in "*/13" has a day in "*"'],
    ]);

    it('accepts a custom group', function () {
        $cron = new AsCron(CronInterval::Daily, group: 'my-plugin');

        expect($cron->group)->toBe('my-plugin');
    });

    it('accepts a custom hook name', function () {
        $cron = new AsCron(CronInterval::Daily, hook: 'my_plugin/cleanup');

        expect($cron->hook)->toBe('my_plugin/cleanup');
    });

    it('is readonly', function () {
        expect(AsCron::class)->toBeReadonlyClass();
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
