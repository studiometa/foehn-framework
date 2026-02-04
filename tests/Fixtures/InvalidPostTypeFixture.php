<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsPostType;

/**
 * Invalid: has #[AsPostType] but does NOT extend Timber\Post.
 */
#[AsPostType(name: 'invalid')]
final class InvalidPostTypeFixture {}
