# #[AsTaxonomy]

Register a class as a custom WordPress taxonomy.

## Signature

```php
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsTaxonomy
{
    public function __construct(
        public string $name,
        public array $postTypes = [],
        public ?string $singular = null,
        public ?string $plural = null,
        public bool $public = true,
        public bool $hierarchical = false,
        public bool $showInRest = true,
        public bool $showAdminColumn = true,
        public ?string $rewriteSlug = null,
        public array $labels = [],
        public array|false|null $rewrite = null,
    ) {}
}
```

## Parameters

| Parameter         | Type                    | Default | Description                                        |
| ----------------- | ----------------------- | ------- | -------------------------------------------------- |
| `name`            | `string`                | —       | Taxonomy slug (required)                           |
| `postTypes`       | `string[]`              | `[]`    | Associated post type slugs                         |
| `singular`        | `?string`               | `null`  | Singular label                                     |
| `plural`          | `?string`               | `null`  | Plural label                                       |
| `public`          | `bool`                  | `true`  | Whether publicly visible                           |
| `hierarchical`    | `bool`                  | `false` | Hierarchical like categories                       |
| `showInRest`      | `bool`                  | `true`  | Enable REST API and Gutenberg                      |
| `showAdminColumn` | `bool`                  | `true`  | Show column in admin post list                     |
| `rewriteSlug`     | `?string`               | `null`  | Custom URL slug (shorthand for `rewrite`)          |
| `labels`          | `array<string, string>` | `[]`    | Custom labels (merged with auto-generated ones)    |
| `rewrite`         | `array\|false\|null`    | `null`  | Full rewrite config, `false` to disable, or `null` |

## Usage

### Basic Taxonomy

```php
<?php

namespace App\Taxonomies;

use Studiometa\Foehn\Attributes\AsTaxonomy;
use Timber\Term;

#[AsTaxonomy(
    name: 'product_category',
    postTypes: ['product'],
    singular: 'Category',
    plural: 'Categories',
    hierarchical: true,
)]
final class ProductCategory extends Term {}
```

### Tag-style Taxonomy

```php
#[AsTaxonomy(
    name: 'product_tag',
    postTypes: ['product'],
    singular: 'Tag',
    plural: 'Tags',
    hierarchical: false,
)]
final class ProductTag extends Term {}
```

### Shared Taxonomy

```php
#[AsTaxonomy(
    name: 'location',
    postTypes: ['event', 'team', 'office'],
    singular: 'Location',
    plural: 'Locations',
)]
final class Location extends Term {}
```

### With Advanced Configuration

Implement `ConfiguresTaxonomy` for full control:

```php
<?php

namespace App\Taxonomies;

use Studiometa\Foehn\Attributes\AsTaxonomy;
use Studiometa\Foehn\Contracts\ConfiguresTaxonomy;
use Studiometa\Foehn\PostTypes\TaxonomyBuilder;
use Timber\Term;

#[AsTaxonomy(
    name: 'skill',
    postTypes: ['team'],
    singular: 'Skill',
    plural: 'Skills',
)]
final class Skill extends Term implements ConfiguresTaxonomy
{
    public static function configureTaxonomy(TaxonomyBuilder $builder): TaxonomyBuilder
    {
        return $builder
            ->setCapabilities([
                'manage_terms' => 'manage_skills',
                'edit_terms' => 'edit_skills',
                'delete_terms' => 'delete_skills',
                'assign_terms' => 'assign_skills',
            ]);
    }
}
```

## Related

- [Guide: Taxonomies](/guide/taxonomies)
- [`#[AsPostType]`](./as-post-type)
