<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsBlockPattern;

#[AsBlockPattern(
    name: 'test/hero-only',
    title: 'Hero Only',
    keywords: ['hero', 'header'],
    blockTypes: ['core/post-content'],
)]
final class ConstrainedBlockPatternFixture {}
