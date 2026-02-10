<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsBlockPattern;
use Studiometa\Foehn\Contracts\BlockPatternInterface;

#[AsBlockPattern(
    name: 'test/hero-pattern',
    title: 'Hero Pattern',
    categories: ['featured'],
    keywords: ['hero'],
    description: 'A hero pattern.',
)]
final class BlockPatternFixture implements BlockPatternInterface
{
    public function compose(): array
    {
        return ['heading' => 'Hello'];
    }
}
