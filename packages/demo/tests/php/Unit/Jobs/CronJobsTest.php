<?php

declare(strict_types=1);

use Demo\Jobs\FlushPageCacheAtNewYear;
use Demo\Jobs\WarmAllPlates;
use Studiometa\Foehn\Attributes\AsCron;

describe('Cron jobs', function () {
    it('warms every project once a day, on an interval', function () {
        $cron = new ReflectionClass(WarmAllPlates::class)->getAttributes(AsCron::class)[0]->newInstance();

        expect($cron->intervalSeconds)->toBe(86_400);
        expect($cron->expression)->toBeNull();
        expect($cron->group)->toBe('demo');
    });

    it('flushes the page cache at midnight on 1 January, on a cron expression', function () {
        $cron = new ReflectionClass(FlushPageCacheAtNewYear::class)->getAttributes(AsCron::class)[0]->newInstance();

        expect($cron->expression)->toBe('0 0 1 1 *');
        expect($cron->intervalSeconds)->toBeNull();
    });
});
