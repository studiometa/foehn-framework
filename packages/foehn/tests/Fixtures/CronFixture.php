<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsCron;

#[AsCron('daily')]
final class CronFixture
{
    public function __invoke(): void
    {
        // Cleanup logs
    }
}
