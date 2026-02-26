<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsCron;
use Studiometa\Foehn\Jobs\CronInterval;

#[AsCron(CronInterval::Daily)]
final class CronFixture
{
    public function __invoke(): void
    {
        // Cleanup logs
    }
}
