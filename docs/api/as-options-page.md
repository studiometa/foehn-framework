# #[AsOptionsPage]

Register a class as an ACF options page.

## Signature

```php
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsOptionsPage
{
    public function __construct(
        public string $pageTitle,
        public string $menuTitle,
        public string $menuSlug,
        public string $capability = 'edit_posts',
        public string $parentSlug = '',
        public int $position = 99,
        public string $iconUrl = 'dashicons-admin-generic',
        public bool $redirect = true,
        public string $postId = 'options',
        public bool $autoload = true,
        public string $updateButton = 'Update',
        public string $updatedMessage = 'Options Updated',
    ) {}
}
```

## Parameters

| Parameter        | Type     | Default                    | Description                                     |
| ---------------- | -------- | -------------------------- | ----------------------------------------------- |
| `pageTitle`      | `string` | —                          | Title displayed on the options page             |
| `menuTitle`      | `string` | —                          | Title displayed in the admin menu               |
| `menuSlug`       | `string` | —                          | Unique slug for the options page URL            |
| `capability`     | `string` | `edit_posts`               | Required capability to access the page          |
| `parentSlug`     | `string` | `''`                       | Parent menu slug (empty for top-level menu)     |
| `position`       | `int`    | `99`                       | Menu position (only for top-level menus)        |
| `iconUrl`        | `string` | `dashicons-admin-generic`  | Dashicon class or icon URL                      |
| `redirect`       | `bool`   | `true`                     | Whether to redirect to the first child page     |
| `postId`         | `string` | `options`                  | Custom post ID for storing options              |
| `autoload`       | `bool`   | `true`                     | Whether to autoload options on every page load  |
| `updateButton`   | `string` | `Update`                   | Text for the update button                      |
| `updatedMessage` | `string` | `Options Updated`          | Message displayed after saving                  |

## Usage

### Basic Usage

```php
<?php

namespace App\Fields\Options;

use StoutLogic\AcfBuilder\FieldsBuilder;
use Studiometa\Foehn\Attributes\AsOptionsPage;

#[AsOptionsPage(
    pageTitle: 'Theme Settings',
    menuTitle: 'Theme Settings',
    menuSlug: 'theme-settings',
    iconUrl: 'dashicons-admin-customizer',
)]
final class ThemeSettings
{
    public static function fields(): FieldsBuilder
    {
        $fields = new FieldsBuilder('theme_settings');

        $fields
            ->addTab('general', ['label' => 'General'])
            ->addText('company_name', ['label' => 'Company Name'])
            ->addEmail('contact_email', ['label' => 'Contact Email'])
            ->addTab('social', ['label' => 'Social Media'])
            ->addUrl('facebook_url', ['label' => 'Facebook'])
            ->addUrl('twitter_url', ['label' => 'Twitter'])
            ->addUrl('instagram_url', ['label' => 'Instagram']);

        return $fields;
    }

    /**
     * Get an option value.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = get_field($key, 'option');

        return $value !== null && $value !== false ? $value : $default;
    }
}
```

### Submenu Page

```php
#[AsOptionsPage(
    pageTitle: 'Footer Settings',
    menuTitle: 'Footer',
    menuSlug: 'footer-settings',
    parentSlug: 'theme-settings',
)]
final class FooterSettings
{
    public static function fields(): FieldsBuilder
    {
        $fields = new FieldsBuilder('footer_settings');

        $fields
            ->addWysiwyg('footer_text', ['label' => 'Footer Text'])
            ->addRepeater('footer_links', ['label' => 'Footer Links'])
                ->addText('label', ['label' => 'Label'])
                ->addUrl('url', ['label' => 'URL'])
            ->endRepeater();

        return $fields;
    }
}
```

### With Custom Capability

```php
#[AsOptionsPage(
    pageTitle: 'Advanced Settings',
    menuTitle: 'Advanced',
    menuSlug: 'advanced-settings',
    capability: 'manage_options', // Only administrators
    parentSlug: 'theme-settings',
)]
final class AdvancedSettings
{
    public static function fields(): FieldsBuilder
    {
        $fields = new FieldsBuilder('advanced_settings');

        $fields
            ->addTrueFalse('enable_debug', ['label' => 'Enable Debug Mode'])
            ->addTextarea('custom_scripts', ['label' => 'Custom Scripts']);

        return $fields;
    }
}
```

## Accessing Options

### In PHP

```php
// Using the static helper
$companyName = ThemeSettings::get('company_name');
$email = ThemeSettings::get('contact_email', 'default@example.com');

// Using ACF directly
$companyName = get_field('company_name', 'option');
```

### In Twig Templates

```twig
{# Using ACF function #}
{{ fn('get_field', 'company_name', 'option') }}

{# Or add to context via a view composer #}
{{ options.company_name }}
```

## CLI Scaffolding

Generate an options page with the CLI:

```bash
# Top-level options page
wp tempest make:options-page ThemeSettings

# Submenu options page
wp tempest make:options-page FooterSettings --parent=theme-settings

# With custom icon
wp tempest make:options-page SocialSettings --icon=dashicons-share

# Preview without creating
wp tempest make:options-page ThemeSettings --dry-run
```

## Related

- [`#[AsFieldGroup]`](./as-field-group)
- [ACF Options Page Documentation](https://www.advancedcustomfields.com/resources/options-page/)
