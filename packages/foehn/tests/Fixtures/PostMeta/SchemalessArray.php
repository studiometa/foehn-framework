<?php

declare(strict_types=1);

namespace Tests\Fixtures\PostMeta;

use Studiometa\Foehn\Attributes\AsPostMeta;
use Timber\Post;

#[AsPostMeta(key: 'credits', type: 'array')]
final class SchemalessArray extends Post {}
