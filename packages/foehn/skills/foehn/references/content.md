# Content model

Post types, taxonomies, post meta, dates, model queries, sample content and menus with WP-CLI, and settings pages.

Docs: [Post types](https://studiometa.github.io/foehn-framework/guide/post-types.md), [Taxonomies](https://studiometa.github.io/foehn-framework/guide/taxonomies.md), [Querying posts](https://studiometa.github.io/foehn-framework/guide/querying-posts.md), [Settings pages](https://studiometa.github.io/foehn-framework/guide/settings-pages.md).

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

## Method names a model must not reuse

A model extends `Timber\Post`. Twig resolves `{{ post.date }}` to the method `date()`, and Timber's own code calls its methods. A method you add with the same name as a parent method replaces it everywhere. A `private` or `protected` one, or one with an incompatible signature, is a fatal error when PHP loads the class.

Public methods of `Studiometa\Foehn\Models\Post` with Timber 2.5:

`ancestors`, `author`, `authors`, `can_edit`, `categories`, `category`, `children`, `comment_count`, `comment_form`, `comments`, `content`, `convert`, `css_class`, `date`, `edit_link`, `excerpt`, `field_object`, `format`, `gallery`, `get_field`, `get_method_values`, `has_field`, `has_term`, `import`, `import_field`, `link`, `meta`, `modified_author`, `modified_date`, `modified_time`, `modified_timestamp`, `name`, `next`, `paged_content`, `pagination`, `parent`, `password_required`, `path`, `post_class`, `prev`, `preview`, `raw_meta`, `setup`, `tags`, `teardown`, `terms`, `thumbnail`, `thumbnail_id`, `time`, `timestamp`, `title`, `type`, `update`, `wp_object`; static: `build`, `query`, `find`, `all`, `first`, `count`, `exists`.

Protected: `get_info`, `fetch_meta`, `get_entity_name`, `get_object_type`, `get_post_preview_id`, `get_post_preview_object`, `get_revised_data_from_method`, `maybe_show_password_form`, `content_handle_no_teaser_block`; static `get_wp_link_page`, `is_previewing`. Public properties such as `ID`, `slug`, `post_type`, `post_date` and `menu_order` are reserved too.

The list changes with the Timber version. Print it for the installed version:

```bash
wp eval 'echo implode(" ", get_class_methods(\Studiometa\Foehn\Models\Post::class)), "\n";'
```

Give an accessor a name that is not in the list: a meta key `date` gets `eventDate()`, not `date()`. To read such a key without an accessor, use `post.meta('date')` in Twig. `make:post-type --meta` writes no accessor for a key whose method the parent has, and says so in its `notes`.

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

### Dates and an archive of upcoming items

Store a date as a `Y-m-d` string with `type: 'string'`. Meta queries compare it with `type => 'DATE'`, and the order of the strings is the order of the dates. A time goes in `Y-m-d H:i:s` with `type => 'DATETIME'`.

```php
#[AsPostType(name: 'event', singular: 'Event', plural: 'Events', hasArchive: true, rewriteSlug: 'events', supports: ['title', 'editor', 'thumbnail', 'custom-fields'])]
#[AsPostMeta(key: 'start_date', type: 'string', description: 'Start date, Y-m-d')]
final class Event extends Post
{
    public function startDate(): ?string
    {
        $value = $this->meta('start_date');

        return is_string($value) && $value !== '' ? $value : null;
    }
}
```

In Twig, `{% if post.startDate %}{{ post.startDate|date('j F Y') }}{% endif %}`. Timber's `date` filter prints the current date for `null`, so test the value first.

In a controller or provider, the next three events: `Event::query()->whereMeta('start_date', wp_date('Y-m-d'), '>=', 'DATE')->orderByMeta('start_date', 'ASC')->limit(3)->get()`. `wp_date()` gives today in the site time zone.

The post type archive is the main query, so change it in `pre_get_posts`. Set defaults only: keep the `meta_query` that is there, and keep `orderby`, `order` and `posts_per_page` when the visitor or `QueryFiltersHook` set them. Priority 20 runs after `QueryFiltersHook`, which empties a refused `posts_per_page`. The `custom-fields` support lets editors edit the date: see [Editing meta values](#editing-meta-values).

```php
#[AsAction('pre_get_posts', priority: 20)]
public function upcomingEvents(\WP_Query $query): void
{
    if (is_admin() || !$query->is_main_query() || !$query->is_post_type_archive('event')) {
        return;
    }

    // A named clause, joined with AND to the meta_query that is there:
    // added inside it, the clause would follow its relation, which can be OR.
    $metaQuery = [
        'relation' => 'AND',
        'upcoming' => [
            'key' => 'start_date',
            'value' => wp_date('Y-m-d'),
            'compare' => '>=',
            'type' => 'DATE',
        ],
    ];
    $existing = $query->get('meta_query');
    if (is_array($existing) && $existing !== []) {
        $metaQuery[] = $existing;
    }
    $query->set('meta_query', $metaQuery);

    if (!$query->get('orderby')) {
        $query->set('orderby', 'upcoming'); // the named clause
        $query->set('order', $query->get('order') ?: 'ASC'); // soonest first
    }

    if (!$query->get('posts_per_page')) {
        $query->set('posts_per_page', 12);
    }
}
```

With the page cache on, a cached archive keeps a past event until the page `ttl` ends or a content change purges the page.

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

### Editing meta values

Føhn registers the key. It adds no field, meta box or form to the admin. What an editor can use:

- **REST and the block editor**: WordPress puts `meta` in the REST response of a post type only when the type supports `custom-fields`. Add `'custom-fields'` to `supports`. Without it, the key is registered but the editor cannot read or save it.
- **Custom Fields panel**: with `custom-fields` support, the editor turns on Preferences > General > Advanced > Custom fields. The panel lists keys that do not start with `_`, as text.
- **Block bindings**: a paragraph, heading, button or image bound to the key with `core/post-meta` shows the value on the front end. With `custom-fields` support, the editor also shows the value in the block and saves an edit to the meta. A key starting with `_` or with `showInRest: false` does not bind.
- **ACF**: a field group (the `foehn-acf` skill) gives a real form: date picker, select, repeater. `#[AsPostMeta]` on the same key keeps the REST schema.
- **A custom form**: an `add_meta_box()` call in an `#[AsAction('add_meta_boxes')]` method and a `save_post` action. Føhn has no helper for this.

Scripts and agents write values with `wp post meta update <id> <key> <value>`, or `--meta_input` on `wp post create` (see [Sample content and menus](#sample-content-and-menus-with-wp-cli)). The `sanitize` method runs in both cases.

## Sample content and menus with WP-CLI

Read the project conventions first (`AGENTS.md`): a project can keep its sample content in a seed script, and an item created by hand is lost when the database is rebuilt.

```bash
wp post create --post_type=event --post_status=publish --post_title='Opening night' \
  --meta_input='{"start_date":"2026-12-04","venue":"Main hall"}' --porcelain   # prints the post ID
wp post meta update 42 start_date 2026-12-05
```

A menu location is not a menu. `#[AsMenu('header')]` declares the location `header`. Editors create menus and assign one to each location. The `wp menu item add-*` commands take a menu (name, slug or ID), not a location: `wp menu item add-custom header …` fails with `Error: Invalid menu.` when no menu is named `header`.

```bash
wp menu list --fields=term_id,slug,locations --format=json   # the menu assigned to each location
wp menu create 'Header' --porcelain                          # only when the location has no menu; prints the ID
wp menu location assign <menu> header
wp menu item add-post <menu> <post-id>
wp menu item add-term <menu> <taxonomy> <term-id>
wp menu item add-custom <menu> 'Events' "$(wp eval 'echo get_post_type_archive_link("event");')"
```

There is no `add-*` command for a post type archive: add its URL with `add-custom`. Twig reads the location: `menus.header.items`.

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
