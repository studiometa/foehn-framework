<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsJob;

/**
 * Invalid: has #[AsJob] but __invoke takes no parameters.
 */
#[AsJob]
final class InvalidJobHandlerFixture
{
    public function __invoke(): void
    {
        // Missing DTO parameter
    }
}
