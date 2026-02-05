<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsContextProvider;

/**
 * Invalid: has #[AsContextProvider] but does NOT implement ContextProviderInterface.
 */
#[AsContextProvider('single')]
final class InvalidContextProviderFixture {}
