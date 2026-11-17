# SVG Icons

WordPress 7.1 has an Icons API: a registry of SVG icons in collections, and `wp_get_icon()` to print one. Føhn registers the theme's icons with it, and adds the `icon()` Twig function to print them.

## Adding an icon

Put an SVG file in `assets/icons/` in the theme:

```
theme/
└── assets/
    └── icons/
        ├── arrow-left.svg
        └── arrow-right.svg
```

Nothing else is necessary. On `init`, each file becomes an icon:

| Item       | Value                                                                            |
| ---------- | -------------------------------------------------------------------------------- |
| Collection | The theme's directory name, for example `starter-theme`                          |
| Icon name  | `<collection>/<file name without .svg>`, for example `starter-theme/arrow-right` |
| Label      | The file name with spaces, for example "Arrow right"                             |

The collection label is the theme's `Theme Name`. A child theme with its own `assets/icons/` uses that directory, not the parent's.

Rules for the files:

- Only the files directly in `assets/icons/` are registered. Files in subdirectories are not, because an icon name cannot contain a `/`.
- A file name must contain only lowercase letters, digits, `-` and `_`, and start and end with a letter or a digit. WordPress refuses other names. Føhn skips such a file, and gives a notice when `FoehnConfig::debug` is on.
- WordPress reads a file only when the icon is printed for the first time. Registering costs one directory listing per request.

## Printing an icon

```twig
{{ icon('arrow-right') }}
{{ icon('arrow-right', { size: 16, class: 'button__icon' }) }}
{{ icon('arrow-right', { size: null }) }}
{{ icon('core/search', { label: 'Search' }) }}
```

A name without a `/` is an icon of the theme collection. A name with a `/` is used as it is, so `core/search` prints a WordPress core icon.

| Option  | Default | Effect                                                                                                           |
| ------- | ------- | ---------------------------------------------------------------------------------------------------------------- |
| `size`  | `24`    | Sets `width` and `height`, in pixels. `null` keeps the dimensions of the file.                                   |
| `class` | `''`    | Adds classes to the `<svg>`.                                                                                     |
| `label` | `''`    | Adds `role="img"` and `aria-label`. Without a label, the icon gets `aria-hidden="true"` and `focusable="false"`. |

An icon that is not registered prints nothing. The function calls `wp_get_icon()` directly, so the same icon prints the same markup from PHP:

```php
echo wp_get_icon(\Studiometa\Foehn\Icons\ThemeIcons::qualify('arrow-right'), ['size' => 16]);
```

## What WordPress removes from the SVG

WordPress sanitizes the file with `wp_kses()` and keeps only these elements and attributes:

| Element     | Attributes kept                                                                    |
| ----------- | ---------------------------------------------------------------------------------- |
| `<svg>`     | `class`, `xmlns`, `width`, `height`, `viewBox`, `aria-hidden`, `role`, `focusable` |
| `<path>`    | `d`, `fill`, `fill-rule`, `transform`                                              |
| `<polygon>` | `points`, `fill`, `fill-rule`, `transform`, `focusable`                            |

All other elements and attributes are removed. Thus:

- `<circle>`, `<rect>`, `<line>` and `<use>` are removed. Convert the shapes to paths before you add the file.
- The `<g>` tags are removed, but the shapes in them stay. A `transform` or a `fill` on a `<g>` is lost.
- `stroke` is removed everywhere. A stroke icon, for example from Lucide or Feather, prints nothing visible. Convert the strokes to fills first.
- `fill` on the `<svg>` element is removed, so `<svg fill="currentColor">` does not make the icon use the text color.

### Use the text color

By default, an SVG shape is black. To make an icon use the text color, do one of these:

Put `fill="currentColor"` on each `<path>` or `<polygon>`. WordPress keeps it:

```xml
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
  <path fill="currentColor" d="M13.06 5.47 12 6.53l4.66 4.72H4v1.5h12.66L12 17.47l1.06 1.06L19.56 12z"/>
</svg>
```

Or set the fill in CSS, and keep the files without `fill`:

```css
.icon {
  fill: currentColor;
}
```

```twig
{{ icon('arrow-right', { class: 'icon' }) }}
```

## WordPress before 7.1

The Icons API arrived in WordPress 7.1. On an older site, Føhn registers no icons and `icon()` prints an empty string. When `FoehnConfig::debug` is on, a notice tells you why.

## See also

- [Registering and rendering SVG icons in WordPress 7.1](https://make.wordpress.org/core/2026/07/24/registering-and-rendering-svg-icons-in-wordpress-7-1/), the dev note
- [Theme Conventions](/guide/theme-conventions)
- [Twig Extensions](/guide/twig-extensions)
