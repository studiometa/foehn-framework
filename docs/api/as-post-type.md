# #[AsPostType]

Register a class as a custom WordPress post type.

## Signature

```php
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsPostType
{
    public function __construct(
        public string $name,
        public ?string $singular = null,
        public ?string $plural = null,
        public bool $public = true,
        public bool $hasArchive = false,
        public bool $showInRest = true,
        public ?string $menuIcon = null,
        public array $supports = ['title', 'editor', 'thumbnail'],
        public array $taxonomies = [],
        public ?string $rewriteSlug = null,
    ) {}
}
```

## Parameters

| Parameter     | Type       | Default                            | Description                      |
| ------------- | ---------- | ---------------------------------- | -------------------------------- |
| `name`        | `string`   | —                                  | Post type slug (required)        |
| `singular`    | `?string`  | `null`                             | Singular label                   |
| `plural`      | `?string`  | `null`                             | Plural label                     |
| `public`      | `bool`     | `true`                             | Whether publicly visible         |
| `hasArchive`  | `bool`     | `false`                            | Enable archive pages             |
| `showInRest`  | `bool`     | `true`                             | Enable REST API and Gutenberg    |
| `menuIcon`    | `?string`  | `null`                             | Dashicon name or custom icon URL |
| `supports`    | `string[]` | `['title', 'editor', 'thumbnail']` | Supported features               |
| `taxonomies`  | `string[]` | `[]`                               | Associated taxonomy slugs        |
| `rewriteSlug` | `?string`  | `null`                             | Custom URL slug                  |

## Usage

### Basic Post Type

```php
<?php

namespace App\Models;

use Studiometa\WPTempest\Attributes\AsPostType;
use Timber\Post;

#[AsPostType(
    name: 'product',
    singular: 'Product',
    plural: 'Products',
)]
final class Product extends Post {}
```

### Full Configuration

```php
#[AsPostType(
    name: 'product',
    singular: 'Product',
    plural: 'Products',
    public: true,
    hasArchive: true,
    showInRest: true,
    menuIcon: 'dashicons-cart',
    supports: ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
    taxonomies: ['product_category', 'product_tag'],
    rewriteSlug: 'shop',
)]
final class Product extends Post
{
    public function price(): ?float
    {
        return $this->meta('price') ? (float) $this->meta('price') : null;
    }
}
```

### With Advanced Configuration

Implement `ConfiguresPostType` for full control:

```php
<?php

namespace App\Models;

use Studiometa\WPTempest\Attributes\AsPostType;
use Studiometa\WPTempest\Contracts\ConfiguresPostType;
use Timber\Post;

#[AsPostType(name: 'event', singular: 'Event', plural: 'Events')]
final class Event extends Post implements ConfiguresPostType
{
    public static function postTypeArgs(array $args): array
    {
        $args['capability_type'] = 'event';
        $args['map_meta_cap'] = true;

        return $args;
    }
}
```

## Supported Features

Available values for `supports`:

- `title` — Post title
- `editor` — Content editor
- `thumbnail` — Featured image
- `excerpt` — Excerpt field
- `author` — Author selection
- `comments` — Comments
- `trackbacks` — Trackbacks
- `revisions` — Revisions
- `custom-fields` — Custom fields
- `page-attributes` — Page attributes (order, parent)
- `post-formats` — Post formats

## Related

- [Guide: Post Types](/guide/post-types)
- [`#[AsTaxonomy]`](./as-taxonomy)
