<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsCron;
use Studiometa\Foehn\Jobs\CronInterval;

/**
 * Invalid: has #[AsCron] but no __invoke() method.
 */
#[AsCron(CronInterval::Daily)]
final class InvalidCronFixture
{
    public function run(): void
    {
        // Missing __invoke
    }
}
