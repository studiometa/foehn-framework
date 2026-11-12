<?php

declare(strict_types=1);

namespace Tests\Fixtures\InspectApp;

use Studiometa\Foehn\Attributes\AsPostMeta;
use Studiometa\Foehn\Attributes\AsPostType;
use Timber\Post;

#[AsPostType(name: 'product', singular: 'Product', plural: 'Products', hasArchive: true, taxonomies: ['category'])]
#[AsPostMeta(key: 'price', type: 'number', description: 'What it costs')]
#[AsPostMeta(key: 'sku')]
final class Product extends Post {}
