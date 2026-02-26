<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsJob;

/**
 * Invalid: has #[AsJob] but no __invoke method.
 */
#[AsJob]
final class JobHandlerNoInvokeFixture
{
    public function handle(JobDtoFixture $job): void
    {
        // Wrong method name
    }
}
