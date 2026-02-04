<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\WPTempest\Attributes\AsTimberModel;

/**
 * Invalid: has #[AsTimberModel] but does NOT extend Timber\Post or Timber\Term.
 */
#[AsTimberModel(name: 'invalid')]
final class InvalidTimberModelFixture {}
