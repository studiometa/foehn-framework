# #[AsSettingsPage]

Registers an admin page backed by the WordPress Settings API.

## Signature

```php
<?php

namespace Studiometa\Foehn\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsSettingsPage
{
    public function __construct(
        public string $slug,
        public string $title,
        public ?string $menuTitle = null,
        public ?string $parent = 'options-general.php',
        public string $capability = 'manage_options',
        public ?string $icon = null,
        public ?int $position = null,
    ) {}

    public function menuTitle(): string;
}
```

## Parameters

| Parameter    | Type      | Default                 | Description                                                           |
| ------------ | --------- | ----------------------- | --------------------------------------------------------------------- |
| `slug`       | `string`  | _required_              | The page slug, and the option group its settings are registered under |
| `title`      | `string`  | _required_              | The page heading                                                      |
| `menuTitle`  | `?string` | `null`                  | The menu label. Falls back to `title`                                 |
| `parent`     | `?string` | `'options-general.php'` | The admin menu it sits under. `null` makes it a top-level menu        |
| `capability` | `string`  | `'manage_options'`      | What a user needs to reach the page                                   |
| `icon`       | `?string` | `null`                  | Dashicon, URL or base64 SVG. Top-level pages only                     |
| `position`   | `?int`    | `null`                  | Where in the menu. Top-level pages only                               |

## SettingsInterface

```php
<?php

namespace Studiometa\Foehn\Contracts;

use Studiometa\Foehn\Settings\Setting;

interface SettingsInterface
{
    /** @return array<string, Setting> */
    public static function settings(): array;
}
```

Required. A class carrying the attribute without it is refused during discovery, because there is nothing to register.

## The form

Føhn draws it. On `admin_menu` it adds one untitled section to the page and one `add_settings_field()` for each setting, with the label in the row header (`label_for`, except for a checkbox, which labels itself). Each field shows the value `Settings::get()` answers. See [Fields](/guide/settings-pages#fields).

## Setting

```php
Setting::string(string $default = '', string $label = '', string $description = '', string $control = 'text', array $options = [], ?string $sanitize = null, bool $showInRest = false)
Setting::bool(bool $default = false, string $label = '', string $description = '', ?string $sanitize = null, bool $showInRest = false)
Setting::int(int $default = 0, string $label = '', string $description = '', array $options = [], ?string $sanitize = null, bool $showInRest = false)
Setting::number(float $default = 0.0, string $label = '', string $description = '', ?string $sanitize = null, bool $showInRest = false)
```

| Parameter     | Description                                                                                    |
| ------------- | ---------------------------------------------------------------------------------------------- |
| `label`       | The field label. The option name when it is empty                                              |
| `description` | The help text under the field, and the REST schema description when `showInRest` is on         |
| `control`     | `text`, `textarea`, `email`, `url` or `tel`. Any other value throws `InvalidArgumentException` |
| `options`     | value => label. Makes the field a `<select>`                                                   |
| `sanitize`    | A function name, or the name of a public static method on the page class — never a closure     |
| `showInRest`  | Off by default, unlike `#[AsPostMeta]`                                                         |

| Control                        | Default sanitiser                                   |
| ------------------------------ | --------------------------------------------------- |
| `text`, `tel`                  | `sanitize_text_field`                               |
| `textarea`                     | `sanitize_textarea_field`                           |
| `email`                        | `sanitize_email`                                    |
| `url`                          | `esc_url_raw`                                       |
| `checkbox` (`bool()`)          | `rest_sanitize_boolean`                             |
| `number` (`int()`, `number()`) | `absint`, `floatval`                                |
| `select` (`options`)           | Keeps one of the options, and the default otherwise |

## Settings

```php
Studiometa\Foehn\Settings\Settings::get(string $name, mixed $default = null): mixed;
Studiometa\Foehn\Settings\Settings::has(string $name): bool;
Studiometa\Foehn\Settings\Settings::all(): array;
```

`get()` answers with the declared default before the option has ever been saved — which `get_option()` does not — and applies the declared type to what was stored.

## Notes

- **Two hooks.** `register_setting()` runs on `init`; the menu entry and the fields are added on `admin_menu`, which the discovery hooks itself.
- **The page cache.** A change to a declared setting empties the whole page cache.
- **Option names are global.** The Settings API has no namespacing, so prefix them.
- **Native controls only.** No media picker, repeater or conditional field. See the [guide](/guide/settings-pages#fields).

## Related

- [Guide: Settings Pages](/guide/settings-pages)
- [#[AsPostMeta]](./as-post-meta)
- [#[AsAcfOptionsPage]](./as-acf-options-page)
