# ACF Options Pages

Føhn provides `#[AsAcfOptionsPage]` for creating ACF options pages with type-safe fields using `stoutlogic/acf-builder`.

## Requirements

- [ACF Pro](https://www.advancedcustomfields.com/pro/) installed and active
- `stoutlogic/acf-builder` package

```bash
composer require stoutlogic/acf-builder
```

## Basic Options Page

```php
<?php
// app/Options/ThemeSettings.php

namespace App\Options;

use Studiometa\Foehn\Attributes\AsAcfOptionsPage;
use Studiometa\Foehn\Contracts\AcfOptionsPageInterface;
use StoutLogic\AcfBuilder\FieldsBuilder;

#[AsAcfOptionsPage(
    pageTitle: 'Theme Settings',
    menuTitle: 'Theme',
    menuSlug: 'theme-settings',
    capability: 'manage_options',
    iconUrl: 'dashicons-admin-generic',
)]
final class ThemeSettings implements AcfOptionsPageInterface
{
    public static function fields(): FieldsBuilder
    {
        return (new FieldsBuilder('theme_settings'))
            ->addText('site_name', ['label' => 'Site Name'])
            ->addTextarea('footer_text', ['label' => 'Footer Text'])
            ->addImage('logo', ['label' => 'Site Logo']);
    }
}
```

## Retrieving Option Values

### Using AcfOptionsService

```php
<?php

namespace App\ContextProviders;

use Studiometa\Foehn\Attributes\AsContextProvider;
use Studiometa\Foehn\Contracts\ContextProviderInterface;
use Studiometa\Foehn\Services\AcfOptionsService;

#[AsContextProvider(templates: ['*'])]
final readonly class GlobalContextProvider implements ContextProviderInterface
{
    public function __construct(
        private AcfOptionsService $options,
    ) {}

    public function provide(array $context): array
    {
        return [
            ...$context,
            'site_name' => $this->options->get('site_name', 'theme-settings'),
            'footer_text' => $this->options->get('footer_text', 'theme-settings'),
            'logo' => $this->options->get('logo', 'theme-settings'),
        ];
    }
}
```

### Using ACF Functions Directly

```php
// Get a single field
$siteName = get_field('site_name', 'theme-settings');

// Get all fields
$settings = get_fields('theme-settings');
```

## Sub-Pages

Create child pages under a parent options page:

```php
<?php
// app/Options/SocialSettings.php

namespace App\Options;

use Studiometa\Foehn\Attributes\AsAcfOptionsPage;
use Studiometa\Foehn\Contracts\AcfOptionsPageInterface;
use StoutLogic\AcfBuilder\FieldsBuilder;

#[AsAcfOptionsPage(
    pageTitle: 'Social Media',
    parentSlug: 'theme-settings',
    capability: 'manage_options',
)]
final class SocialSettings implements AcfOptionsPageInterface
{
    public static function fields(): FieldsBuilder
    {
        return (new FieldsBuilder('social_settings'))
            ->addUrl('facebook', ['label' => 'Facebook URL'])
            ->addUrl('twitter', ['label' => 'Twitter URL'])
            ->addUrl('instagram', ['label' => 'Instagram URL'])
            ->addUrl('linkedin', ['label' => 'LinkedIn URL']);
    }
}
```

## Full Configuration

```php
#[AsAcfOptionsPage(
    pageTitle: 'Advanced Settings',
    menuTitle: 'Advanced',
    menuSlug: 'advanced-settings',
    capability: 'manage_options',
    position: 59,
    iconUrl: 'dashicons-admin-settings',
    redirect: false,
    postId: 'advanced_options',
    autoload: true,
    updateButton: 'Save Settings',
    updatedMessage: 'Settings saved successfully!',
)]
```

## Options Without Fields

You can create options pages without implementing `AcfOptionsPageInterface`. This is useful when fields are defined elsewhere or via the ACF UI:

```php
<?php

namespace App\Options;

use Studiometa\Foehn\Attributes\AsAcfOptionsPage;

#[AsAcfOptionsPage(
    pageTitle: 'External Settings',
    menuSlug: 'external-settings',
)]
final class ExternalSettings
{
    // Fields defined in ACF UI or imported JSON
}
```

## AcfOptionsService API

The `AcfOptionsService` provides a convenient wrapper around ACF functions:

```php
use Studiometa\Foehn\Services\AcfOptionsService;

$options = new AcfOptionsService();

// Get a single field value
$value = $options->get('field_name', 'options-page-slug');

// Get all fields from an options page
$allFields = $options->all('options-page-slug');

// Check if a field has a value
if ($options->has('field_name', 'options-page-slug')) {
    // Field has a non-empty value
}

// Get field object with metadata
$fieldObject = $options->getObject('field_name', 'options-page-slug');
```

## Using in Twig Templates

```twig
{# Get options in a context provider and pass to template #}
<footer class="footer">
    <p>{{ footer_text }}</p>

    {% if logo %}
        <img src="{{ logo.src }}" alt="{{ site_name }}">
    {% endif %}
</footer>
```

## Related

- [API: #[AsAcfOptionsPage]](/api/as-acf-options-page)
- [API: AcfOptionsPageInterface](/api/acf-options-page-interface)
- [Guide: ACF Blocks](/guide/acf-blocks)
- [Guide: Context Providers](/guide/context-providers)
