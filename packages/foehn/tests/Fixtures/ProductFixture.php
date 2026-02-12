<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsPostType;
use Studiometa\Foehn\Models\Post;

/**
 * Test fixture for a custom post type with query support.
 */
#[AsPostType(name: 'product', singular: 'Product', plural: 'Products')]
final class ProductFixture extends Post {}
