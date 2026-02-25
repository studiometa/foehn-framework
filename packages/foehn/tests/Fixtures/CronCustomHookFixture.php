<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsCron;

#[AsCron('hourly', group: 'my-plugin', hook: 'my_plugin/sync_data')]
final class CronCustomHookFixture
{
    public function __invoke(): void
    {
        // Sync data
    }
}
