# Settings Pages

An admin screen for the handful of values a theme needs: a contact address, a feature toggle, an API key. `#[AsSettingsPage]` puts it on the WordPress Settings API.

Named for the API it wraps. WordPress uses both words — `add_options_page()` registers the menu entry, while everything that does the work is `register_setting()`, `add_settings_section()` and `settings_fields()`.

## A page

```php
<?php
// app/Settings/ThemeSettings.php

namespace App\Settings;

use Studiometa\Foehn\Attributes\AsSettingsPage;
use Studiometa\Foehn\Contracts\SettingsPageInterface;
use Studiometa\Foehn\Settings\Setting;

#[AsSettingsPage(
    slug: 'theme-settings',
    title: 'Theme settings',
    parent: 'themes.php',
    template: 'settings/theme-settings',
)]
final readonly class ThemeSettings implements SettingsPageInterface
{
    /** @return array<string, Setting> */
    public static function settings(): array
    {
        return [
            'theme_contact_email' => Setting::string(sanitize: 'sanitize_email'),
            'theme_show_banner' => Setting::bool(default: false),
        ];
    }
}
```

```twig
{# templates/settings/theme-settings.twig #}
<table class="form-table" role="presentation">
  <tr>
    <th scope="row"><label for="theme_contact_email">Contact email</label></th>
    <td>
      <input
        type="email"
        id="theme_contact_email"
        name="theme_contact_email"
        value="{{ settings.theme_contact_email }}" />
    </td>
  </tr>

  <tr>
    <th scope="row">Banner</th>
    <td>
      <label>
        <input
          type="checkbox"
          name="theme_show_banner"
          value="1"
          {{ settings.theme_show_banner ? 'checked' : '' }} />
        Show the announcement banner
      </label>
    </td>
  </tr>
</table>
```

`settings()` says what is stored. The template says what the form looks like. That separation is the whole point, and the difference from an ACF options page, which declares both.

The form is Twig, like every other view in a Føhn theme, and the page class has no PHP of its own beyond the declaration. The template receives:

| Variable   | Contents                                                      |
| ---------- | ------------------------------------------------------------- |
| `settings` | The current value of each declared setting, typed as declared |
| `page`     | `slug` and `title`                                            |

`settings.theme_show_banner` is a boolean, not the empty string WordPress stores an unchecked box as. Each input's `name` is the option name.

### When the form needs more than the values

A page that has to build its form from something else — a list of post types, a value fetched from an API — implements `SettingsFormInterface` instead of naming a template:

```php
final readonly class ThemeSettings implements SettingsPageInterface, SettingsFormInterface
{
    public function __construct(private ViewEngineInterface $view) {}

    public function form(): string
    {
        return $this->view->render('settings/theme-settings', [
            'settings' => Settings::all(),
            'post_types' => get_post_types(['public' => true], 'objects'),
        ]);
    }
}
```

It returns the HTML rather than echoing it, like [`TemplateControllerInterface::handle()`](/api/template-controller-interface), so the page composes it however it likes — Twig with its own context, or anything else.

**A page needs one or the other.** Without a template and without `form()` there is nothing between the heading and the submit button, and discovery refuses the page rather than rendering an empty one.

## What the framework gives you, and what it does not

Føhn provides the menu entry, `register_setting()` for each declared setting with its type, default and sanitiser, the capability, and the page shell:

```
<div class="wrap">
  <h1>Theme settings</h1>
  settings_errors()
  <form action="options.php" method="post">
    settings_fields()        ← the nonce and option group, without which the save is rejected
    do_settings_sections()
    your template or form()  ← yours
    submit_button()
  </form>
</div>
```

`settings_fields()` is why the shell exists at all: a page that forgets it looks like it simply does not save, with no error anywhere. You cannot forget it, because you never write it.

**There is no field abstraction, and that is deliberate.** Text inputs and checkboxes are a day's work; repeaters, conditional logic, media pickers and layouts are ACF's actual product, and a `Field::text(...)` builder is the first step towards maintaining a field library nobody asked Føhn for. The body of the form is markup you write — in Twig, or one `@wordpress/components` island if a page earns it.

## Declaring a setting

| Factory             | Type      | Default sanitiser       |
| ------------------- | --------- | ----------------------- |
| `Setting::string()` | `string`  | `sanitize_text_field`   |
| `Setting::bool()`   | `boolean` | `rest_sanitize_boolean` |
| `Setting::int()`    | `integer` | `absint`                |
| `Setting::number()` | `number`  | `floatval`              |

Each takes `default`, `sanitize`, `showInRest` and `description`.

`sanitize` is a **function name**, or the name of a public static method on the page class — never a closure, because a discovery item reaches the cache through `var_export()`:

```php
'theme_ratio' => Setting::number(default: 1.5, sanitize: 'clampRatio'),

// …on the page class:
public static function clampRatio(mixed $value): float
{
    return min(2.0, max(0.5, (float) $value));
}
```

**`showInRest` is off by default**, unlike [`#[AsPostMeta]`](/api/as-post-meta). Settings are configuration and sometimes credentials, so exposure is opt-in. `description` only has an effect when it is on.

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

A settings page's own template gets them without asking, in `settings`. Elsewhere in Twig, read them through a [context provider](/guide/context-providers) rather than reaching for a global.

## Where the page appears

| `parent`                          | Result                                             |
| --------------------------------- | -------------------------------------------------- |
| `'options-general.php'` (default) | Under Settings                                     |
| `'themes.php'`                    | Under Appearance                                   |
| `'edit.php?post_type=book'`       | Under that post type's menu                        |
| `null`                            | Its own top-level menu, with `icon` and `position` |

`capability` defaults to `manage_options` and is what WordPress checks before showing the page at all.

## Migrating from an ACF options page

The values are the same options; only the editing screen changes. Declare each field as a `Setting`, and write the form once as a Twig template — that is the part `AcfOptionsPageInterface::fields()` was doing for you, and the reason it takes an afternoon rather than a minute. Fields ACF has no plain equivalent for (repeaters, flexible content) are the signal to keep [the ACF package](/guide/acf-options-pages) for that page.

## Related

- [#[AsSettingsPage]](/api/as-settings-page)
- [#[AsPostMeta]](/api/as-post-meta) — for values that belong to a post rather than to the site
- [ACF Options Pages](/guide/acf-options-pages)
