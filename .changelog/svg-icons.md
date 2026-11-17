### Added

- Each SVG file in the theme's `assets/icons/` is registered with the WordPress 7.1 Icons API, as `<theme directory>/<file name>`. The `icon()` Twig function prints a registered icon with `wp_get_icon()`, and a name without a collection is an icon of the theme: `{{ icon('arrow-right', { size: 16 }) }}`. Before WordPress 7.1, no icon is registered and `icon()` prints nothing
- The starter's pagination shows the `arrow-left` and `arrow-right` icons of its `assets/icons/`
