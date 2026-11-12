# Operations

Config files, opt-in hooks, the project model, the discovery cache, the page cache, query filters, images, the object cache, verification, security keys and the database export.

Docs: [Configuration](https://studiometa.github.io/foehn-framework/guide/configuration.md), [CLI commands](https://studiometa.github.io/foehn-framework/guide/cli-commands.md#project-inspection), [Hooks](https://studiometa.github.io/foehn-framework/guide/hooks.md#built-in-hooks), [Discovery cache](https://studiometa.github.io/foehn-framework/guide/discovery-cache.md), [Page cache](https://studiometa.github.io/foehn-framework/guide/page-cache.md), [Query filters](https://studiometa.github.io/foehn-framework/guide/query-filters.md), [Images](https://studiometa.github.io/foehn-framework/guide/images.md), [Caching](https://studiometa.github.io/foehn-framework/guide/caching.md), [Verification](https://studiometa.github.io/foehn-framework/guide/verification.md), [Security](https://studiometa.github.io/foehn-framework/guide/security.md), [Database export](https://studiometa.github.io/foehn-framework/guide/database-export.md).

## Config files

Each `theme/app/<name>.config.php` returns one object. Føhn registers it in the container under its class and its interfaces.

| File                       | Class (`Studiometa\Foehn\Config\…`) | Main arguments                                                                                                                                                                                                             |
| -------------------------- | ----------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `foehn.config.php`         | `FoehnConfig`                       | `discoveryCacheStrategy` (`DiscoveryCacheStrategy::NONE`), `discoveryCachePath`, `hooks` (`[]`), `imageTransformer` (`null`), `debug` (`false`), `recordErrors`, `traceRequests` (`null` = `local` and `development` only) |
| `page-cache.config.php`    | `PageCacheConfig`                   | `enabled` (`false`), `ttl`, `environments` (`['production']`), `cacheQueryArgs`, `excludedPaths`, `excludeWhenBodyContains`, `ignoredQueryArgs`, `bypassCookies`, `cacheNotFound`, `browserMaxAge`, `debugHeaders`         |
| `db-export.config.php`     | `DatabaseExportConfig`              | `window` (`'-2 months'`), `safeTables`, `structureOnlyTables`, `columns` (table => column => `Anonymizer`), `allowedEmailDomains`                                                                                          |
| `query-filters.config.php` | `QueryFiltersConfig`                | `taxonomies`, `publicVars`                                                                                                                                                                                                 |
| `rest.config.php`          | `RestConfig`                        | `defaultCapability` (`'edit_posts'`; `null` = any logged-in user)                                                                                                                                                          |
| `timber.config.php`        | `TimberConfig`                      | `templatesDir` (`['templates']`)                                                                                                                                                                                           |

The file name is free, but follow these names. `DiscoveryCacheStrategy` is `Tempest\Discovery\DiscoveryCacheStrategy` (`FULL`, `PARTIAL`, `NONE`).

Environment files: `foehn.local.config.php`, `foehn.dev.config.php`, `foehn.staging.config.php`, `foehn.production.config.php` (by `wp_get_environment_type()`). The environment file **replaces** the plain file. It does not merge into it. Repeat every argument you need.

Read configuration in code with constructor injection (`public function __construct(private PageCacheConfig $config)`).

## Opt-in framework hooks

These classes are in the framework, but discovery skips them until `FoehnConfig::hooks` names them.

| Class                                     | Effect                                                                                                                                        |
| ----------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------- |
| `Hooks\Cleanup\CleanContent`              | Removes empty `<p>` and archive title prefixes                                                                                                |
| `Hooks\Cleanup\CleanHeadTags`             | Removes wlwmanifest, RSD, shortlink and REST discovery links                                                                                  |
| `Hooks\Cleanup\CleanImageSizes`           | Removes `medium_large`, `1536x1536`, `2048x2048`                                                                                              |
| `Hooks\Cleanup\DisableBlockStyles`        | Dequeues core block styles. Do not use it when editors write content with blocks.                                                             |
| `Hooks\Cleanup\DisableEmoji`              | Removes emoji scripts and styles                                                                                                              |
| `Hooks\Cleanup\DisableFeeds`              | Removes the feed links from `wp_head`. The feeds still answer.                                                                                |
| `Hooks\Cleanup\DisableGlobalStyles`       | Removes the inline `theme.json` styles. The starter enables it because it ships no `theme.json`. Remove it if you adopt `theme.json` presets. |
| `Hooks\Cleanup\DisableOembed`             | Removes the oEmbed discovery links from `wp_head`                                                                                             |
| `Hooks\Security\DisableFileEditor`        | Defines `DISALLOW_FILE_EDIT`, which turns off the theme and plugin editors                                                                    |
| `Hooks\Security\DisableVersionDisclosure` | Removes the WordPress version                                                                                                                 |
| `Hooks\Security\DisableXmlRpc`            | Turns off XML-RPC                                                                                                                             |
| `Hooks\Security\GenericLoginErrors`       | One login error message for every failure                                                                                                     |
| `Hooks\Security\RestApiAuth`              | Removes the `/wp/v2/users` endpoints for anonymous requests. Other endpoints stay public.                                                     |
| `Hooks\Security\SecurityHeaders`          | Sends `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` and `Permissions-Policy`                                                 |
| `Hooks\QueryFiltersHook`                  | Applies `query-filters.config.php`                                                                                                            |
| `Hooks\YouTubeNoCookieHooks`              | Rewrites YouTube embeds to youtube-nocookie.com                                                                                               |
| `Hooks\StudiometaUi`                      | Registers the `@ui` and `@svg` Twig namespaces of `studiometa/ui`                                                                             |
| `Hooks\S3UploadsEndpoint`                 | Points `humanmade/s3-uploads` at a non-AWS endpoint                                                                                           |
| `Images\ImageCacheHooks`                  | Forgets an image's transforms when the image changes                                                                                          |

All are under `Studiometa\Foehn\`. Do not copy their code into the theme. Add the class to `hooks`.

Indexing protection is not opt-in: outside `production`, Føhn always adds `noindex, nofollow`, an `X-Robots-Tag` header, a `Disallow: /` robots.txt and turns off core sitemaps.

## Project model

- `wp foehn inspect` reports what the project declares, from the discovery items, in these sections: `problems`, `post-types`, `taxonomies`, `settings`, `blocks`, `patterns`, `bindings`, `templates`, `views`, `context-providers`, `menus`, `image-sizes`, `rewrite-rules`, `rest-routes`, `cli-commands`, `seeders`, `plugins`, `theme-supports`, `vite`. It registers and writes nothing.
- With no section, `wp foehn inspect --format=json` prints a summary, short enough to read whole: `{"summary": {"<section>": ["<row name>", …]}, "commands": {…}}`. Read it first, then read the sections you need in full in one call: `wp foehn inspect post-types templates menus --format=json`. `--all` prints every section; do not read it when a few sections answer the question.
- JSON is printed on one line. The full report keeps each section name as its key. An unknown section is an error that lists the valid names.
- `post-types` gives each type's Timber model class, its `#[AsPostMeta]` fields and its taxonomies, from either side of the link. A type Føhn does not register, such as `post` or a plugin's post type, is listed when a `#[AsTimberModel]` maps it or a `#[AsPostMeta]` names it, with `declared: false` (and `model: null` without a Timber model). A field with no subtype is listed on every type.
- To write content with core WP-CLI, read the storage keys first. `post-types` and `taxonomies` give each meta field's `key`, `type`, `default` and `single` (`wp post meta update`, `wp term meta update`). `settings` gives each option's name, `type`, `default` and `sanitize` callback (`wp option update`). `blocks` gives each attribute's `name`, `type`, `default` and `enum`, for block comments in `post_content`. Sample content goes in a seeder instead of one-off commands: see [content.md](content.md#seeders).
- Settings are registered on `init`, which WP-CLI fires, so `wp option update` runs the same sanitizer as the admin form (`sanitizeOnWpCli: true`). Every setting has one: declared, or the default of its type.
- `templates` is `{base, controllers, templates}`. Each row of `templates` is a WordPress template a controller claims: `template`, `controller` (lowest `priority` wins, a tie goes to the one discovered last), `overridden` (the controllers that lose) and `request`. `controllers` gives each class once, with its `priority`, `keys` and `partial`.
- `views` is `{base, providers, everyView, views}`. Each row of `views` is a Twig file of the theme, by view name (`pages/single`), with `file` and the `providers` that match it, in run order, other than those in `everyView`. `everyView` lists the providers on `*`, which match every view, and `providers` gives each class once, with its `priority`, `keys` and `partial`. All the providers of a view run in `priority` order.
- `context-providers` lists the view names each provider matches and the keys it adds. `menus` gives the Twig variable of each location (`menus.<location>`).
- Context keys: `base` keys are always set (`post`, `posts`, `site`, `user`, `theme`, `http_host`, `wp_title`, `body_class`, and `menus` with a `#[AsMenu]`). A template's `request` adds `term`, `search_query` or `author` for a term archive, `search` or `author`. The `keys` of a controller or provider come from literal `->with('key', …)` calls in its source, its parent classes and their traits, and may be conditional. `partial: true` means the class also sets keys only known at runtime (`merge()`, `withDto()`, a variable key). Keys from plugins' `timber/context` filters, `ViewEngine::share()` and block `compose()` are not listed.
- `inspect` does not say which view a controller renders: the controller picks it at runtime. Read the controller's `render()`/`renderFirst()` calls, then look the view up in `views`.
- `vite` lists the entry names `ViteManifest::enqueue()` takes, read from `dist/.vite/manifest.json`, and the dev server URL while `npm run dev` runs.
- With the `FULL` discovery cache, a class added since the cache was written is missing from the report until `wp foehn discovery:clear`.

## Generators

- Every `make:*` command (and `make:acf-block`, `make:field-group`, `make:options-page` from `foehn-acf`) runs without a prompt. Give the values as options: a missing value takes its default, and a missing name is an error.
- `--dry-run --format=json` writes nothing and prints `{"dryRun": true, "files": [{"path", "status", "contents"}], "notes": [...]}`. Read `contents` before you write the file.
- `--format=json` prints the same object for the real run, without `contents`. `status` is `created`, `overwritten` or `exists` (`would-create` or `would-overwrite` on a dry run). `notes` lists the next steps, for example the Twig template to create. `path` is absolute.
- A file that exists without `--force` is an error with exit status 1, on a dry run too. The file is left as it is. Use `--force` only for a file you mean to replace.
- A generated class goes next to the project's classes of the same kind, from discovery: their directory (the one that holds most of them), their namespace, and the parent class most of them extend. With none, the command's default directory and the stub's parent. The `path` of a dry run shows where it goes.
- `make:post-type <name> --rewrite-slug=<slug> --meta=<key>[:<type>],…` writes the model with `#[AsPostType]`, one `#[AsPostMeta]` per field and a typed accessor for each (`start_date` gives `startDate(): ?string`). Types: `string` (default), `integer`, `number`, `boolean`. Store dates as `Y-m-d` strings. With `--meta`, `supports` includes `custom-fields`, so the block editor reads and saves the fields. The class extends `Studiometa\Foehn\Models\Post` unless the project's post types share another parent. No accessor is written for a key whose method the parent has (`date`, `link`, `title`…, see [content.md](content.md#method-names-a-model-must-not-reuse)): read it with `post.meta('<key>')`.
- The `make:post-type` notes give, for `single-<name>` and `archive-<name>`, the controller that claims it and the Twig file to create, next to the theme's views of the same kind (`templates/pages/single-<name>.twig` in the starter). The controller picks the view at runtime: check its `render()`/`renderFirst()` call.

## Agent skills

- `wp foehn skill --format=json` lists the skills of the installed packages: `name`, `description`, `package`, installed `version`, and `path` relative to the project root. The sources are every Composer package with a `skills/<name>/SKILL.md` (third-party packages too) and `node_modules/@studiometa/foehn-vite-plugin/skills`.
- `wp foehn skill <name>` prints its `SKILL.md`. `--list-references` lists its reference files, and `--reference=<file>` prints one (`blocks`, `blocks.md` or `references/blocks.md`). An unknown skill or reference is an error that lists the valid names.
- The command reads files only. When WordPress cannot boot, read the same files directly: `vendor/studiometa/*/skills/*/SKILL.md` and `node_modules/@studiometa/foehn-vite-plugin/skills/*/SKILL.md`.

## Problems and errors

Docs: [Problems and errors](https://studiometa.github.io/foehn-framework/guide/troubleshooting.md).

- A class whose attribute contract is broken is skipped and recorded, and every other class registers: `#[AsPostType]`/`#[AsTaxonomy]`/`#[AsTimberModel]` without `extends Timber\Post` or `Timber\Term`; `#[AsBlock]`, `#[AsContextProvider]`, `#[AsTemplateController]`, `#[AsBlockBinding]`, `#[AsSettingsPage]`, `#[AsCliCommand]`, `#[AsSeeder]`, `#[AsAcfBlock]`, `#[AsAcfFieldGroup]` without their interface; `#[AsTwigExtension]` without `extends AbstractExtension`; `#[AsCron]` without a public `__invoke()`; `#[AsJob]` whose `__invoke()` does not take one class-typed parameter; `#[AsRewriteRule]` with `after` other than `top`/`bottom`; an invalid `#[AsPostMeta]` (that key only); a class in `hooks` that does not exist.
- `wp foehn inspect problems --format=json` gives one row per problem: `class`, `attribute`, `reason`, `fix` (`null` when the cause is not known). It also lists a post type naming a taxonomy, or a taxonomy naming a post type, that no attribute declares and WordPress does not register. Apply the `fix`, run `wp foehn discovery:clear`, then check that `problems` is empty.
- `wp foehn discovery:status` prints the skipped classes, one line each, as `<reason> Fix: <fix>`. The records are in the discovery cache, so a warm cache still reports them. With `debug` on, each one is also a PHP warning on every request.
- `TimberViewEngine` throws `Studiometa\Foehn\Views\ViewRenderingFailed` (a `RuntimeException`). A Twig error gives the view asked for, the template and line where Twig failed and Twig's message (the Twig error is `getPrevious()`). A missing view gives every view tried, the directories Timber searched and `Fix: create <directory>/<view>.twig`.

## Error log

Docs: [The error log](https://studiometa.github.io/foehn-framework/guide/troubleshooting.md#the-error-log).

- `wp foehn errors` prints the last entry in a few lines (messages cut at 300 characters, 5 stack lines). `--limit=<n>` (newest first), `--level=exception,fatal,warning,notice`, `--since=<date>` (`-10 minutes`), `--request=<id>` (the entries of one traced request), `--format=json` (every field, one line), `--clear`.
- Recorded: uncaught exceptions, fatal errors, and the warnings and notices of the theme's own code (PHP files of the theme directory outside `vendor/` and `node_modules/`, and its Twig templates). Not recorded: warnings of WordPress, plugins and packages, warnings that `error_reporting()` leaves out (`@`, notices when `WP_DEBUG` is off), deprecations, caught exceptions, errors raised before the theme boots Føhn. Each error once per request, at most 20 per request.
- Fields: `time`, `level`, `class`, `message`, `file`, `line`, `twig` (`template:line`), `previous`, `stack` (theme frames, then `+N other frames`), `method`, `url` (or `CLI` and the WP-CLI command), `template` (the WordPress template Føhn matched), `controller`, `view`, `context` (keys), `requestId` (the id of the traced request, from its `X-Foehn-Trace` header, or `null`).
- On by default in the `local` and `development` environments. `FoehnConfig(recordErrors: true)` turns it on elsewhere, `false` turns it off.
- File: `wp-content/cache/foehn/errors/errors.jsonl`, rotated at 256 KB, 3 files at most.
- PHP and WordPress handle the error as before: each handler gives the error to the one before it, an uncaught exception is thrown again so PHP ends the request with its fatal error, and `WP_Fatal_Error_Handler` still shows its page.

## Debug log

Docs: [The debug log](https://studiometa.github.io/foehn-framework/guide/troubleshooting.md#the-debug-log).

- `wp foehn log` reads the file WordPress gives to PHP's `error_log` when `WP_DEBUG` and `WP_DEBUG_LOG` are on: `wp-content/debug.log`, or the path `WP_DEBUG_LOG` holds. A Føhn `wp-config.php` sets `WP_DEBUG_LOG` from `WP_DEBUG`: `WP_DEBUG=true` in `.env` turns it on. When it is off, the command says so and exits with an error.
- It holds what the error log leaves out: warnings of WordPress, plugins and packages, `_doing_it_wrong()` notices, deprecations, `error_log()` messages, errors raised before the theme boots.
- Levels: `fatal`, `warning`, `notice`, `deprecated`, `log` (a message without a PHP error type). The lines without a date after an entry are its stack trace (after `Stack trace:`) or the rest of its message.
- Entries with the same level, message, file and line are one group: `level`, `message`, `file`, `line`, `count`, `first`, `last` (UTC), `trace` (of the last entry, 50 lines at most). The groups seen last come first.
- Default: the last 20 groups, two lines each and 3 trace lines. `--limit=<n>`, `--level=fatal,warning`, `--since=<date>` (`-10 minutes`), `--grep=<text>` (message, file or trace, case does not matter), `--format=json` (every field, one line). Filters apply before grouping.
- Reads the last 8 MB of the file only, and says so (on stderr with `--format=json`). It never deletes or rotates the file.

## Request trace

Docs: [Request Trace](https://studiometa.github.io/foehn-framework/guide/request-trace.md).

- `wp foehn trace <path>` (`/about/`, or a URL of the site) requests the page with `wp_remote_get()` as an anonymous visitor (no cookies, no redirects followed, certificate not checked), reads the id in its `X-Foehn-Trace` header and prints the stored trace. `last` (the default) is the last traced request, `<id>` one trace, `list` the last requests one per line (`--limit=<n>`, 10 by default). `--format=json` prints every field and the full error entries of the request; `--clear` deletes the traces.
- A trace holds: `id`, `time`, `method`, `url`, `status`, `location` (a redirect), `durationMs`, `queries`, `hierarchy` (template files WordPress tried), `templateFile` (the file it found), `template` and `controller`, `views` (each with `count`, `keys`, `candidates` from `renderFirst()`, `providers` in the order they ran) and `blocks` (name and count). Lists keep 30 items; the text cuts them after 10.
- Traced: the requests that reach WordPress's `send_headers` action (front-end pages, feeds, redirects, 404s). Not traced: admin, `admin-ajax.php`, WP-Cron, REST, WP-CLI, `/favicon.ico`, `/robots.txt`, static files and pages the page cache serves. Errors raised after `send_headers` carry the id as `requestId`.
- On by default in the `local` and `development` environments. `FoehnConfig(traceRequests: true)` turns it on elsewhere, `false` turns it off.
- Store: `wp-content/cache/foehn/traces`, one JSON file per request, the last 100 kept. The page cache does not store the `X-Foehn-Trace` header.

## Discovery cache

- `NONE`: scan on every request. `PARTIAL`: cache vendor packages, scan the app. `FULL`: cache everything.
- With `FULL` (the starter default), a new or changed attribute is ignored until the cache is cleared: `wp foehn discovery:clear`. The next request writes a new cache. `wp foehn discovery:generate` writes it now.
- `composer install` clears the discovery cache and the page cache (both under `wp-content/cache/foehn/`, the default location), so a deploy refreshes them. A custom `discoveryCachePath` or `PageCacheConfig::path` is not cleared.
- `wp foehn discovery:list` shows every item, its location, and whether the location was restored from the cache. `wp foehn discovery:status` shows the cache state per location and the classes discovery skipped.
- Cache files: `wp-content/cache/foehn/discovery/` by default.

## Page cache

Static HTML files for anonymous `GET` requests, served by nginx or Apache (generated config) or by the `advanced-cache.php` drop-in.

```php
// app/page-cache.config.php
use Studiometa\Foehn\Config\PageCacheConfig;

return new PageCacheConfig(
    enabled: true,
    ttl: 8 * HOUR_IN_SECONDS,
    environments: ['production'],
    cacheQueryArgs: [
        'project_category' => '^[a-z0-9-]+(?:,[a-z0-9-]+)*$', // a pattern
        'orderby' => ['date', 'title'],                     // only these values
        'posts_per_page' => [3, 6, 12],
    ],
    excludedPaths: ['/contact'],
);
```

- A query argument that is not ignored and not in `cacheQueryArgs` makes the request bypass the cache. A filter added in `query-filters.config.php` must also be named in `cacheQueryArgs`, or every filtered URL is uncached.
- `foehn_sections` is always keyed. Do not add it.
- Content changes purge the affected URLs. Template and code changes do not. A deploy that runs `composer install` clears the whole page cache. Otherwise run `wp foehn cache:clear` (or `--url=<url>` for one URL).
- Nonces in a cached page expire after 12 to 24 hours. Put pages with forms that post in `excludedPaths`, or use `excludeWhenBodyContains`.
- Logged-in users, previews and password-protected posts are never cached. Search results are not cached unless `s` is in `cacheQueryArgs`.
- `wp foehn cache:config --server=nginx|apache --write` writes the server config. Run it again after each change to `page-cache*.config.php`. `wp foehn cache:status` reports a stale snippet. `wp foehn cache:warm` fills the cache from the sitemap.
- To try the cache locally, add `app/page-cache.local.config.php` with `environments: ['local']`.

## Query filters

WordPress already reads public taxonomy query vars (`?genre=rock`, `?genre[]=rock&genre[]=jazz`) and `orderby`, `order`, `s`. Use `QueryFiltersConfig` only for operators (`__and`, `__not_in`, `__exists`), private vars such as `posts_per_page`, or non-public taxonomies.

```php
// app/query-filters.config.php
use Studiometa\Foehn\Config\QueryFiltersConfig;

return new QueryFiltersConfig(
    taxonomies: ['genre' => ['in', 'not_in']],
    publicVars: ['posts_per_page' => [3, 6, 12]], // a value outside the list is refused
);
```

Add `QueryFiltersHook::class` to `FoehnConfig::hooks`. In Twig, build the filter UI with `query_get()`, `query_url_toggle()`, `query_hidden_inputs()` and `facet()`. A `pre_get_posts` action that sets defaults must run after the hook (`priority: 20`) and must not overwrite a value the visitor set. An example that adds a date clause to a post type archive: [content.md](content.md#dates-and-an-archive-of-upcoming-items).

## Images

`#[AsImageSize]` registers sizes generated at upload. For arbitrary sizes and formats, use `image_url()` in Twig:

```twig
<img src="{{ image_url(post.thumbnail, { w: 800, h: 600, fit: 'crop', fm: 'webp' }) }}" alt="{{ post.thumbnail.alt }}" />
```

With no transformer, `image_url()` returns the source URL. To transform, `composer require league/glide` and set `imageTransformer: \Studiometa\Foehn\Images\GlideTransformer::class` in `FoehnConfig`. Add `ImageCacheHooks::class` to `hooks`. Sizes snap to a step (100 px by default, maximum 2600). The server config to serve cached transforms is in the images guide.

## Object cache

Inject `Studiometa\Foehn\Contracts\CacheInterface` (a transient-backed implementation):

```php
$projects = $this->cache->remember('featured_projects', HOUR_IN_SECONDS, fn() => Project::query()->limit(3)->get());

$this->cache->tags(['projects'])->remember('project_count', DAY_IN_SECONDS, fn() => Project::count());
$this->cache->flushTag('projects'); // for example in a save_post action
```

Other methods: `get`, `set`, `has`, `forget`, `rememberForever`, `forever`, `increment`, `decrement`, `flushTags`.

## Environment helpers

- `Studiometa\Foehn\Helpers\Env`: `Env::get()`, `Env::is('staging')`, `Env::isProduction()`, `Env::isStaging()`, `Env::isDevelopment()`, `Env::isLocal()`, `Env::isDebug()`.
- `Studiometa\Foehn\Helpers\WP`: `WP::query()` (the main `WP_Query`), `WP::post()`, `WP::user()`, `WP::db()`, `WP::withPost($post, $callback)`. Use these instead of the `global` keyword.

## Verification and security keys

```bash
wp foehn verify --profile=updates --output=build/foehn-verification.json   # CI, after a WordPress or plugin update
wp foehn verify --profile=production                                       # deploy script
```

Exit status: `0` pass, `1` something to fix, `2` the gate could not run.

`wp foehn salts:generate` writes the eight WordPress keys to `.env` when they are missing. `--force` rotates them and logs out every user. The generated `wp-config.php` refuses a production request without keys.

## Database export

```bash
ssh production 'wp foehn db:export -' > db.sql.gz   # dump to stdout, report to stderr
ddev import-db --file=db.sql.gz
ddev wp user update user-1 --user_pass=password      # no password hash is exported
```

- Core tables are kept. `users` and `comments` are anonymized (`user-<ID>@example.invalid`, `user-<ID>`), personal `usermeta` keys and transients are removed, revisions older than the window (`--from`, default `-2 months`) are removed.
- Presets for WooCommerce (orders in the window, anonymized), Gravity Forms (entries structure only) and logs or indexes apply when their tables exist.
- **Fail-closed:** a table no rule covers is exported without rows and listed in a warning. Declare it in `db-export.config.php`: `safeTables`, `structureOnlyTables`, or `columns` with `Studiometa\Foehn\DatabaseExport\Anonymizer` cases (`Email`, `Username`, `Name`, `Ip`, `Empty`). Names are without the table prefix; a trailing `*` matches a prefix.
- Not anonymized: post content, options (`admin_email`, plugin API keys), comment text.
