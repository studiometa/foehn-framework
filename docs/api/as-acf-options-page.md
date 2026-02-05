# #[AsAcfOptionsPage]

Register a class as an ACF (Advanced Custom Fields) options page.

## Signature

```php
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsAcfOptionsPage
{
    public function __construct(
        public string $pageTitle,
        public ?string $menuTitle = null,
        public ?string $menuSlug = null,
        public string $capability = 'edit_posts',
        public ?int $position = null,
        public ?string $parentSlug = null,
        public ?string $iconUrl = null,
        public bool $redirect = true,
        public ?string $postId = null,
        public bool $autoload = true,
        public ?string $updateButton = null,
        public ?string $updatedMessage = null,
    ) {}

    public function getMenuSlug(): string {}
    public function getMenuTitle(): string {}
    public function getPostId(): string {}
    public function isSubPage(): bool {}
}
```

## Parameters

| Parameter        | Type      | Default        | Description                                    |
| ---------------- | --------- | -------------- | ---------------------------------------------- |
| `pageTitle`      | `string`  | —              | Page title displayed on the page (required)    |
| `menuTitle`      | `?string` | `$pageTitle`   | Title displayed in admin menu                  |
| `menuSlug`       | `?string` | sanitized title| URL slug for the page                          |
| `capability`     | `string`  | `'edit_posts'` | Required capability to view page               |
| `position`       | `?int`    | `null`         | Menu position (null = bottom)                  |
| `parentSlug`     | `?string` | `null`         | Parent page slug (for sub-pages)               |
| `iconUrl`        | `?string` | `null`         | Menu icon (dashicon, URL, or base64 SVG)       |
| `redirect`       | `bool`    | `true`         | Redirect to first child page                   |
| `postId`         | `?string` | `$menuSlug`    | Custom post_id for `get_field()`               |
| `autoload`       | `bool`    | `true`         | Autoload options for better performance        |
| `updateButton`   | `?string` | `null`         | Custom text for save button                    |
| `updatedMessage` | `?string` | `null`         | Custom message after saving                    |

## Usage

### Basic Options Page

```php
<?php

namespace App\Options;

use Studiometa\Foehn\Attributes\AsAcfOptionsPage;
use Studiometa\Foehn\Contracts\AcfOptionsPageInterface;
use StoutLogic\AcfBuilder\FieldsBuilder;

#[AsAcfOptionsPage(
    pageTitle: 'Theme Settings',
    capability: 'manage_options',
)]
final class ThemeSettings implements AcfOptionsPageInterface
{
    public static function fields(): FieldsBuilder
    {
        return (new FieldsBuilder('theme_settings'))
            ->addText('site_name')
            ->addTextarea('footer_text');
    }
}
```

### Full Configuration

```php
#[AsAcfOptionsPage(
    pageTitle: 'Theme Settings',
    menuTitle: 'Theme',
    menuSlug: 'theme-settings',
    capability: 'manage_options',
    position: 59,
    iconUrl: 'dashicons-admin-generic',
    redirect: false,
    postId: 'theme_options',
    autoload: true,
    updateButton: 'Save Theme Settings',
    updatedMessage: 'Theme settings have been updated.',
)]
```

### Sub-Page

```php
#[AsAcfOptionsPage(
    pageTitle: 'Social Media Settings',
    parentSlug: 'theme-settings',
    capability: 'manage_options',
)]
final class SocialSettings implements AcfOptionsPageInterface
{
    public static function fields(): FieldsBuilder
    {
        return (new FieldsBuilder('social_settings'))
            ->addUrl('facebook')
            ->addUrl('twitter')
            ->addUrl('instagram');
    }
}
```

### Without Fields (External Definition)

```php
#[AsAcfOptionsPage(
    pageTitle: 'External Settings',
    menuSlug: 'external-settings',
)]
final class ExternalSettings
{
    // Fields defined via ACF UI or JSON import
}
```

## Helper Methods

### getMenuSlug()

Returns the effective menu slug (explicit or sanitized from page title).

```php
$attr = new AsAcfOptionsPage(pageTitle: 'Theme Settings');
$attr->getMenuSlug(); // 'theme-settings'

$attr = new AsAcfOptionsPage(pageTitle: 'Theme Settings', menuSlug: 'custom');
$attr->getMenuSlug(); // 'custom'
```

### getMenuTitle()

Returns the effective menu title (explicit or page title).

```php
$attr = new AsAcfOptionsPage(pageTitle: 'Theme Settings');
$attr->getMenuTitle(); // 'Theme Settings'

$attr = new AsAcfOptionsPage(pageTitle: 'Theme Settings', menuTitle: 'Theme');
$attr->getMenuTitle(); // 'Theme'
```

### getPostId()

Returns the effective post_id for `get_field()` calls.

```php
$attr = new AsAcfOptionsPage(pageTitle: 'Theme', menuSlug: 'theme-settings');
$attr->getPostId(); // 'theme-settings'

$attr = new AsAcfOptionsPage(pageTitle: 'Theme', postId: 'custom_id');
$attr->getPostId(); // 'custom_id'
```

### isSubPage()

Returns `true` if this is a sub-page (has a parent).

```php
$attr = new AsAcfOptionsPage(pageTitle: 'Theme');
$attr->isSubPage(); // false

$attr = new AsAcfOptionsPage(pageTitle: 'Social', parentSlug: 'theme');
$attr->isSubPage(); // true
```

## Optional Interface

Implementing `AcfOptionsPageInterface` is optional. When implemented, Føhn automatically registers the field group:

```php
interface AcfOptionsPageInterface
{
    public static function fields(): FieldsBuilder;
}
```

## Related

- [Guide: ACF Options Pages](/guide/acf-options-pages)
- [`AcfOptionsPageInterface`](./acf-options-page-interface)
- [`#[AsAcfBlock]`](./as-acf-block)
