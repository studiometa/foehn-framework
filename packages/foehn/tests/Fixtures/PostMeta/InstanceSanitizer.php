<?php

declare(strict_types=1);

namespace Tests\Fixtures\PostMeta;

use Studiometa\Foehn\Attributes\AsPostMeta;
use Timber\Post;

#[AsPostMeta(key: 'sku', sanitize: 'sanitizeSku')]
final class InstanceSanitizer extends Post
{
    public function sanitizeSku(mixed $value): string
    {
        return (string) $value;
    }
}
