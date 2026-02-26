<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsJob;

#[AsJob]
final class JobHandlerFixture
{
    public function __invoke(JobDtoFixture $job): void
    {
        // Process import
    }
}
