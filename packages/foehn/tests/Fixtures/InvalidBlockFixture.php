<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsBlock;

/**
 * Invalid: has #[AsBlock] but does NOT implement BlockInterface.
 */
#[AsBlock(name: 'test/invalid', title: 'Invalid')]
final class InvalidBlockFixture {}
