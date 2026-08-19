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
        public ?string $template = null,
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
| `template`   | `?string` | `null`                  | A Twig template rendered as the body of the form                      |

## SettingsPageInterface

```php
<?php

namespace Studiometa\Foehn\Contracts;

use Studiometa\Foehn\Settings\Setting;

interface SettingsPageInterface
{
    /** @return array<string, Setting> */
    public static function settings(): array;
}
```

Required. A class carrying the attribute without it is refused during discovery, because there is nothing to register.

## The form body

A page supplies it in one of two ways, and discovery refuses a page that supplies neither.

### A Twig template

`template: 'settings/theme-settings'` on the attribute. The template is rendered through `ViewEngineInterface` and receives:

| Variable   | Contents                                                      |
| ---------- | ------------------------------------------------------------- |
| `settings` | The current value of each declared setting, typed as declared |
| `page`     | `slug` and `title`                                            |

### SettingsFormInterface

```php
<?php

namespace Studiometa\Foehn\Contracts;

interface SettingsFormInterface
{
    public function form(): string;
}
```

For a form that needs more than the page's own values. Returns the HTML rather than echoing it, like `TemplateControllerInterface::handle()`; the page is resolved from the container, so a `ViewEngineInterface` in its constructor renders Twig here too. Implementing it takes precedence over a `template`.

## Setting

```php
Setting::string(string $default = '', ?string $sanitize = null, bool $showInRest = false, string $description = '')
Setting::bool(bool $default = false, …)
Setting::int(int $default = 0, …)
Setting::number(float $default = 0.0, …)
```

| Type      | Default sanitiser       |
| --------- | ----------------------- |
| `string`  | `sanitize_text_field`   |
| `boolean` | `rest_sanitize_boolean` |
| `integer` | `absint`                |
| `number`  | `floatval`              |

`sanitize` takes a function name, or the name of a public static method on the page class — never a closure. `showInRest` is off by default, unlike `#[AsPostMeta]`; `description` only has an effect when it is on.

## Settings

```php
Studiometa\Foehn\Settings\Settings::get(string $name, mixed $default = null): mixed;
Studiometa\Foehn\Settings\Settings::has(string $name): bool;
Studiometa\Foehn\Settings\Settings::all(): array;
```

`get()` answers with the declared default before the option has ever been saved — which `get_option()` does not — and applies the declared type to what was stored.

## Notes

- **Two hooks.** `register_setting()` runs on `init`; the menu entry is added on `admin_menu`, which the discovery hooks itself.
- **Option names are global.** The Settings API has no namespacing, so prefix them.
- **No field abstraction**, by decision. See the [guide](/guide/settings-pages#what-the-framework-gives-you-and-what-it-does-not).

## Related

- [Guide: Settings Pages](/guide/settings-pages)
- [#[AsPostMeta]](./as-post-meta)
- [#[AsAcfOptionsPage]](./as-acf-options-page)
