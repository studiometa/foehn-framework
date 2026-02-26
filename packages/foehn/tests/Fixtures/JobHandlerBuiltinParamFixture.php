<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsJob;

/**
 * Invalid: has #[AsJob] but __invoke takes a builtin type (string).
 */
#[AsJob]
final class JobHandlerBuiltinParamFixture
{
    public function __invoke(string $message): void
    {
        // Builtin type, not a DTO
    }
}
