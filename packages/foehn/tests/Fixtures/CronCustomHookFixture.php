<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsCron;
use Studiometa\Foehn\Jobs\CronInterval;

#[AsCron(CronInterval::Hourly, group: 'my-plugin', hook: 'my_plugin/sync_data')]
final class CronCustomHookFixture
{
    public function __invoke(): void
    {
        // Sync data
    }
}
