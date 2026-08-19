<?php

declare(strict_types=1);

namespace Tests\Fixtures\PostMeta;

use Studiometa\Foehn\Attributes\AsPostMeta;
use Timber\Post;

#[AsPostMeta(key: 'sku', sanitize: 'sanitizeNothing')]
final class MissingSanitizer extends Post {}
