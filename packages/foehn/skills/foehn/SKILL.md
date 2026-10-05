---
name: foehn
description: 'Build WordPress themes with studiometa/foehn, the attribute-based framework on Tempest discovery and Timber/Twig. Load it when code in a theme made from studiometa/foehn-starter uses Studiometa\Foehn classes or attributes such as #[AsAction], #[AsFilter], #[AsPostType], #[AsTaxonomy], #[AsPostMeta], #[AsBlock], #[AsBlockPattern], #[AsBlockBinding], #[AsTemplateController], #[AsContextProvider], #[AsMenu], #[AsImageSize], #[AsSettingsPage], #[AsRewriteRule], #[AsRestRoute], #[AsShortcode], #[AsCliCommand], #[AsJob], #[AsCron] or #[AsTwigExtension]; TemplateContext, ViewEngineInterface, BlockInterface, FoehnConfig, PageCacheConfig, ViteManifest or foehn_section(); or the wp foehn make:*, discovery:*, cache:*, verify and salts:generate commands.'
---

# Føhn

Føhn is a WordPress framework for classic (PHP and Twig) themes. You declare WordPress features as PHP attributes on classes. Føhn finds the classes, builds them through a dependency-injection container, and registers them with WordPress at the correct moment. Templates are Twig, rendered through Timber.

ACF blocks, ACF field groups and ACF options pages are not in this package. They are in `studiometa/foehn-acf`, with its own `foehn-acf` skill. The Vite build is in `@studiometa/foehn-vite-plugin`, with the `foehn-vite-plugin` skill.

Full documentation: https://studiometa.github.io/foehn-framework/

## Mental model

- **Attribute**: a `#[As…]` class in `Studiometa\Foehn\Attributes`. Its constructor signature is the complete declaration: named arguments and their defaults. Read the attribute class when you are not sure of an argument.
- **Discovery**: Føhn scans every class under the project's PSR-4 namespaces (from Composer, `App\` → `theme/app/` in the starter) and every installed package that opts in. The directory name does not matter. The attribute and, for some attributes, an interface matter.
- **Discovery phase**: each discovery applies at one WordPress moment: `early` (`after_setup_theme`), `main` (`init`) or `late` (`wp_loaded`). You do not choose it. Do not wrap registrations in your own `init` hook.
- **Container**: every discovered class is built by the Tempest container. Ask for services with constructor parameters (`ViewEngineInterface`, `CacheInterface`, your own services). Outside a discovered class, use `Studiometa\Foehn\app(Foo::class)` or `Kernel::get(Foo::class)`.
- **Config file**: a `*.config.php` file in `theme/app/` that returns a config object (`FoehnConfig`, `PageCacheConfig`, `TimberConfig`, `RestConfig`, `QueryFiltersConfig`). A file named for an environment (`foehn.production.config.php`, `page-cache.local.config.php`) is read only in that environment and replaces the plain file. A config file replaces the object. It does not merge.
- **Views**: a template controller renders a page from a `TemplateContext`. Context providers add data to the context. Blocks, patterns, sections and settings pages also render Twig through `ViewEngineInterface`.

## Theme layout (starter)

```
theme/
├── functions.php          # Kernel::boot(__DIR__ . '/app');
├── app/                   # namespace App\ (PSR-4)
│   ├── foehn.config.php   # discovery cache + opt-in framework hooks
│   ├── page-cache.config.php
│   ├── Controllers/       # #[AsTemplateController]
│   ├── ContextProviders/  # #[AsContextProvider]
│   ├── Hooks/             # #[AsAction] / #[AsFilter]
│   ├── Menus/             # #[AsMenu]
│   └── …                  # not in the starter: Models/ (#[AsPostType]), Taxonomies/, Blocks/, Settings/, Routes/… as in the demo
├── templates/             # Twig root (TimberConfig::templatesDir defaults to ['templates'])
│   ├── layouts/base.twig
│   ├── pages/             # one per controller target: single, archive, page, search, 404
│   ├── components/
│   ├── sections/          # foehn_section() targets
│   └── blocks/            # one per #[AsBlock]
└── assets/                # css/app.css, js/app.js, css/blocks/*.css, js/blocks/*.js
```

Follow these folder names for new code. They are conventions, not requirements.

## Hooks

```php
namespace App\Hooks;

use Studiometa\Foehn\Attributes\AsAction;
use Studiometa\Foehn\Attributes\AsFilter;

final class ThemeHooks
{
    #[AsAction('after_setup_theme')]
    public function setupTheme(): void
    {
        add_theme_support('post-thumbnails');
        add_theme_support('title-tag');
    }

    // priority defaults to 10, acceptedArgs to 1. Both attributes are repeatable.
    #[AsAction('pre_get_posts', priority: 20)]
    public function orderProjects(\WP_Query $query): void
    {
        if (is_admin() || !$query->is_main_query()) {
            return;
        }
        // …
    }

    #[AsFilter('excerpt_length')]
    public function excerptLength(): int
    {
        return 30;
    }
}
```

Set `acceptedArgs` when the method needs more than the first hook argument: `#[AsAction('save_post', priority: 20, acceptedArgs: 3)]`.

## Post types, taxonomies and meta

```php
namespace App\Models;

use Studiometa\Foehn\Attributes\AsPostMeta;
use Studiometa\Foehn\Attributes\AsPostType;
use Studiometa\Foehn\Contracts\ConfiguresPostType;
use Studiometa\Foehn\Models\Post;
use Studiometa\Foehn\PostTypes\PostTypeBuilder;

#[AsPostType(
    name: 'project',
    singular: 'Project',
    plural: 'Projects',
    hasArchive: true,
    menuIcon: 'dashicons-camera-alt',
    supports: ['title', 'editor', 'thumbnail', 'excerpt'],
    taxonomies: ['project_category'],
)]
#[AsPostMeta(key: 'client', type: 'string', description: 'Who commissioned the series')]
#[AsPostMeta(key: 'year', type: 'integer')]
final class Project extends Post implements ConfiguresPostType
{
    // Optional: anything the attribute cannot express.
    public static function configurePostType(PostTypeBuilder $builder): PostTypeBuilder
    {
        return $builder->setRewrite(['slug' => 'projects', 'with_front' => false]);
    }

    public function client(): ?string
    {
        $client = $this->meta('client');

        return is_string($client) && $client !== '' ? $client : null;
    }
}
```

- Extend `Studiometa\Foehn\Models\Post`. Føhn registers the class in Timber's class map, so Twig receives `Project` objects, and the model gets query methods: `Project::query()->limit(6)->whereTax('project_category', 'osaka')->get()`, `Project::find(42)`, `Project::all()`, `Project::first()`, `Project::count()`, `Project::exists()`.
- `#[AsPostMeta]` registers a meta key with a REST schema. The key then shows in the block editor and binds through core's `core/post-meta` source with no PHP.
- Taxonomies: `#[AsTaxonomy(name: 'project_category', postTypes: ['project'], singular: 'Series', plural: 'Series', hierarchical: true)]` on a class that extends `Timber\Term`. Implement `ConfiguresTaxonomy` for the `TaxonomyBuilder`.

Details: [references/content.md](references/content.md).

## Template controllers

A template controller takes over rendering for WordPress template-hierarchy names. Wildcards are allowed. The starter ships controllers for `single`, `archive`, `page`, `search` and `404`.

```php
namespace App\Controllers;

use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Contracts\TemplateControllerInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Studiometa\Foehn\Views\TemplateContext;

#[AsTemplateController(['single', 'single-*'])]
final readonly class SingleController implements TemplateControllerInterface
{
    public function __construct(
        private ViewEngineInterface $view,
    ) {}

    public function handle(TemplateContext $context): ?string
    {
        $post = $context->post;

        // renderFirst: the first template that exists wins, so a post type
        // with no template of its own falls back instead of throwing.
        return $this->view->renderFirst([
            "pages/single-{$post?->post_type}",
            'pages/single',
        ], $context->with('related', []));
    }
}
```

- `TemplateContext` is immutable. `with()`, `merge()` and `withDto()` return a new instance. Typed properties: `post`, `posts`, `site`, `user`. Read other keys with `get()`.
- Return `null` to let WordPress continue with its own template.
- `render()` throws when the template does not exist. Use `renderFirst()` with a fallback list.

## Context providers

```php
namespace App\ContextProviders;

use Studiometa\Foehn\Attributes\AsContextProvider;
use Studiometa\Foehn\Contracts\ContextProviderInterface;
use Studiometa\Foehn\Views\TemplateContext;

// Matches the name given to render(): '*', 'pages/single', 'pages/single-*', 'sections/posts'. priority: 10.
#[AsContextProvider('*')]
final class GlobalContextProvider implements ContextProviderInterface
{
    public function provide(TemplateContext $context): TemplateContext
    {
        return $context->with('current_year', date('Y'));
    }
}
```

`site`, `user`, `post` and `posts` come from Timber. `menus.<location>` comes from `#[AsMenu]`. Do not add them again.

Views, sections, DTOs and Twig helpers: [references/views.md](references/views.md).

## Native blocks

Every `#[AsBlock]` is a dynamic, server-rendered block. Føhn derives the editor sidebar from `attributes()`, so there is no block JavaScript and no build step.

```php
namespace App\Blocks;

use Studiometa\Foehn\Attributes\AsBlock;
use Studiometa\Foehn\Contracts\BlockInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Studiometa\Foehn\Data\ImageData;
use WP_Block;

#[AsBlock(name: 'theme/callout', title: 'Callout', category: 'widgets', icon: 'megaphone')]
final readonly class CalloutBlock implements BlockInterface
{
    public function __construct(
        private ViewEngineInterface $view,
    ) {}

    public static function attributes(): array
    {
        return [
            'title' => ['type' => 'string', 'default' => ''],                       // text field
            'tone' => ['type' => 'string', 'default' => 'info', 'options' => ['info' => 'Info', 'warning' => 'Warning']], // select
            'iconId' => ['type' => 'integer', 'control' => 'image', 'label' => 'Icon'], // media picker
            'dismissible' => ['type' => 'boolean', 'default' => false],             // toggle
        ];
    }

    public function compose(array $attributes, string $content, WP_Block $block): array
    {
        return [
            'title' => $attributes['title'] ?? '',
            'tone' => $attributes['tone'] ?? 'info',
            'icon' => ImageData::fromAttachmentId($attributes['iconId'] ?? null),
            'dismissible' => $attributes['dismissible'] ?? false,
        ];
    }

    public function render(array $attributes, string $content, WP_Block $block): string
    {
        return $this->view->render('blocks/callout', $this->compose($attributes, $content, $block));
    }
}
```

- Template: `templates/blocks/callout.twig`. Optional assets by name: `assets/css/blocks/callout.css` (front end and editor) and `assets/js/blocks/callout.js` (front end, as a script module).
- Prose goes in inner blocks with core blocks. Set `allowedBlocks`, `innerBlocksTemplate` or `innerBlocksTemplateLock` to make a container. The inner markup reaches Twig as `content` (print it with `|raw`).
- `compose()` can return an `Arrayable` DTO instead of an array.

Controls, containers, interactive blocks, patterns, bindings and categories: [references/blocks.md](references/blocks.md).

## Small declarations

```php
#[AsMenu('header', 'Primary menu')]
final class HeaderMenu {}            // Twig: menus.header

#[AsImageSize(width: 800, height: 600, crop: true)]
final class CardImageSize {}         // size name "card", derived from the class name; or name: 'card'
```

Settings pages (`#[AsSettingsPage]` + `SettingsPageInterface` + `Setting::string()/bool()/int()/number()`, read with `Settings::get()`), rewrite rules (`#[AsRewriteRule]` + `RewriteHandlerInterface`), REST routes (`#[AsRestRoute]`), shortcodes (`#[AsShortcode]`), WP-CLI commands (`#[AsCliCommand]` + `CliCommandInterface`), background jobs (`#[AsJob]`, `dispatch()`) and recurring jobs (`#[AsCron]`): [references/content.md](references/content.md) and [references/routes-and-commands.md](references/routes-and-commands.md).

## Configuration

`theme/app/foehn.config.php` in the starter:

```php
use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Hooks\Cleanup\CleanHeadTags;
use Studiometa\Foehn\Hooks\Cleanup\DisableEmoji;
use Studiometa\Foehn\Hooks\Security\DisableXmlRpc;
use Tempest\Discovery\DiscoveryCacheStrategy;

return new FoehnConfig(discoveryCacheStrategy: DiscoveryCacheStrategy::FULL, hooks: [
    CleanHeadTags::class,
    DisableEmoji::class,
    DisableXmlRpc::class,
]);
```

The framework's own hook classes (`Hooks\Cleanup\*`, `Hooks\Security\*`, `QueryFiltersHook`, `YouTubeNoCookieHooks`, `StudiometaUi`, `ImageCacheHooks`, …) do nothing until `hooks` names them. Config classes, opt-in hooks, the page cache, images and verification: [references/operations.md](references/operations.md).

## Assets

```php
use Studiometa\Foehn\Assets\ViteManifest;

#[AsAction('wp_enqueue_scripts')]
public function enqueue(): void
{
    ViteManifest::fromTheme()
        ->enqueue('theme/assets/css/app.css', handle: 'theme-styles')
        ->enqueue('theme/assets/js/app.js', handle: 'theme-app', inFooter: true);
}
```

Entry names are the `input` paths from `vite.config.js`, relative to the project root. `ViteManifest` reads the dev server `hot` file or `theme/dist/.vite/manifest.json`. With neither, it enqueues nothing and does not fail.

## CLI

All commands are under `wp foehn` (`ddev wp foehn …` in a DDEV project).

| Command                                                                                                                                                                                                                     | Purpose                                                             |
| --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------- |
| `make:post-type`, `make:taxonomy`, `make:model`, `make:block` (`--interactive`), `make:pattern`, `make:controller`, `make:context-provider`, `make:context`, `make:hooks`, `make:menu`, `make:image-size`, `make:shortcode` | Generate a class from a stub. All accept `--force` and `--dry-run`. |
| `discovery:list`, `discovery:status`, `discovery:generate`, `discovery:clear`                                                                                                                                               | Inspect and manage the discovery cache.                             |
| `cache:status`, `cache:clear` (`--url=`), `cache:warm`, `cache:config`                                                                                                                                                      | Static page cache.                                                  |
| `rewrite:flush`                                                                                                                                                                                                             | Rebuild rewrite rules.                                              |
| `verify --profile=updates\|production`                                                                                                                                                                                      | Release gates with a JSON report.                                   |
| `salts:generate` (`--force` rotates)                                                                                                                                                                                        | Write the WordPress security keys to `.env`.                        |

Run `wp help foehn <command>` for the options of one command. See https://studiometa.github.io/foehn-framework/guide/cli-commands.html.

## Gotchas

- **Discovery cache.** The starter sets `DiscoveryCacheStrategy::FULL` in every environment. A new class or a changed attribute is not seen until you run `wp foehn discovery:clear`. `wp foehn discovery:list` shows what was found and whether it came from the cache. To scan on every request locally, add `app/foehn.local.config.php` with `DiscoveryCacheStrategy::NONE`, and copy the `hooks` list into it, because the file replaces the plain one.
- **Autoloading.** Only classes Composer can autoload are discovered. A class outside the PSR-4 namespaces in `composer.json` is never found.
- **Opt-in hooks.** A framework hook class that is not in `FoehnConfig::hooks` does nothing. For example, `QueryFiltersHook` is required for `query-filters.config.php` to apply.
- **Interfaces are part of the contract.** A template controller, context provider, block, block binding, settings page or CLI command class must implement its interface. `#[AsCliCommand]` on a class without `CliCommandInterface` is silently skipped.
- **Template lookup.** `render()` throws on a missing template. Use `renderFirst()` with a generic last entry.
- **Context provider patterns match render names.** A provider runs when `render('pages/single', …)` is called with a matching name. `#[AsContextProvider('single')]` never runs for a controller that renders `pages/single`. Use `'pages/single'`, `'pages/*'` or `'*'`.
- **Controller names.** Føhn resolves one name per request: custom taxonomy pages are `taxonomy` / `taxonomy-{tax}`, not `tax-*`; author and date pages are `author` and `date`, not `archive`. A request no controller declares falls back to WordPress. See [references/views.md](references/views.md#request-flow).
- **Timber uses `false`, not `null`.** Read context values through `TemplateContext` typed properties, or test the type. `$context['user'] ?? null` can give `false`.
- **Blocks are dynamic.** There is no `save` output. Do not write block JavaScript or `block.json` by hand. If no Føhn block shows in the inserter, `wp-content/foehn/editor.js` is missing: run `composer install`.
- **Page cache.** It is on only in `production` in the starter. A purge runs on content changes, not on template edits. `composer install` clears the page cache and the discovery cache at their default location. Without it, run `wp foehn cache:clear` after a deploy that changes templates. A nonce in a cached page expires: exclude pages with forms that post.
- **Rewrite rules.** Føhn flushes when the set of `#[AsRewriteRule]` declarations changes. Every query var the rule uses must be in `queryVars`, or WordPress drops it.
- **REST permission.** `#[AsRestRoute]` with no `permission` requires `RestConfig::defaultCapability` (`edit_posts`). Use `permission: 'public'` for anonymous access, or the name of a method on the class.

## References

- [references/content.md](references/content.md): post types, taxonomies, post meta, models and queries, settings pages.
- [references/views.md](references/views.md): `TemplateContext`, controllers, context providers, DTOs, sections, Twig functions.
- [references/blocks.md](references/blocks.md): native blocks, sidebar controls, containers, Interactivity API, patterns, bindings, categories.
- [references/routes-and-commands.md](references/routes-and-commands.md): REST routes, rewrite rules, shortcodes, CLI commands, jobs and cron, Twig extensions, custom discoveries.
- [references/operations.md](references/operations.md): config files, opt-in hooks, discovery cache, page cache, query filters, images, verification.
