# AcfFieldGroupInterface

Interface for ACF (Advanced Custom Fields) field groups.

## Signature

```php
<?php

namespace Studiometa\Foehn\Contracts;

use StoutLogic\AcfBuilder\FieldsBuilder;

interface AcfFieldGroupInterface
{
    /**
     * Define ACF fields for this group.
     *
     * @return FieldsBuilder The configured fields builder
     */
    public static function fields(): FieldsBuilder;
}
```

## Methods

### fields()

Define ACF fields using `stoutlogic/acf-builder`. This is a static method called during registration.

```php
public static function fields(): FieldsBuilder
{
    return (new FieldsBuilder('product_fields'))
        ->addText('sku', ['label' => 'SKU'])
        ->addNumber('price', ['label' => 'Price'])
        ->addWysiwyg('description', ['label' => 'Description']);
}
```

## Usage

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

## Complex Fields Example

```php
public static function fields(): FieldsBuilder
{
    return (new FieldsBuilder('property_fields'))
        ->addTab('General')
            ->addText('external_id', ['label' => 'External ID'])
            ->addText('address', ['label' => 'Address'])
            ->addNumber('bedrooms', ['label' => 'Bedrooms'])
            ->addNumber('bathrooms', ['label' => 'Bathrooms'])

        ->addTab('Media')
            ->addGallery('photos', ['label' => 'Photos'])
            ->addOembed('video_tour', ['label' => 'Video Tour'])

        ->addTab('Features')
            ->addRepeater('features', ['layout' => 'table'])
                ->addText('name')
                ->addText('value')
            ->endRepeater()

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
```

## Comparison with AcfBlockInterface

| Feature            | `AcfFieldGroupInterface`   | `AcfBlockInterface`              |
| ------------------ | -------------------------- | -------------------------------- |
| Purpose            | Post/page/taxonomy fields  | Gutenberg block fields           |
| Methods            | `fields()` only            | `fields()`, `compose()`, `render()` |
| Location           | Set via attribute          | Automatically set to block       |
| Rendering          | WordPress handles display  | Class handles rendering          |

## Accessing Field Values

Field values are accessed via standard ACF functions in your templates or code:

```php
// In PHP
$sku = get_field('sku');
$price = get_field('price');

// In Twig (with Timber)
{{ post.meta('sku') }}
{{ post.meta('price') }}

// Or using ACF functions
{{ function('get_field', 'sku') }}
```

## Related

- [`#[AsAcfFieldGroup]`](./as-acf-field-group)
- [`AcfBlockInterface`](./acf-block-interface)
- [`#[AsAcfBlock]`](./as-acf-block)
