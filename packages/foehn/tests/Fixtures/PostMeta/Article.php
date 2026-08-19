<?php

declare(strict_types=1);

namespace Tests\Fixtures\PostMeta;

use Studiometa\Foehn\Attributes\AsPostMeta;
use Studiometa\Foehn\Attributes\AsTimberModel;
use Timber\Post;

/**
 * A model for a post type registered elsewhere — core's `post`, here.
 */
#[AsTimberModel('post')]
#[AsPostMeta(key: 'reading_time', type: 'integer')]
final class Article extends Post {}
