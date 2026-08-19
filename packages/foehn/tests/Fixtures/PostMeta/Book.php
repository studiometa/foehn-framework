<?php

declare(strict_types=1);

namespace Tests\Fixtures\PostMeta;

use Studiometa\Foehn\Attributes\AsPostMeta;
use Studiometa\Foehn\Attributes\AsPostType;
use Timber\Post;

/**
 * A subtype written out, and one that does not exist.
 */
#[AsPostType(name: 'book', singular: 'Book', plural: 'Books')]
#[AsPostMeta(key: 'isbn', objectSubtype: 'page')]
#[AsPostMeta(key: 'nickname', objectType: 'user')]
final class Book extends Post {}
