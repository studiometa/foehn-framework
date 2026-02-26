<?php

declare(strict_types=1);

namespace Tests\Fixtures;

/**
 * A job DTO with default parameter values.
 */
final readonly class JobDtoWithDefaultsFixture
{
    public function __construct(
        public int $id,
        public string $format = 'json',
        public bool $dryRun = false,
    ) {}
}
