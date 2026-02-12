<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Models;

use Studiometa\Foehn\Attributes\AsTimberModel;
use Studiometa\Foehn\Concerns\QueriesPostType;
use Timber\Post as TimberPost;

/**
 * Base post model with fluent query support.
 *
 * Extends Timber\Post and adds query methods via QueriesPostType trait.
 * Automatically registered as Timber's classmap for the 'post' type.
 *
 * Theme post type models should extend this class:
 *
 * @example
 * ```php
 * #[AsPostType(name: 'product', singular: 'Product', plural: 'Products')]
 * final class Product extends \Studiometa\Foehn\Models\Post
 * {
 *     public function getPrice(): float
 *     {
 *         return (float) $this->meta('price');
 *     }
 * }
 *
 * // Query methods are automatically available:
 * Product::query()->limit(10)->get();
 * Product::find(42);
 * ```
 */
#[AsTimberModel('post')]
class Post extends TimberPost
{
    use QueriesPostType;
}
