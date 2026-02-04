<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\WPTempest\Attributes\AsViewComposer;

/**
 * Invalid: has #[AsViewComposer] but does NOT implement ViewComposerInterface.
 */
#[AsViewComposer('single')]
final class InvalidViewComposerFixture {}
