<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\WPTempest\Attributes\AsAcfBlock;

/**
 * Invalid: has #[AsAcfBlock] but does NOT implement AcfBlockInterface.
 */
#[AsAcfBlock(name: 'invalid', title: 'Invalid')]
final class InvalidAcfBlockFixture {}
