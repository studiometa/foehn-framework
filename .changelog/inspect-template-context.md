### Added

- `wp foehn inspect settings` lists each `#[AsSettingsPage]` with the options it stores: option name, type, default and sanitizer. The sanitizer runs under WP-CLI too, so `wp option update` saves a value as the admin form does
- The `blocks` section of `wp foehn inspect` gives each block attribute with its type, default and allowed values
- The `templates` section of `wp foehn inspect` gives, for each WordPress template a controller claims, the controller that renders it and the ones it overrides. A new `views` section gives, for each Twig file of the theme, the context providers that match it in the order they run. Both list the context keys known without rendering: the keys Føhn and Timber always set, and the keys a controller or provider sets with a literal `with()` call. See [Template context](https://studiometa.github.io/foehn-framework/guide/cli-commands#template-context)

### Fixed

- When several `#[AsTemplateController]` classes claim the same template, the one with the lowest `priority` renders it, as the attribute documents; on a tie, the one discovered last. Wildcard patterns are tried in `priority` order. Before, `priority` was ignored and the controller discovered last always won
- The context provider guide and API pages said that providers match WordPress template names such as `single-product`. They match the view name passed to `render()`, such as `pages/single-product`
