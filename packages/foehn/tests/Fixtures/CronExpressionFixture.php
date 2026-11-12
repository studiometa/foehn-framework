<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsCron;

#[AsCron('0 0 * * *')]
final class CronExpressionFixture
{
    public function __invoke(): void
    {
        // Purge pages that show today's date
    }
}
