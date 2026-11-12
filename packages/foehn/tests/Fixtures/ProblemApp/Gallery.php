<?php

declare(strict_types=1);

namespace Tests\Fixtures\ProblemApp;

use Studiometa\Foehn\Attributes\AsPostType;

/**
 * Broken: #[AsPostType] on a class that does not extend Timber\Post.
 */
#[AsPostType(name: 'gallery')]
final class Gallery {}
