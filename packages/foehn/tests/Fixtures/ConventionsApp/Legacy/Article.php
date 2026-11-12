<?php

declare(strict_types=1);

namespace Tests\Fixtures\ConventionsApp\Legacy;

use Studiometa\Foehn\Attributes\AsPostType;
use Timber\Post;

/**
 * The one post type outside Content/, under another parent: the majority wins.
 */
#[AsPostType(name: 'article')]
final class Article extends Post {}
