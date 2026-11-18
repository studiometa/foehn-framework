# Polylang

[Polylang](https://wordpress.org/plugins/polylang/) is a multilingual plugin. Føhn does not require it. When a site activates it and adds at least one language, Føhn turns on its Polylang adapter. Free Polylang is enough: the adapter calls only the `pll_*` functions of its public API.

## The `Multilingual` contract

The rest of Føhn does not call Polylang. It asks the `Studiometa\Foehn\Contracts\Multilingual` contract, which one adapter for each plugin implements. Polylang implements it now, and WPML will implement it later. `Studiometa\Foehn\multilingual()` gives the adapter of the site, or `null` on a site with one language:

```php
use function Studiometa\Foehn\multilingual;

$multilingual = multilingual();

$multilingual?->defaultLanguage();          // 'en'
$multilingual?->currentLanguage();          // 'fr', the language of the request
$multilingual?->adminLanguage();            // 'fr', or null for "all languages" in the admin bar
$multilingual?->languages();                // ['en' => 'English', 'fr' => 'Français']
$multilingual?->translations($post);        // ['en' => 12, 'fr' => 13], published only
$multilingual?->url('fr', $post);           // the URL of the French translation
$multilingual?->url('fr');                  // the French home page
```

| Method              | Returns                                                                                                    |
| ------------------- | ---------------------------------------------------------------------------------------------------------- |
| `defaultLanguage()` | The code of the default language.                                                                          |
| `currentLanguage()` | The code of the language of the request. The default language before the plugin has found it.              |
| `adminLanguage()`   | The code of the language selected in the admin bar, or `null` when "all languages" is selected.            |
| `languages()`       | Every language, as code => name.                                                                           |
| `translations()`    | The translations of a `WP_Post` or a `WP_Term`, the object included, as code => id. Posts: published only. |
| `url()`             | The URL of the translation of an object in a language, or the home page of the language when it has none.  |

## What Føhn does automatically

| Feature         | Effect with Polylang                                                                    | Details                                         |
| --------------- | --------------------------------------------------------------------------------------- | ----------------------------------------------- |
| Twig            | `languages()` gives the language switcher of the current page.                          | [Language switcher](#language-switcher)         |
| Page cache      | A saved post also purges its translations and the home page of each of their languages. | [Page cache](#page-cache)                       |
| Settings pages  | A setting declared `translatable` stores one value for each language.                   | [Translatable settings](#translatable-settings) |
| Database export | Nothing to do: Polylang has no tables of its own.                                       | [Database export](#database-export)             |

## Language switcher

`languages()` gives one item for each language, in the order of Polylang. It exists in Twig and in PHP (`Studiometa\Foehn\languages()`). The theme writes its own markup:

```twig
{% set items = languages() %}
{% if items %}
    <ul>
        {% for item in items %}
            <li>
                <a href="{{ item.url }}" hreflang="{{ item.code }}" lang="{{ item.code }}"{% if item.current %} aria-current="true"{% endif %}>
                    {{ item.name }}
                </a>
            </li>
        {% endfor %}
    </ul>
{% endif %}
```

| Key          | Value                                                                             |
| ------------ | --------------------------------------------------------------------------------- |
| `code`       | The language code, such as `fr`.                                                  |
| `name`       | The language name, such as `Français`.                                            |
| `url`        | The URL of the translation of the current page, or the home page of the language. |
| `current`    | Whether it is the language of the request.                                        |
| `translated` | Whether the current page has a published translation in the language.             |

The current page is the queried post or term. On another page (an archive, a search), each language links to its home page, and `translated` is true only on the front page. The list is empty on a site with one language.

## Page cache

The language switcher of a translation links to the post, so a saved post also purges:

- the page of each published translation;
- the home page of the language of each translation.

The three URL modes of Polylang work with the [page cache](/guide/page-cache):

| Polylang URL mode                         | What the project sets                                                                                             |
| ----------------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| The language in a folder (`/fr/`)         | Nothing. Each language has its own paths.                                                                         |
| A subdomain or a domain for each language | `PageCacheConfig::$hosts`: the other hosts, such as `['fr.example.com']`. A request for another host is a bypass. |
| The `?lang=` query argument               | `cacheQueryArgs: ['lang' => ['en', 'fr']]`, so each language has its own file.                                    |

```php
return new PageCacheConfig(
    enabled: true,
    hosts: ['fr.example.com', 'de.example.com'],
);
```

The warm-up reads one sitemap (`warmSitemap`, or the sitemap of Rank Math). Make sure that it lists the pages of every language.

## Translatable settings

A [settings page](/guide/settings-pages) setting declared `translatable` stores one value for each language, as ACF and ACFML do for options pages. Other settings keep one value for all languages.

```php
public static function settings(): array
{
    return [
        'theme_tagline' => Setting::string(label: 'Tagline', translatable: true),
        'theme_phone' => Setting::string(label: 'Phone', control: 'tel'),
    ];
}
```

- **Storage.** `theme_tagline` holds the value of the default language, and `theme_tagline_fr` the French value.
- **Editing.** The settings page edits the language selected in the admin bar. With "all languages" selected, it edits the default language and shows a notice that asks to select a language.
- **Reading.** `Settings::get('theme_tagline')` reads the language of the request. An empty translation reads the value of the default language.

Only `Setting::string()` takes `translatable`. A Customizer section ignores it. Without Polylang, a translatable setting has one value, `theme_tagline`.

## Database export

Polylang keeps the languages and the links between translations in taxonomies (`language`, `term_language`, `post_translations`, `term_translations`) and its settings in the `polylang` option. [`wp foehn db:export`](/guide/database-export) keeps the core tables, so no rule is necessary.
