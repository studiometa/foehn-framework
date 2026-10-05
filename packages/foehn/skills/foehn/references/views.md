# Views

Template controllers, `TemplateContext`, context providers, DTOs, sections and the Twig functions Føhn adds.

Docs: [Template controllers](https://studiometa.github.io/foehn-framework/guide/template-controllers.md), [Context providers](https://studiometa.github.io/foehn-framework/guide/context-providers.md), [Arrayable DTOs](https://studiometa.github.io/foehn-framework/guide/arrayable-dtos.md), [Section rendering](https://studiometa.github.io/foehn-framework/guide/section-rendering.md), [Twig extensions](https://studiometa.github.io/foehn-framework/guide/twig-extensions.md).

## Request flow

1. On `template_include`, Føhn computes one template name for the request, in this order: `404`, `search`, `front-page`, `home`; for a singular request `single-{type}-{slug}`, `single-{type}`, `single`, `page-{slug}`, `page-{id}`, `page`, `attachment`, `singular`; for an archive `archive-{type}` (post type archive), `category-{slug}`, `category`, `tag-{slug}`, `tag`, `taxonomy-{tax}-{term}`, `taxonomy-{tax}`, `taxonomy`, `author`, `date`, `archive`; else `index`. The slug, id and term variants (`single-{type}-{slug}`, `single-{type}`, `page-{slug}`, `page-{id}`, `category-{slug}`, `tag-{slug}`, `taxonomy-{tax}-{term}`, `taxonomy-{tax}`) are used only when a controller declares them (exactly or through a wildcard); otherwise the next, more general name is used. The other names are used as they are, even when no controller declares them: a post type archive is always `archive-{type}`, so `archive` alone does not cover it (declare `archive-*`).
2. Føhn takes the controller that declares that exact name. If there is none, it takes the first controller whose wildcard (`single-*`) matches. Declare each exact name in one controller only.
3. Føhn builds a `TemplateContext` from `Timber::context()`. The controller's `handle()` returns HTML, which Føhn prints, or `null` to let WordPress load its own template.
4. Each `ViewEngineInterface::render()` call runs the context providers that match **the template name given to `render()`** (for example `pages/single`), in `priority` order, lowest first.

## `#[AsTemplateController]`

```php
#[AsTemplateController(['archive', 'archive-*', 'category', 'category-*', 'tag', 'tag-*', 'taxonomy', 'taxonomy-*', 'author', 'date'])]
final readonly class ArchiveController implements TemplateControllerInterface
{
    public function __construct(
        private ViewEngineInterface $view,
    ) {}

    public function handle(TemplateContext $context): string
    {
        if ($context->posts && method_exists($context->posts, 'pagination')) {
            $context = $context->with('pagination', $context->posts->pagination(['mid_size' => 2, 'end_size' => 1]));
        }

        $context = $context
            ->with('archive_title', get_the_archive_title())
            ->with('archive_description', get_the_archive_description());

        // get_queried_object()->name, not get_query_var('post_type'): the query var
        // can be an array, which interpolates to "Array".
        $templates = match (true) {
            is_post_type_archive() => ['pages/archive-' . (get_queried_object()->name ?? ''), 'pages/archive'],
            is_category() => ['pages/category', 'pages/archive'],
            is_tag() => ['pages/tag', 'pages/archive'],
            default => ['pages/archive'],
        };

        return $this->view->renderFirst($templates, $context);
    }
}
```

The starter's `ArchiveController` also declares `front-page` and `home`, and declares `tax-*` instead of `taxonomy` and `taxonomy-*`. If you add a `FrontPageController` for `front-page` or `home`, remove those names from the archive controller.

`archive` does not cover every archive. A custom taxonomy term page resolves to `taxonomy` (or `taxonomy-{tax}…` when declared), an author page to `author`, a date archive to `date`. The pattern `tax-*` matches none of these names. To render custom taxonomy, author and date archives through the archive controller, add `'taxonomy'`, `'taxonomy-*'`, `'author'` and `'date'` to its templates. A request with no matching controller falls back to WordPress and the theme's `index.php`.

Password-protected posts: test `post_password_required($post->ID)` and render a password template. The starter ships no `templates/pages/password.twig`: its `PageController` uses `renderFirst(['pages/password', 'pages/page'])`, but its `SingleController` calls `render('pages/password')`, which throws until you add that file.

## `TemplateContext`

`Studiometa\Foehn\Views\TemplateContext` is a `final readonly` class.

| Member                                                  | Returns                                                         |
| ------------------------------------------------------- | --------------------------------------------------------------- |
| `$context->post`                                        | `?Timber\Post`                                                  |
| `$context->posts`                                       | `?Timber\PostCollectionInterface`                               |
| `$context->site`                                        | `Timber\Site`                                                   |
| `$context->user`                                        | `?Timber\User` (`null` for a visitor, never `false`)            |
| `post(Project::class)`                                  | the post when it is an instance of that class, else `null`      |
| `posts(Project::class)`                                 | the collection when its posts are of that class, else `null`    |
| `get('key', $default)`, `has('key')`, `$context['key']` | other keys (from Timber, providers, `with()`)                   |
| `with('key', $value)`                                   | a new context with one key set                                  |
| `merge([...])` or `merge($dto)`                         | a new context with several keys                                 |
| `withDto($dto)`                                         | a new context with the DTO's keys, and the DTO kept for `dto()` |
| `dto(Foo::class)`                                       | the DTO added with `withDto()`, or `null`                       |
| `toArray()`                                             | the flat array Twig receives                                    |

The context is immutable. Always assign the result: `$context = $context->with(...)`.

## `ViewEngineInterface`

Inject `Studiometa\Foehn\Contracts\ViewEngineInterface` (implemented by `TimberViewEngine`).

- `render(string $template, array|object $context = [])`: the name has no `.twig` extension and is relative to `templates/`. It throws when the template does not exist.
- `renderFirst(array $templates, array|object $context = [])`: the first template that exists.
- `exists(string $template)`.
- `share(string $key, mixed $value)`: a value for every later render.

The context can be an array, a `TemplateContext` or an `Arrayable` DTO.

## `#[AsContextProvider]`

```php
#[AsContextProvider(['pages/single', 'pages/single-*'], priority: 20)]
final readonly class RelatedProjectsProvider implements ContextProviderInterface
{
    public function provide(TemplateContext $context): TemplateContext
    {
        $project = $context->post(Project::class);

        if ($project === null) {
            return $context;
        }

        return $context->with('related', Project::query()->limit(3)->exclude($project->ID)->get());
    }
}
```

- The patterns match the template name passed to `render()` or `renderFirst()`, not the WordPress hierarchy name. In the starter layout that is `pages/single`, `pages/archive`, `sections/posts`, `blocks/hero`, `settings/theme-settings`. `*` matches any characters, `/` included, so `'*'` runs for every render, blocks and sections too.
- A provider for `'single'` does not run when the controller renders `pages/single`. This is the most common reason a provider "does nothing".
- Use a context provider for data many templates share. Use a controller for data one page owns, and to choose the template.
- `menus` is filled by `#[AsMenu]`. Read `menus.header.items` in Twig.

## DTOs

A DTO is a `final readonly` class that implements `Studiometa\Foehn\Contracts\Arrayable` and uses `Studiometa\Foehn\Concerns\HasToArray`. `toArray()` gives its public properties with camelCase names converted to snake_case keys (`backgroundImage` becomes `background_image`). Nested `Arrayable` values are converted too.

```php
namespace App\Data;

use Studiometa\Foehn\Concerns\HasToArray;
use Studiometa\Foehn\Contracts\Arrayable;
use Studiometa\Foehn\Data\ImageData;
use Studiometa\Foehn\Data\LinkData;

final readonly class HeroContext implements Arrayable
{
    use HasToArray;

    public function __construct(
        public string $title,
        public ?string $subtitle = null,
        public ?ImageData $background = null,   // {{ background.src }}, {{ background.alt }}
        public ?LinkData $cta = null,           // {{ cta.url }}, {{ cta.title }}, {{ cta.target }}
        public string $height = 'medium',
    ) {}
}
```

Built-in DTOs in `Studiometa\Foehn\Data`:

- `ImageData(id, src, alt, width, height)`, and `ImageData::fromAttachmentId(?int $id, string $size = 'large')`, which returns `null` for a missing attachment.
- `LinkData(url, title, target)`.
- `SpacingData`.

`LinkData::fromAcf()` and `SpacingData::fromAcf()` read ACF field values. See the `foehn-acf` skill.

## Sections

A section is a named region of a normal page that Føhn can render alone. Declare it in the page template:

```twig
{# templates/pages/archive.twig #}
{{ foehn_section('posts', { posts, pagination }) }}
```

This renders `templates/sections/posts.twig` inside `<div id="foehn-section-posts" data-foehn-section="posts">`. The same URL with `?foehn_sections=posts` (up to five names, comma-separated) runs the same query, controller and page template, and returns only the selected wrappers as HTML.

- `foehn_section(name, context = {}, lazy: false)`: the explicit context is merged over the active Twig context. Pass loop values (`post`, `posts`, `pagination`) explicitly. Do not read WordPress loop globals such as `get_the_ID()` in a section.
- `foehn_section_url('posts')` or `foehn_section_url('list,count', url)`: the URL that returns those sections. Use it with the `Fetch` component of `studiometa/ui` in `data-option-src`, and keep the normal URL in `href` or `action`.
- `lazy: true` outputs a `LazyInclude` placeholder that loads the section after the page.
- Names: lowercase letters, numbers and single hyphens, 64 characters at most. Sections cannot be nested.
- The page cache stores section responses with the same rules as pages.

## Twig functions and filters

| Name                                                                                                                                                    | Source                    | Use                                                                              |
| ------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------- | -------------------------------------------------------------------------------- |
| `foehn_section()`, `foehn_section_url()`                                                                                                                | `SectionExtension`        | Sections                                                                         |
| `image_url(image, { w, h, fit, fm })`                                                                                                                   | `ImageExtension`          | Transformed image URL. Returns the source URL when no transformer is configured. |
| `html_classes()`, `html_styles()`, `html_attributes()`, `{% element %}`                                                                                 | `studiometa/twig-toolkit` | HTML attribute helpers                                                           |
| `wp_interactive`, `wp_context`, `wp_directive`, `wp_bind`, `wp_on`, `wp_class`, `wp_text`                                                               | `InteractivityExtension`  | Interactivity API directives                                                     |
| `wp_block_start`, `wp_block_end`, `wp_block`                                                                                                            | `BlockMarkupExtension`    | Block comment markup in patterns                                                 |
| `query_get`, `query_has`, `query_contains`, `query_all`, `query_url`, `query_url_without`, `query_url_toggle`, `query_url_clear`, `query_hidden_inputs` | `QueryExtension`          | Filter UIs from the URL query                                                    |
| `facet(taxonomy)`                                                                                                                                       | `FacetExtension`          | Filter options for a taxonomy (a list of `FacetOption`)                          |
| `video_embed`, `video_id`, `video_platform`, `video_is_supported` (functions and filters)                                                               | `VideoEmbedExtension`     | YouTube and Vimeo embed URLs                                                     |

Timber's own functions stay available: `function('wp_head')`, `post.thumbnail.src('large')`, `post.terms('category')`, and so on.

To add your own functions or filters, see `#[AsTwigExtension]` in [routes-and-commands.md](routes-and-commands.md).

## Template folders

The starter uses `layouts/`, `pages/`, `components/`, `sections/` and `blocks/` under `templates/`. Page templates extend `layouts/base.twig` and fill `{% block content %}`. To use a different root, return `new TimberConfig(templatesDir: ['views'])` from `app/timber.config.php`.
