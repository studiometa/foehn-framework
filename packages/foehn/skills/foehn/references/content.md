# Content model

Post types, taxonomies, post meta, model queries and settings pages.

Docs: [Post types](https://studiometa.github.io/foehn-framework/guide/post-types.html), [Taxonomies](https://studiometa.github.io/foehn-framework/guide/taxonomies.html), [Querying posts](https://studiometa.github.io/foehn-framework/guide/querying-posts.html), [Settings pages](https://studiometa.github.io/foehn-framework/guide/settings-pages.html).

## `#[AsPostType]`

Put it on a class that extends `Studiometa\Foehn\Models\Post` (itself a `Timber\Post`). Arguments, with defaults:

| Argument             | Default                                                                        |
| -------------------- | ------------------------------------------------------------------------------ |
| `name`               | required, the post type slug                                                   |
| `singular`, `plural` | `null`; labels are generated from them                                         |
| `public`             | `true`                                                                         |
| `hasArchive`         | `false`                                                                        |
| `showInRest`         | `true` (the block editor needs it)                                             |
| `menuIcon`           | `null` (a dashicon name such as `'dashicons-cart'`)                            |
| `supports`           | `['title', 'editor', 'thumbnail']`                                             |
| `taxonomies`         | `[]`                                                                           |
| `rewriteSlug`        | `null`                                                                         |
| `hierarchical`       | `false`                                                                        |
| `menuPosition`       | `null`                                                                         |
| `labels`             | `[]`, merged over the generated labels                                         |
| `rewrite`            | `null`; an array for the full `rewrite` argument, `false` to turn rewrites off |

For anything else, implement `Studiometa\Foehn\Contracts\ConfiguresPostType`. The `PostTypeBuilder` has `setLabels`, `setCustomLabels`, `setPublic`, `setHasArchive`, `setShowInRest`, `setMenuIcon`, `setSupports`, `setTaxonomies`, `setRewriteSlug`, `setHierarchical`, `setMenuPosition`, `setRewrite` and `setExtraArgs` (any other `register_post_type()` argument).

```php
public static function configurePostType(PostTypeBuilder $builder): PostTypeBuilder
{
    return $builder
        ->setRewrite(['slug' => 'projects', 'with_front' => false])
        ->setMenuPosition(5)
        ->setExtraArgs(['capability_type' => 'project', 'map_meta_cap' => true]);
}
```

The class becomes Timber's class for that post type. Methods you add are callable in Twig: `{{ post.client }}`, `{% for fact in post.facts %}`.

Built-in models: `Studiometa\Foehn\Models\Post` (mapped to `post`) and `Studiometa\Foehn\Models\Page` (mapped to `page`). To map another existing type, such as an attachment or a plugin's type, put `#[AsTimberModel('product')]` on a class that extends `Timber\Post`. It registers the class map only, not a post type. It also works on a `Timber\Term` subclass, for an existing taxonomy such as `category`.

## Queries

`Studiometa\Foehn\Models\Post` uses the `QueriesPostType` trait:

```php
Project::all();                 // every published project (limit: -1)
Project::all(limit: 10);
Project::find(42);              // ?Project
Project::first(['meta_key' => 'featured', 'meta_value' => '1']);
Project::count();
Project::exists();

Project::query()
    ->limit(6)
    ->page(2)
    ->orderBy('date', 'DESC')
    ->orderByMeta('year', 'DESC', numeric: true)
    ->whereTax('project_category', ['osaka', 'winter'])          // field: 'slug', operator: 'IN'
    ->whereMeta('client', 'Acme')                                  // compare: '='
    ->exclude($current->ID)
    ->get();                                                       // list of Project
```

Other builder methods: `offset`, `status`, `include`, `taxRelation`, `metaRelation`, `search`, `byAuthor`, `dateQuery`, `parent`, `parentIn`, `parentNotIn`, `set` (any `WP_Query` argument), `merge`, `first`, `count`, `exists`, `getParameters` (for debugging). A `null`, `''` or `[]` value to `whereTax()` adds no clause, so you can pass a request value directly.

Inside controllers you can also use `Timber::get_posts([...])`. The demo does this for its front page.

## `#[AsTaxonomy]`

Put it on a class that extends `Timber\Term`.

```php
use Studiometa\Foehn\Attributes\AsTaxonomy;
use Studiometa\Foehn\Contracts\ConfiguresTaxonomy;
use Studiometa\Foehn\PostTypes\TaxonomyBuilder;
use Timber\Term;

#[AsTaxonomy(
    name: 'project_category',
    singular: 'Series',
    plural: 'Series',
    postTypes: ['project'],
    hierarchical: true,
    showAdminColumn: true,
)]
final class ProjectCategory extends Term implements ConfiguresTaxonomy
{
    public static function configureTaxonomy(TaxonomyBuilder $builder): TaxonomyBuilder
    {
        return $builder->setRewrite(['slug' => 'projects/series', 'with_front' => false]);
    }
}
```

Arguments: `name`, `postTypes` (`[]`), `singular`, `plural`, `public` (`true`), `hierarchical` (`false`), `showInRest` (`true`), `showAdminColumn` (`true`), `rewriteSlug`, `labels`, `rewrite`.

## `#[AsPostMeta]`

Repeatable, on the model class. It calls `register_meta()` for that post type only (the subtype comes from the `#[AsPostType]` on the same class).

```php
#[AsPostType(name: 'product', singular: 'Product', plural: 'Products')]
#[AsPostMeta(key: 'price', type: 'number', description: 'Price in euros')]
#[AsPostMeta(key: 'sku', showInRest: false, sanitize: 'sanitizeSku')]
#[AsPostMeta(key: 'gallery', type: 'integer', single: false)]
final class Product extends Post
{
    public static function sanitizeSku(mixed $value): string
    {
        return strtoupper((string) $value);
    }
}
```

Arguments: `key`, `type` (`string`, `boolean`, `integer`, `number`, `array`, `object`), `single` (`true`), `showInRest` (`true`), `description`, `default`, `objectType` (`post`, `term`, `user`, `comment`), `objectSubtype`, `capability` (`edit_posts`), `sanitize`, `schema`.

Rules:

- `sanitize` is the name of a public static method on the class. Never a closure: discovery items go into the cache through `var_export()`, so a closure fails only where the cache is on.
- `array` and `object` types need an explicit `schema`. Føhn refuses the declaration without one.
- A key that is `single` and in REST binds through core's `core/post-meta` source. You do not need a custom block binding to show a stored value.

```html
<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"price"}}}}} -->
<p></p>
<!-- /wp:paragraph -->
```

## Settings pages

An admin screen on the WordPress Settings API, for a few site-wide values. `settings()` declares what is stored. A Twig template is the form body. Føhn adds the page shell: heading, `<form>`, `settings_fields()` (the nonce), and the submit button.

```php
namespace App\Settings;

use Studiometa\Foehn\Attributes\AsSettingsPage;
use Studiometa\Foehn\Contracts\SettingsPageInterface;
use Studiometa\Foehn\Settings\Setting;

#[AsSettingsPage(
    slug: 'theme-settings',
    title: 'Theme settings',
    parent: 'themes.php',               // default 'options-general.php'; null = top-level menu
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
            'theme_posts_per_archive' => Setting::int(default: 12),
        ];
    }
}
```

```twig
{# templates/settings/theme-settings.twig — receives `settings` and `page` #}
<table class="form-table" role="presentation">
  <tr>
    <th scope="row"><label for="theme_contact_email">Contact email</label></th>
    <td><input type="email" id="theme_contact_email" name="theme_contact_email" value="{{ settings.theme_contact_email }}" /></td>
  </tr>
</table>
```

- Factories: `Setting::string()`, `Setting::bool()`, `Setting::int()`, `Setting::number()`. Each takes `default`, `sanitize` (a function name or a public static method on the page class), `showInRest` (`false`) and `description`.
- Other attribute arguments: `menuTitle`, `capability` (`manage_options`), `icon`, `position`.
- A page with no template must implement `Studiometa\Foehn\Contracts\SettingsFormInterface` and return the form HTML from `form()`. With neither, discovery refuses the page.
- Read values with `Studiometa\Foehn\Settings\Settings::get('theme_show_banner')`, not `get_option()`. `Settings::get()` returns the declared default before the first save, and casts to the declared type (a checkbox is `true` or `false`, not `''` or `'1'`). Also `Settings::has()` and `Settings::all()`.
- Option names are global. Prefix them.
- Put settings in Twig through a context provider:

```php
return $context->with('show_banner', Settings::get('theme_show_banner'));
```

For repeaters, flexible content or conditional fields, use an ACF options page (the `foehn-acf` skill).
