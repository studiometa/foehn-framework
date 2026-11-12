<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsCron;

#[AsCron('0 0 * *')]
final class InvalidCronExpressionFixture
{
    public function __invoke(): void
    {
        // Never scheduled: the expression has four fields
    }
}
