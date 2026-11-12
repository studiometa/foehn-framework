<?php

declare(strict_types=1);

namespace Tests\Fixtures\ProblemApp;

use Studiometa\Foehn\Attributes\AsPostType;
use Timber\Post;

/**
 * A valid post type, scanned next to a broken one: it must still register.
 */
#[AsPostType(name: 'article')]
final class Article extends Post {}
