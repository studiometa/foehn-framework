### Added

- Føhn turns on its Polylang adapter when Polylang is active and has a language. The rest of the framework asks the new `Contracts\Multilingual` contract (`defaultLanguage()`, `currentLanguage()`, `adminLanguage()`, `languages()`, `translations()`, `url()`), which `Studiometa\Foehn\multilingual()` gives, or `null` on a site with one language. Free Polylang is enough
- The `languages()` Twig function and PHP helper give the language switcher of the current page: for each language, its code, its name, the URL of the translation (or the home page of the language when there is none), whether it is current and whether the page is translated in it
- With the page cache on, a saved post also purges its translations and the home page of each of their languages
- `PageCacheConfig::$hosts` lists the other hosts the site answers on, such as the language subdomains of Polylang, so the page cache stores and serves their pages. A request for any other host is still a bypass
- `Setting::string(translatable: true)` stores one value for each language on a settings page: `{name}` for the default language and `{name}_{lang}` for the others. The page edits the language of the admin bar, and `Settings::get()` reads the language of the request, with the value of the default language when the translation is empty
