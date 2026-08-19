<?php

declare(strict_types=1);

namespace Tests\Fixtures\PostMeta;

use Studiometa\Foehn\Attributes\AsPostMeta;
use Studiometa\Foehn\Attributes\AsPostType;
use Timber\Post;

#[AsPostType(name: 'product', singular: 'Product', plural: 'Products')]
#[AsPostMeta(key: 'price', type: 'number', description: 'What it costs')]
#[AsPostMeta(key: 'sku', showInRest: false, sanitize: 'sanitizeSku')]
#[AsPostMeta(key: 'gallery', type: 'integer', single: false, default: 0)]
final class Product extends Post
{
    public static function sanitizeSku(mixed $value): string
    {
        return strtoupper((string) $value);
    }
}
