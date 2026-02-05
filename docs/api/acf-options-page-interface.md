# AcfOptionsPageInterface

Interface for ACF options pages that define fields programmatically.

## Signature

```php
interface AcfOptionsPageInterface
{
    public static function fields(): FieldsBuilder;
}
```

## Methods

### fields()

Define ACF fields for this options page using `stoutlogic/acf-builder`.

**Returns:** `FieldsBuilder` — The configured fields builder

## Usage

### Basic Implementation

```php
<?php

namespace App\Options;

use Studiometa\Foehn\Attributes\AsAcfOptionsPage;
use Studiometa\Foehn\Contracts\AcfOptionsPageInterface;
use StoutLogic\AcfBuilder\FieldsBuilder;

#[AsAcfOptionsPage(
    pageTitle: 'Theme Settings',
    menuSlug: 'theme-settings',
)]
final class ThemeSettings implements AcfOptionsPageInterface
{
    public static function fields(): FieldsBuilder
    {
        return (new FieldsBuilder('theme_settings'))
            ->addText('site_name', [
                'label' => 'Site Name',
                'instructions' => 'Enter your site name',
            ])
            ->addTextarea('footer_text', [
                'label' => 'Footer Text',
                'rows' => 4,
            ]);
    }
}
```

### With Tabs and Groups

```php
public static function fields(): FieldsBuilder
{
    return (new FieldsBuilder('theme_settings'))
        ->addTab('general', ['label' => 'General'])
            ->addText('site_name')
            ->addImage('logo')

        ->addTab('social', ['label' => 'Social Media'])
            ->addUrl('facebook')
            ->addUrl('twitter')
            ->addUrl('instagram')

        ->addTab('footer', ['label' => 'Footer'])
            ->addWysiwyg('footer_content')
            ->addText('copyright');
}
```

### With Repeater Fields

```php
public static function fields(): FieldsBuilder
{
    return (new FieldsBuilder('partners_settings'))
        ->addRepeater('partners', ['label' => 'Partners', 'layout' => 'block'])
            ->addText('name', ['label' => 'Partner Name'])
            ->addImage('logo', ['label' => 'Partner Logo'])
            ->addUrl('website', ['label' => 'Website URL'])
        ->endRepeater();
}
```

### With Conditional Logic

```php
public static function fields(): FieldsBuilder
{
    return (new FieldsBuilder('display_settings'))
        ->addTrueFalse('show_banner', [
            'label' => 'Show Banner',
            'default_value' => false,
        ])
        ->addImage('banner_image', ['label' => 'Banner Image'])
            ->conditional('show_banner', '==', '1')
        ->addText('banner_text', ['label' => 'Banner Text'])
            ->conditional('show_banner', '==', '1');
}
```

## When to Implement

Implement this interface when you want to:

- Define fields programmatically with type safety
- Version control your field definitions
- Share field configurations across environments

## When Not to Implement

Skip implementing this interface when:

- Fields are defined via ACF UI
- Fields are imported from JSON
- You're using a third-party ACF field group

```php
#[AsAcfOptionsPage(pageTitle: 'External Settings')]
final class ExternalSettings
{
    // No interface needed - fields defined elsewhere
}
```

## Related

- [Guide: ACF Options Pages](/guide/acf-options-pages)
- [`#[AsAcfOptionsPage]`](./as-acf-options-page)
- [`AcfBlockInterface`](./acf-block-interface)
