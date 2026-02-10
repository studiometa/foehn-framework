<?php

declare(strict_types=1);

namespace App\Models;

use Studiometa\Foehn\Attributes\AsPostType;
use Studiometa\Foehn\Contracts\ConfiguresPostType;
use Studiometa\Foehn\PostTypes\PostTypeBuilder;
use Timber\Post as TimberPost;

#[AsPostType(
    name: 'product',
    singular: 'Produit',
    plural: 'Produits',
    public: true,
    hasArchive: true,
    showInRest: true,
    menuIcon: 'dashicons-cart',
    supports: ['title', 'editor', 'thumbnail', 'excerpt', 'revisions'],
    taxonomies: ['product_category', 'product_tag'],
)]
final class Product extends TimberPost implements ConfiguresPostType
{
    public static function configurePostType(PostTypeBuilder $builder): PostTypeBuilder
    {
        return $builder
            ->setRewrite(['slug' => 'boutique', 'with_front' => false])
            ->setMenuPosition(5);
    }

    public function price(): ?float
    {
        $price = $this->meta('price');

        return $price ? (float) $price : null;
    }

    public function formattedPrice(): string
    {
        $price = $this->price();

        if ($price === null) {
            return __('Prix sur demande', 'starter-theme');
        }

        return number_format($price, 2, ',', ' ') . ' €';
    }

    public function isOnSale(): bool
    {
        $salePrice = $this->meta('sale_price');

        return $salePrice && (float) $salePrice < $this->price();
    }

    /**
     * @return list<\Timber\Term>
     */
    public function productCategories(): array
    {
        return $this->terms('product_category');
    }
}
