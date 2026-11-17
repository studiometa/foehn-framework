# Settings Pages

An admin screen for the handful of values a theme needs: a contact address, a feature toggle, an API key. `#[AsSettingsPage]` puts it on the WordPress Settings API, and [`#[AsCustomizerSection]`](#in-the-customizer) puts the same declaration in the Customizer.

Named for the API it wraps. WordPress uses both words — `add_options_page()` registers the menu entry, while everything that does the work is `register_setting()`, `add_settings_section()` and `settings_fields()`.

## A page

```php
<?php
// app/Settings/ThemeSettings.php

namespace App\Settings;

use Studiometa\Foehn\Attributes\AsSettingsPage;
use Studiometa\Foehn\Contracts\SettingsInterface;
use Studiometa\Foehn\Settings\Setting;

#[AsSettingsPage(
    slug: 'theme-settings',
    title: 'Theme settings',
    parent: 'themes.php',
)]
final readonly class ThemeSettings implements SettingsInterface
{
    /** @return array<string, Setting> */
    public static function settings(): array
    {
        return [
            'theme_contact_email' => Setting::string(label: 'Contact email', control: 'email'),
            'theme_show_banner' => Setting::bool(label: 'Banner', description: 'Show the announcement banner'),
            'theme_layout' => Setting::string(
                default: 'grid',
                label: 'Layout',
                options: ['grid' => 'Grid', 'list' => 'List'],
            ),
        ];
    }
}
```

That is the whole page. Each `Setting` says what is stored and which field edits it, and Føhn draws the form with the native APIs, so the page looks like Settings → General:

```
<div class="wrap">
  <h1>Theme settings</h1>
  settings_errors()          ← "Settings saved." and the errors
  <form action="options.php" method="post">
    settings_fields()        ← the nonce and option group, without which the save is rejected
    do_settings_sections()   ← a form-table row for each setting, from add_settings_field()
    submit_button()
  </form>
</div>
```

`settings_fields()` is why Føhn draws the page: a page that forgets it looks like it simply does not save, with no error anywhere.

## Fields

The control of a setting is the native field that edits it. Føhn prints the markup WordPress uses on its own screens:

| Setting                                | Field                                                             |
| -------------------------------------- | ----------------------------------------------------------------- |
| `Setting::string()`                    | `<input type="text" class="regular-text">`                        |
| `Setting::string(control: 'email')`    | `<input type="email" class="regular-text">`, also `url` and `tel` |
| `Setting::string(control: 'textarea')` | `<textarea class="large-text" rows="5">`                          |
| `Setting::string(options: [...])`      | `<select>`, one `<option>` for each value => label                |
| `Setting::int()`, `Setting::number()`  | `<input type="number" class="small-text">`                        |
| `Setting::int(options: [...])`         | `<select>`                                                        |
| `Setting::bool()`                      | A checkbox in a `fieldset`, with the `description` as its text    |

`label` is the row label, and the option name when it is empty. `description` is the help text in a `p.description` under the field, which the field points at with `aria-describedby`.

The controls are the ones WordPress draws without a script. Repeaters, conditional fields and media pickers are not here: they are the signal to use [an ACF options page](/guide/acf-options-pages).

## Declaring a setting

| Factory                                | Type      | Default sanitiser                                   |
| -------------------------------------- | --------- | --------------------------------------------------- |
| `Setting::string()`                    | `string`  | `sanitize_text_field`                               |
| `Setting::string(control: 'textarea')` | `string`  | `sanitize_textarea_field`                           |
| `Setting::string(control: 'email')`    | `string`  | `sanitize_email`                                    |
| `Setting::string(control: 'url')`      | `string`  | `esc_url_raw`                                       |
| `Setting::bool()`                      | `boolean` | `rest_sanitize_boolean`                             |
| `Setting::int()`                       | `integer` | `absint`                                            |
| `Setting::number()`                    | `number`  | `floatval`                                          |
| any factory with `options`             | its type  | keeps one of the options, and the default otherwise |

Each takes `default`, `label`, `description`, `sanitize` and `showInRest`. `string()` also takes `control` and `options`, and `int()` takes `options`.

`sanitize` is a **function name**, or the name of a public static method on the page class — never a closure, because a discovery item reaches the cache through `var_export()`:

```php
'theme_ratio' => Setting::number(default: 1.5, sanitize: 'clampRatio'),

// …on the page class:
public static function clampRatio(mixed $value): float
{
    return min(2.0, max(0.5, (float) $value));
}
```

**`showInRest` is off by default**, unlike [`#[AsPostMeta]`](/api/as-post-meta). Settings are configuration and sometimes credentials, so exposure is opt-in. When it is on, `description` is also the description in the REST schema.

**Labels are plain text.** `settings()` runs during discovery and its result is cached, so a label wrapped in `__()` keeps the language of the request that filled the cache.

::: warning
**Option names are global.** The Settings API has no namespacing: `'contact_email'` becomes a WordPress option of exactly that name, on a site that may run other plugins. Prefix them.
:::

## Reading a setting

```php
use Studiometa\Foehn\Settings\Settings;

Settings::get('theme_show_banner');   // false, before anything is saved
Settings::get('theme_contact_email');
```

Read through `Settings::get()` rather than `get_option()`. Two reasons, both of which bite:

- `get_option()` answers `false` for an option that has never been saved, whatever `register_setting()` was told the default was — the default only applies once the row exists.
- WordPress stores an unchecked checkbox as the empty string and a checked one as `'1'`. `Settings::get()` applies the type the page declared, so a boolean setting answers `true` or `false`.

In Twig, read them through a [context provider](/guide/context-providers) rather than reaching for a global.

A change to a declared setting empties the whole [page cache](/guide/page-cache), as a change to the site title does: a phone number or a social link is on every page.

## From the command line

`wp foehn inspect settings` lists each `#[AsSettingsPage]` with the options it stores: option name, type, label, control, default and sanitizer.

```bash
wp foehn inspect settings
```

Føhn registers the settings on `init`, so the sanitizer also runs under WP-CLI. `wp option update theme_contact_email …` saves the value as the admin form does.

## Where the page appears

| `parent`                          | Result                                             |
| --------------------------------- | -------------------------------------------------- |
| `'options-general.php'` (default) | Under Settings                                     |
| `'themes.php'`                    | Under Appearance                                   |
| `'edit.php?post_type=book'`       | Under that post type's menu                        |
| `null`                            | Its own top-level menu, with `icon` and `position` |

`capability` defaults to `manage_options` and is what WordPress checks before showing the page at all.

## In the Customizer

Values that change how the site looks — a phone number in the footer, social links, a baseline — are better next to the live preview. `#[AsCustomizerSection]` takes the same `settings()` as a page and adds a section to Appearance → Customize:

```php
<?php
// app/Settings/ContactSettings.php

namespace App\Settings;

use Studiometa\Foehn\Attributes\AsCustomizerSection;
use Studiometa\Foehn\Contracts\SettingsInterface;
use Studiometa\Foehn\Settings\Setting;

#[AsCustomizerSection(id: 'theme_contact', title: 'Contact', priority: 30)]
final readonly class ContactSettings implements SettingsInterface
{
    /** @return array<string, Setting> */
    public static function settings(): array
    {
        return [
            'theme_phone' => Setting::string(label: 'Phone', control: 'tel'),
            'theme_address' => Setting::string(label: 'Address', control: 'textarea'),
        ];
    }
}
```

Each setting gets a native Customizer control of its control type, with its label, description and options. The values are stored as options, not theme mods, so `Settings::get()` reads them as it reads a settings page, and a save empties the page cache.

**An id that exists adds the fields to that section.** `id: 'title_tagline'` puts them in Site Identity, next to the site title and the logo. The other arguments are then not used.

| Argument      | Default                | Description                                      |
| ------------- | ---------------------- | ------------------------------------------------ |
| `id`          | _required_             | The section id, and the option group             |
| `title`       | _required_             | The section heading                              |
| `description` | `''`                   | Shown at the top of the section                  |
| `panel`       | `null`                 | The id of the panel the section sits in          |
| `priority`    | `160`                  | Where in the Customizer. Site Identity is `20`   |
| `capability`  | `'edit_theme_options'` | What a user needs to see the section and save it |

## Migrating from an ACF options page

Declare each ACF field as a `Setting` with the control that matches it. ACF stores an options field as the option `<post id>_<name>` (`options_phone` by default), so give the setting that name, or copy the values with `wp option update`. Fields with no native control (repeaters, flexible content, images) are the signal to keep [the ACF package](/guide/acf-options-pages) for that page.

## Related

- [#[AsSettingsPage]](/api/as-settings-page)
- [#[AsCustomizerSection]](/api/as-customizer-section)
- [#[AsPostMeta]](/api/as-post-meta) — for values that belong to a post rather than to the site
- [ACF Options Pages](/guide/acf-options-pages)
