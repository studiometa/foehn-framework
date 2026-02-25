<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsCron;

/**
 * Invalid: has #[AsCron] but no __invoke() method.
 */
#[AsCron('daily')]
final class InvalidCronFixture
{
    public function run(): void
    {
        // Missing __invoke
    }
}
