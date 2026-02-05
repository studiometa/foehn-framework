# #[AsAcfFieldGroup]

Register a class as an ACF (Advanced Custom Fields) field group for post types, page templates, taxonomies, or options pages.

## Signature

```php
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsAcfFieldGroup
{
    public function __construct(
        public string $name,
        public string $title,
        public array $location,
        public string $position = 'normal',
        public int $menuOrder = 0,
        public string $style = 'default',
        public string $labelPlacement = 'top',
        public string $instructionPlacement = 'label',
        public array $hideOnScreen = [],
    ) {}
}
```

## Parameters

| Parameter               | Type       | Default     | Description                                           |
| ----------------------- | ---------- | ----------- | ----------------------------------------------------- |
| `name`                  | `string`   | —           | Unique field group name (required)                    |
| `title`                 | `string`   | —           | Display title in admin (required)                     |
| `location`              | `array`    | —           | Location rules (required)                             |
| `position`              | `string`   | `'normal'`  | Position: `acf_after_title`, `normal`, `side`         |
| `menuOrder`             | `int`      | `0`         | Order in admin                                        |
| `style`                 | `string`   | `'default'` | Style: `default`, `seamless`                          |
| `labelPlacement`        | `string`   | `'top'`     | Label placement: `top`, `left`                        |
| `instructionPlacement`  | `string`   | `'label'`   | Instruction placement: `label`, `field`               |
| `hideOnScreen`          | `string[]` | `[]`        | Elements to hide: `the_content`, `excerpt`, etc.      |

## Location Syntax

The `location` parameter supports two formats:

### Simplified Format

For common use cases with a single condition:

```php
// Post type
#[AsAcfFieldGroup(location: ['post_type' => 'product'])]

// Page template
#[AsAcfFieldGroup(location: ['page_template' => 'page-faq.php'])]

// Taxonomy
#[AsAcfFieldGroup(location: ['taxonomy' => 'product_category'])]

// Options page
#[AsAcfFieldGroup(location: ['options_page' => 'theme-settings'])]

// Multiple AND conditions
#[AsAcfFieldGroup(location: [
    'post_type' => 'page',
    'page_template' => 'page-contact.php',
])]
```

### Full ACF Format

For complex rules with OR/AND conditions:

```php
#[AsAcfFieldGroup(location: [
    // First OR group (post_type = product AND status != draft)
    [
        ['param' => 'post_type', 'operator' => '==', 'value' => 'product'],
        ['param' => 'post_status', 'operator' => '!=', 'value' => 'draft'],
    ],
    // Second OR group (page template)
    [
        ['param' => 'page_template', 'operator' => '==', 'value' => 'page-shop.php'],
    ],
])]
```

## Usage

### Basic Field Group

```php
<?php

namespace App\Fields;

use Studiometa\Foehn\Attributes\AsAcfFieldGroup;
use Studiometa\Foehn\Contracts\AcfFieldGroupInterface;
use StoutLogic\AcfBuilder\FieldsBuilder;

#[AsAcfFieldGroup(
    name: 'product_fields',
    title: 'Product Details',
    location: ['post_type' => 'product'],
)]
final class ProductFields implements AcfFieldGroupInterface
{
    public static function fields(): FieldsBuilder
    {
        return (new FieldsBuilder('product_fields'))
            ->addText('sku', ['label' => 'SKU'])
            ->addNumber('price', ['label' => 'Price'])
            ->addWysiwyg('description', ['label' => 'Description']);
    }
}
```

### Full Configuration

```php
#[AsAcfFieldGroup(
    name: 'property_fields',
    title: 'Property Details',
    location: ['post_type' => 'property'],
    position: 'acf_after_title',
    menuOrder: 0,
    style: 'seamless',
    labelPlacement: 'left',
    instructionPlacement: 'field',
    hideOnScreen: ['the_content', 'excerpt', 'featured_image'],
)]
final class PropertyFields implements AcfFieldGroupInterface
{
    public static function fields(): FieldsBuilder
    {
        return (new FieldsBuilder('property_fields'))
            ->addText('external_id', ['label' => 'External ID'])
            ->addTab('General')
                ->addText('address', ['label' => 'Address'])
                ->addNumber('bedrooms', ['label' => 'Bedrooms'])
                ->addNumber('bathrooms', ['label' => 'Bathrooms'])
            ->addTab('Pricing')
                ->addNumber('price', ['label' => 'Price'])
                ->addSelect('status', [
                    'label' => 'Status',
                    'choices' => [
                        'available' => 'Available',
                        'pending' => 'Pending',
                        'sold' => 'Sold',
                    ],
                ]);
    }
}
```

### Page Template Fields

```php
#[AsAcfFieldGroup(
    name: 'faq_page_fields',
    title: 'FAQ Page',
    location: ['page_template' => 'page-faq.php'],
)]
final class FAQPageFields implements AcfFieldGroupInterface
{
    public static function fields(): FieldsBuilder
    {
        return (new FieldsBuilder('faq_page_fields'))
            ->addText('intro_title')
            ->addTextarea('intro_text')
            ->addRepeater('faqs', ['layout' => 'block'])
                ->addText('question')
                ->addWysiwyg('answer')
            ->endRepeater();
    }
}
```

### Taxonomy Term Fields

```php
#[AsAcfFieldGroup(
    name: 'category_fields',
    title: 'Category Settings',
    location: ['taxonomy' => 'category'],
)]
final class CategoryFields implements AcfFieldGroupInterface
{
    public static function fields(): FieldsBuilder
    {
        return (new FieldsBuilder('category_fields'))
            ->addImage('featured_image', ['label' => 'Featured Image'])
            ->addColorPicker('accent_color', ['label' => 'Accent Color']);
    }
}
```

### Complex Location Rules

```php
#[AsAcfFieldGroup(
    name: 'shop_fields',
    title: 'Shop Fields',
    location: [
        // Show on products that are not drafts
        [
            ['param' => 'post_type', 'operator' => '==', 'value' => 'product'],
            ['param' => 'post_status', 'operator' => '!=', 'value' => 'draft'],
        ],
        // OR show on the shop page template
        [
            ['param' => 'page_template', 'operator' => '==', 'value' => 'page-shop.php'],
        ],
    ],
)]
final class ShopFields implements AcfFieldGroupInterface
{
    // ...
}
```

## Required Interface

Classes must implement `AcfFieldGroupInterface`:

```php
interface AcfFieldGroupInterface
{
    public static function fields(): FieldsBuilder;
}
```

## Suggested File Structure

```
app/
├── Fields/
│   ├── PostType/
│   │   ├── ProductFields.php
│   │   └── PropertyFields.php
│   ├── Page/
│   │   ├── FrontPageFields.php
│   │   └── FAQPageFields.php
│   ├── Taxonomy/
│   │   └── CategoryFields.php
│   └── Options/
│       └── ThemeSettingsFields.php
```

## Related

- [`AcfFieldGroupInterface`](./acf-field-group-interface)
- [`#[AsAcfBlock]`](./as-acf-block)
- [Guide: ACF Blocks](/guide/acf-blocks)
