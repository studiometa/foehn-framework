# #[AsCustomizerSection]

Adds a section with native fields to the Customizer (Appearance → Customize).

## Signature

```php
<?php

namespace Studiometa\Foehn\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsCustomizerSection
{
    public function __construct(
        public string $id,
        public string $title,
        public string $description = '',
        public ?string $panel = null,
        public int $priority = 160,
        public string $capability = 'edit_theme_options',
    ) {}
}
```

## Parameters

| Parameter     | Type      | Default                | Description                                                                   |
| ------------- | --------- | ---------------------- | ----------------------------------------------------------------------------- |
| `id`          | `string`  | _required_             | The section id, and the option group its settings are registered under        |
| `title`       | `string`  | _required_             | The section heading                                                           |
| `description` | `string`  | `''`                   | Shown at the top of the section                                               |
| `panel`       | `?string` | `null`                 | The id of the panel the section sits in                                       |
| `priority`    | `int`     | `160`                  | Where in the Customizer. Core sections run from `20` (Site Identity) to `160` |
| `capability`  | `string`  | `'edit_theme_options'` | What a user needs to see the section and save its settings                    |

The id of a section that exists, from core or a plugin, adds the fields to that section, and the other parameters are not used. `title_tagline` is Site Identity; `colors`, `header_image`, `background_image` and `static_front_page` are the other core sections.

## Usage

```php
<?php

namespace App\Settings;

use Studiometa\Foehn\Attributes\AsCustomizerSection;
use Studiometa\Foehn\Contracts\SettingsInterface;
use Studiometa\Foehn\Settings\Setting;

#[AsCustomizerSection(id: 'theme_social', title: 'Social networks', priority: 40)]
final readonly class SocialSettings implements SettingsInterface
{
    /** @return array<string, Setting> */
    public static function settings(): array
    {
        return [
            'theme_instagram' => Setting::string(label: 'Instagram', control: 'url'),
            'theme_linkedin' => Setting::string(label: 'LinkedIn', control: 'url'),
        ];
    }
}
```

The class implements [`SettingsInterface`](./as-settings-page#settingsinterface), as a settings page does, and declares each value with [`Setting`](./as-settings-page#setting).

## What Føhn registers

- On `init`: `register_setting()` for each setting, with its type, default and sanitiser, so `wp option update` saves through the same sanitiser.
- On `customize_register`, after core adds its sections:
  - `add_section()`, unless a section with that id exists;
  - `add_setting()` for each setting, stored as an option (`type: option`), with the setting's sanitiser;
  - `add_control()` for each setting, of the setting's control type, with its label, description and options.

Read the values with `Settings::get()`. A save in the Customizer empties the page cache.

## Related

- [Guide: Settings Pages](/guide/settings-pages#in-the-customizer)
- [#[AsSettingsPage]](./as-settings-page)
