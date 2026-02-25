<?php

declare(strict_types=1);

namespace Tests\Fixtures;

/**
 * A job DTO for testing.
 */
final readonly class JobDtoFixture
{
    public function __construct(
        public int $importId,
        public string $source,
    ) {}
}
