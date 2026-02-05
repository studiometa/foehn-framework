# #[AsFieldGroup]

Register a class as an ACF field group.

## Signature

```php
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsFieldGroup
{
    public function __construct(
        public string $key,
        public string $title,
        public array $location = [],
        public int $menuOrder = 0,
        public string $position = 'normal',
        public string $style = 'default',
        public string $labelPlacement = 'top',
        public string $instructionPlacement = 'label',
        public bool $active = true,
    ) {}
}
```

## Parameters

| Parameter              | Type                        | Default   | Description                                            |
| ---------------------- | --------------------------- | --------- | ------------------------------------------------------ |
| `key`                  | `string`                    | —         | Unique field group key (e.g., `product_fields`)        |
| `title`                | `string`                    | —         | Field group title displayed in admin                   |
| `location`             | `array<array<string,mixed>>`| `[]`      | Location rules for where to show the field group       |
| `menuOrder`            | `int`                       | `0`       | Order in the admin meta box list                       |
| `position`             | `string`                    | `normal`  | Position: `acf_after_title`, `normal`, `side`          |
| `style`                | `string`                    | `default` | Style: `default`, `seamless`                           |
| `labelPlacement`       | `string`                    | `top`     | Label placement: `top`, `left`                         |
| `instructionPlacement` | `string`                    | `label`   | Instruction placement: `label`, `field`                |
| `active`               | `bool`                      | `true`    | Whether the field group is active                      |

## Usage

### Basic Usage

```php
<?php

namespace App\Fields\PostType;

use StoutLogic\AcfBuilder\FieldsBuilder;
use Studiometa\Foehn\Attributes\AsFieldGroup;

#[AsFieldGroup(
    key: 'product_fields',
    title: 'Product Fields',
    location: [
        ['post_type', '==', 'product'],
    ],
)]
final class ProductFields
{
    public static function fields(): FieldsBuilder
    {
        $fields = new FieldsBuilder('product_fields');

        $fields
            ->addText('sku', ['label' => 'SKU'])
            ->addNumber('price', ['label' => 'Price'])
            ->addWysiwyg('specifications', ['label' => 'Specifications']);

        return $fields;
    }
}
```

### For Page Templates

```php
#[AsFieldGroup(
    key: 'front_page_fields',
    title: 'Front Page Settings',
    location: [
        ['page_template', '==', 'front-page.php'],
    ],
    position: 'acf_after_title',
    style: 'seamless',
)]
final class FrontPageFields
{
    public static function fields(): FieldsBuilder
    {
        $fields = new FieldsBuilder('front_page_fields');

        $fields
            ->addText('hero_title', ['label' => 'Hero Title'])
            ->addTextarea('hero_subtitle', ['label' => 'Hero Subtitle'])
            ->addImage('hero_image', ['label' => 'Hero Image']);

        return $fields;
    }
}
```

### For Taxonomies

```php
#[AsFieldGroup(
    key: 'category_fields',
    title: 'Category Settings',
    location: [
        ['taxonomy', '==', 'category'],
    ],
)]
final class CategoryFields
{
    public static function fields(): FieldsBuilder
    {
        $fields = new FieldsBuilder('category_fields');

        $fields
            ->addImage('featured_image', ['label' => 'Featured Image'])
            ->addColorPicker('accent_color', ['label' => 'Accent Color']);

        return $fields;
    }
}
```

### Complex Location Rules

```php
#[AsFieldGroup(
    key: 'sidebar_fields',
    title: 'Sidebar Settings',
    location: [
        ['post_type', '==', 'post'],
        ['post_type', '==', 'page'],
    ],
    position: 'side',
)]
final class SidebarFields
{
    public static function fields(): FieldsBuilder
    {
        $fields = new FieldsBuilder('sidebar_fields');

        $fields
            ->addTrueFalse('hide_sidebar', ['label' => 'Hide Sidebar'])
            ->addSelect('sidebar_position', [
                'label' => 'Position',
                'choices' => ['left' => 'Left', 'right' => 'Right'],
            ]);

        return $fields;
    }
}
```

## CLI Scaffolding

Generate a field group with the CLI:

```bash
# For a post type
wp tempest make:field-group ProductFields --post-type=product

# For a page template
wp tempest make:field-group FrontPageFields --page-template=front-page

# For a taxonomy
wp tempest make:field-group CategoryFields --taxonomy=category

# Preview without creating
wp tempest make:field-group ProductFields --post-type=product --dry-run
```

## Related

- [`#[AsOptionsPage]`](./as-options-page)
- [`#[AsAcfBlock]`](./as-acf-block)
- [ACF Builder Documentation](https://github.com/StoutLogic/acf-builder)
