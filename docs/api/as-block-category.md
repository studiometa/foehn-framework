# #[AsBlockCategory]

Register a custom block category.

## Signature

```php
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class AsBlockCategory
{
    public function __construct(
        public string $slug,
        public string $title,
        public ?string $icon = null,
    ) {}
}
```

## Parameters

| Parameter | Type      | Default | Description              |
| --------- | --------- | ------- | ------------------------ |
| `slug`    | `string`  | —       | Category slug (required) |
| `title`   | `string`  | —       | Display title (required) |
| `icon`    | `?string` | `null`  | Dashicon name            |

## Usage

### Single Category

```php
<?php

namespace App\Blocks;

use Studiometa\WPTempest\Attributes\AsBlockCategory;

#[AsBlockCategory(
    slug: 'theme',
    title: 'Theme Blocks',
    icon: 'star-filled',
)]
final class ThemeBlocks {}
```

### Multiple Categories

The attribute is repeatable:

```php
#[AsBlockCategory(slug: 'theme-layout', title: 'Layout')]
#[AsBlockCategory(slug: 'theme-content', title: 'Content')]
#[AsBlockCategory(slug: 'theme-media', title: 'Media')]
final class ThemeBlockCategories {}
```

### Using in Blocks

Reference the category in `#[AsBlock]` or `#[AsAcfBlock]`:

```php
#[AsBlock(
    name: 'theme/hero',
    title: 'Hero',
    category: 'theme-layout', // Uses custom category
)]
final class HeroBlock implements BlockInterface {}
```

## Related

- [Guide: Native Blocks](/guide/native-blocks)
- [`#[AsBlock]`](./as-block)
- [`#[AsAcfBlock]`](./as-acf-block)
