<?php

declare(strict_types=1);

namespace Tests\Fixtures\ProblemApp;

use Studiometa\Foehn\Attributes\AsPostType;
use Timber\Post;

/**
 * A valid post type, scanned next to a broken one: it must still register. It
 * names core's `category`, which is known, and `genre`, which nothing registers.
 */
#[AsPostType(name: 'article', taxonomies: ['category', 'genre'])]
final class Article extends Post {}
