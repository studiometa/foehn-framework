# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.5.0] - 2026-08-20

### Added

- **`ViteManifest`**, the helper `@studiometa/foehn-vite-plugin`'s README had been pointing at for a release without the framework shipping one. It reads whichever of the two things the plugin wrote: the `hot` file while `npm run dev` runs, so entries and the Vite client come from the dev server and hot module replacement works, or `dist/.vite/manifest.json` after a build. It is not `WebpackManifest` renamed — `@studiometa/webpack-config` emits an `assets-manifest.json` of entrypoints and Vite emits a flat map of chunks, which is why there are two classes rather than two branches. It covers the three things a theme gets wrong writing this itself: the stylesheets a JavaScript chunk lists in its `css` array, separate from its own `file`, and whose omission loads the page with no styles and no error; the module type, since `wp_script_add_data($handle, 'type', 'module')` looks like the way to say so and is not, because `WP_Scripts` reads `strategy`, `before`, `after` and `data` and never `type`; and a missing build, which enqueues nothing rather than fataling
- **`studiometa/ui` integration.** `StudiometaUi` registers the `@ui` and `@svg` Twig namespaces the package ships its components under, on `timber/twig` — the namespaces exist only once its extension has been handed Twig's loader, which is why it cannot go through `#[AsTwigExtension]`: the container has no loader to autowire, and the loader only exists once Timber has built the environment. It is an opt-in hook class listed in `foehn.config.php`, because a framework hook class that registered itself for being in a scanned package would let a `composer update` change what a site does. The package stays a `suggest`, and the hook returns the environment untouched when it is absent, so the framework gains no dependency
- The starter and the demo autoload their JavaScript components rather than wiring each one: `import.meta.glob` hands Vite a lazy importer per file, `fromMetaGlob` normalises it, `registerManifests` schedules the start, and the loader mounts whatever `[data-component]` it finds while fetching only those modules. Adding a component is dropping a file into `components/` — `app.js` never changes. `import '@studiometa/ui/autoload'` registers that package's manifest the same way, as a side effect of the import, so `data-component="Modal"` works with no import of `Modal` anywhere. `@studiometa/js-toolkit` and `@studiometa/ui` are runtime dependencies in both packages rather than dev ones, because they ship in the bundle a visitor runs
- **The demo is a site rather than a gallery of switched-on options.** A photographer's portfolio — a homepage, an index of six series, a page per series and an about page — set in a Swiss idiom of two colours, one grotesque and a twelve-column grid. Every attribute is still exercised, but because a page needed it: `#[AsPostMeta]` carries the client and film stock a project page prints, `#[AsImageSize]` sizes the cards, `#[AsRewriteRule]` answers `/_health`, the settings page supplies the contact address in the footer, and an `@ui` accordion answers commission questions on the about page. Thirty photographs from one Unsplash collection travel with it, each carrying its photographer's name and profile link as attachment meta and printed under every plate — the smoke test fails when the count of credits stops matching the count of photographs. `database/` holds the whole site: a SQL dump, the photographs the dump cannot carry because uploads are offloaded, and `restore.sh`, which CI runs on every build so the restore path is tested rather than assumed
- Uploads can be served from the site's own domain instead of the bucket's. `S3_UPLOADS_DISABLE_REPLACE_UPLOAD_URL` stops the plugin rewriting media URLs, and a webserver or CDN rule maps `/wp-content/uploads/` to the bucket — the setup `humanmade/s3-uploads` names in its own README. It removes the bucket hostname and port from every image URL, so a renamed project or a moved bucket no longer serves pages whose every image 404s with nothing in wp-admin to explain it. The installer defines the constant from the environment like the rest
- **Uploads can go to object storage**, which is what makes a Føhn site deployable where local disk does not survive a release. `web/` is generated and `web/wp/` comes from Composer, so a deploy can throw the document root away and rebuild it — `web/wp-content/uploads/` was the exception, and the one directory holding something neither the repository nor Composer can reproduce. Føhn does **not** implement the offload: [`humanmade/s3-uploads`](https://github.com/humanmade/S3-Uploads) has done that for 2.5 million installs, and a first draft of the spec proposing a package of our own was withdrawn once checking showed the generated `wp-config.php` already requires the Composer autoloader — the plugin's one documented prerequisite — and already reads `.env` through an `$env()` helper. What is new is the wiring: `wp-config.php` defines the `S3_UPLOADS_*` constants from the environment, and only when `S3_UPLOADS_BUCKET` is set, so the same generated file serves a development machine with no bucket and a container with one. A value already defined by `config/*.config.php` wins, and a value absent from the environment is left undefined rather than defined as null — which is what lets an IAM instance profile supply the credentials and the plugin derive its own bucket URL. `S3UploadsEndpoint` is an opt-in hook class supplying the `s3_uploads_s3_client_params` filter that the plugin's documentation tells you to hand-write into an mu-plugin, so Cloudflare R2, Scaleway, DigitalOcean Spaces, Ceph and MinIO are `.env` rather than PHP; it also carries the AWS SDK 3.337 checksum switch, whose absence looks like uploads failing for no stated reason. `packages/demo` runs the whole path against MinIO in a ddev service — a real upload, the original and every sub-size in the bucket, none of its files left on local disk, and a request to the public URL returning the bytes, which is the check `wp s3-uploads verify` does not make and the one that catches a bucket that accepts writes and serves nothing. See `docs/guide/uploads.md`
- `#[AsBlockBinding]` registers a block bindings source, so a block attribute — a paragraph's text, an image's `alt`, a button's `url` — shows a value computed at render time, with no custom block. What it is **not** for is the common case: WordPress ships `core/post-meta`, and a key declared with `#[AsPostMeta]` is bindable through it with no source at all, which the guide leads with. A source is for a value that is computed — a formatted price, a reading time, a figure from elsewhere. `usesContext` is what makes the block's context reach the value, since WordPress passes nothing a source did not ask for, and the `$attribute` argument is what lets one source answer for both an image's `url` and its `alt`. The class is resolved from the container when a bound block renders rather than when the source is registered, so a source nothing binds to costs nothing; the callback is built at apply time and never stored, because a callable cannot survive the discovery cache. A name without a namespace is refused during discovery — WordPress refuses it too, but through `_doing_it_wrong()`, which is to say only under `WP_DEBUG`
- `#[AsSettingsPage]` puts an admin screen on the WordPress Settings API, where `register_setting` appeared in zero files — `#[AsAcfOptionsPage]` was the only settings screen Føhn offered, which does not work now that ACF is optional. The page declares what it stores through `settings()`, and its form is a Twig template named on the attribute — like every other view in a Føhn theme, and with no PHP on the page class beyond the declaration. The template receives the current values, typed as declared, so `settings.show_banner` is a boolean rather than the empty string WordPress stores an unchecked box as. A form that needs more than those values implements `SettingsFormInterface::form(): string` instead, returning its HTML the way `TemplateControllerInterface::handle()` does; a page that supplies neither is refused during discovery. the framework supplies the menu entry, `register_setting()` per setting with its type, default and sanitiser, and the page shell — `settings_errors()`, the form, `settings_fields()`, `do_settings_sections()` and the submit button. `settings_fields()` is why the shell exists: a page that forgets it looks like it simply does not save, with no error anywhere, and you cannot forget what you never write. There is **no field abstraction**, by decision: repeaters, conditional logic and media pickers are ACF's actual product, and a `Field::text(...)` builder is the first step towards maintaining a field library nobody asked Føhn for. `Setting::string()`, `bool()`, `int()` and `number()` declare a setting with a sanitiser its type implies, and `show_in_rest` off by default — unlike `#[AsPostMeta]`, because settings are configuration and sometimes credentials. `Settings::get()` reads one back with the declared default, which `get_option()` does not answer with until the option has been saved once, and with the declared type, because WordPress stores an unchecked checkbox as the empty string
- `#[AsRewriteRule]` declares a URL and, through `RewriteHandlerInterface`, answers it. `add_rewrite_rule` appeared in zero files, so every webhook, form handler and signed URL was hand-rolled — usually as a `template_redirect` hook reading `$_SERVER['REQUEST_URI']`, which is the thing rewrite rules exist to avoid. The handler runs on `parse_request`, before the main query, so it can answer and `exit` without a page being rendered first. The declared `queryVars` are registered through the `query_vars` filter, without which WordPress discards the rewrite's own variables and the handler is never reached. Rules registered in code do nothing until WordPress flushes them once, and flushing per request ruins a site: the declared set is hashed into `foehn_rewrite_rules_hash` and flushed exactly when the hash changes, so adding a rule and reloading works. `wp foehn rewrite:flush` forces it, and says so when the site uses plain permalinks, which bypass rewrite rules entirely
- **`studiometa/foehn-acf`**, a package of its own. `#[AsAcfBlock]`, `#[AsAcfFieldGroup]`, `#[AsAcfOptionsPage]`, their discoveries and contracts, `AcfBlockRenderer`, `AcfFieldTransformer`, `AcfConfig`, `AcfOptionsService`, the four field fragment builders and the three `make:` commands all move there, with their tests and their documentation. **Breaking:** a project using any of them adds one Composer requirement — `composer require studiometa/foehn-acf` — and changes no imports, because the classes keep their `Studiometa\Foehn\` namespaces. The package supplies its own `AcfConfig` default through `src/Config/acf.config.php`, which `ConfigLoader` reads before a project's `app/acf.config.php`, so the `Kernel` no longer registers one and `AcfBlockRenderer` is autowired rather than wired by hand. Custom fields no longer need a paid plugin at all: `#[AsPostMeta]` covers the default path, and ACF is what a project reaches for when the editing UI matters
- `#[AsPostMeta]` registers a meta key through `register_meta()`, which Føhn touched in zero files — the reason every custom field went through ACF. The attribute is repeatable and goes on the model that owns the field, so the declaration sits next to the accessor that reads it, and the post type or taxonomy is inferred from the class's own `#[AsPostType]`, `#[AsTaxonomy]` or `#[AsTimberModel]`: `register_meta()` with no `object_subtype` registers a key for _every_ post type. `showInRest` defaults to on, because without REST the field is invisible to the block editor and cannot be bound through core's `core/post-meta`. `sanitize` names a public static method rather than taking a callable — an item reaches the cache through `var_export()`. It does not conflict with ACF, which stores its values in ordinary post meta: declaring a key ACF also manages leaves ACF the editing UI and adds the REST schema
- `wp foehn discovery:list` reports what discovery found: every discovery, its phase, the items it holds and the attribute arguments each was built with. Nothing could say this before — `discovery:status` answers how warm the cache is, not what registered, and on 2026-08-19 that turned a one-line bug into an hour. A discovery that found nothing is listed rather than hidden, and each location says whether it was scanned or restored from the cache, which is what makes a stale entry diagnosable. `--discovery=`, `--location=` and `--format=table|json|count` narrow the output; a third-party discovery renders with no work, because the renderer reflects whatever attribute the item holds
- `#[AsDiscovery]` declares the WordPress phase a discovery class applies in, and discovery classes are now themselves discovered: any class implementing `Tempest\Discovery\Discovery` inside a scanned location is found, resolved and run. A Composer package or a theme's `app/` directory can add one. `docs/guide/custom-discovery.md` documented this and it had never worked — `DiscoveryRunner::getDiscoveryPhases()` was a hardcoded list of nineteen classes that nothing outside that file could add to
- **Every anonymous page view paid for a full render.** Føhn had a discovery cache and could use an object cache, but nothing stopped a visitor's request going through WordPress, the database, the template hierarchy and Twig to produce HTML identical to what the last visitor got — around a hundred milliseconds of work per view, and the thing that falls over first under a traffic spike. There is now a static page cache: PHP writes the rendered HTML of an anonymous `GET` to `wp-content/cache/foehn/pages/{host}/{path}/index.html`, and the next request for that URL is answered from the file. Off by default, and allowed in `production` only, through `app/page-cache.config.php`. See [the guide](https://studiometa.github.io/foehn-framework/guide/page-cache) for what is never cached and for the nonce caveat, which is a decision a project has to make rather than one the framework makes for it
- The cache is served by whichever reader the site can offer, and all of them compute the same filename by construction. `wp foehn cache:config --server=nginx|apache` generates a server snippet from the loaded configuration — so the cookies, the query-arg policy and the cache path in it cannot drift from the PHP that wrote the files — and the generated snippet composes with an existing `location /` rather than replacing it. Where there is no server access, an `advanced-cache.php` drop-in written by the installer serves the same files before WordPress loads, and is the only reader that can enforce a TTL per request or answer with a `304`
- Invalidation is event-driven, because a cache that serves stale pages is worse than no cache. Editing a post purges its permalink, the front page, the posts page, its post type archive, its author and month archives, every term archive it appears in, its ancestors and both adjacent posts; a theme switch, a menu change, a plugin activation, a permalink change or an ACF options save flushes everything. Targets accumulate during a request and are acted on once on `shutdown`, so a bulk edit does not run the same recursive delete forty times. `foehn/page_cache/purge_urls`, `foehn/page_cache/purge_post` and `foehn/page_cache/flush` are the seams for a project's own rules and for a CDN integration
- A campaign link and a paginated URL are both cached, and neither depends on the order its query args arrive in. Args in `ignoredQueryArgs` — the `utm_*` family, `gclid`, `fbclid` and friends — are dropped before the filename is computed, so a newsletter link hits the same file as a bare URL. Args in `cacheQueryArgs` go into the filename instead: `?page=2&lang=fr` and `?lang=fr&page=2` both read `index__lang=fr&page=2&.html`, because no reader parses the query string left to right — each walks the configured names in one fixed order and asks for each value, which is the only form of this nginx can express. A value its configured pattern rejects bypasses to PHP rather than falling back to the unkeyed page, and a repeated arg bypasses too, since nginx keeps the first occurrence and PHP the last. Anything the site was not told about is a bypass
- Non-ASCII permalinks resolve to one cache file. WordPress stores such a slug with lowercase percent escapes while a browser sends uppercase ones, so a naive purge looks for a directory that does not exist and every accented archive stays stale after an edit — the bug that cost wp-super-cache two issues. Every reader decodes the path exactly once, in one place, and there is an end-to-end test for it
- `wp foehn cache:clear`, `cache:status`, `cache:config` and `cache:warm`. `cache:status` reports which readers are installed and whether an installed snippet was generated from a configuration the site no longer has, which is the failure this design is most exposed to
- The keys are read from the environment, so a project can keep them in `.env`, in container variables, or in a vault. `.env.example` lists the eight names empty so they are visible without being committed, and the installer generates nothing when the environment already supplies them. `config/wordpress-salts.config.php` still works for a project that prefers a PHP file, and is read first
- `wp foehn salts:generate` writes a fresh set of WordPress security keys, for rotating them or for a project whose keys were never generated. `--force` replaces existing ones, which logs every user out
- The discovery cache fills itself. A request that had to scan a location writes what it found, so the next one does not, and `composer install` clears the cache through the installer plugin. Between them there is no manual step on a deploy: `wp foehn discovery:generate` stays for warming before traffic arrives rather than on the first visitor's request. A cache that cannot be written is not an error — the page is served and the scan happens again next time
- Config files may be named for an environment — `foehn.production.config.php` — and are then read only in that one, as reported by `wp_get_environment_type()`. The environment's file wins over the plain file beside it
- An integration smoke test for the starter (`packages/starter/tests/smoke/run.sh`), run in CI against a real WordPress in ddev, on a cold cache and again on a warm one
- Tests for `studiometa/foehn-installer`, which had none, and CI now runs the starter's PHP and browser suites and the installer's alongside the framework's
- Add `#[AsCron]` attribute for recurring background jobs via Action Scheduler ([db208f7], [#111], [#110]):
- Add `#[AsJob]` attribute for async job dispatch with typed DTO payloads ([db208f7], [#111], [#110]):
- Add `HookNameResolver` for deterministic hook names derived from FQCN ([1a728f9], [#111])
- Add `CacheInterface` contract and `TransientCache` implementation for dependency injection ([#96])
- Add `TaggedCache` for tag-based cache invalidation via `CacheInterface::tags()` ([#96])
- Add `Arrayable` interface and `HasToArray` trait for typed DTO context composition ([#97])
- Add built-in DTOs for common ACF field patterns ([#97]):
- Widen `compose()` return type to `array|Arrayable` on block interfaces ([#97])
- **Starter:** Add Hero block example demonstrating DTO context composition ([#97])
- **New package:** `@studiometa/foehn-vite-plugin` — Vite plugin for front-end bundling ([#89]):
- Add fluent `PostQueryBuilder` for null-safe query building ([#91], [#100]):
- Add `QueriesPostType` trait with `query()`, `all()`, `find()`, `first()`, `count()`, `exists()` ([#91], [#100])
- Add `PostTypeRegistry` for class-to-post-type mapping ([#91], [#100])
- Add `Foehn\Models\Post` and `Foehn\Models\Page` base classes with query support ([#91], [#100])
- Add Starter Theme documentation with quick start guide and feature overview ([#101])
- Add `TemplateContext` class for typed template controller context ([#107]):

### Changed

- **`packages/starter` splits in two.** It was doing two jobs that pull against each other — the minimal starting point `composer create-project` hands someone, and the demonstration of everything the framework ships — and every feature added made the second job heavier at the first one's expense. A new **`studiometa/foehn-demo`** takes the demonstration: every post type, taxonomy, block, binding, settings page, route, image size and DTO, plus the browser suite and the whole of `tests/smoke/`, which is where exhaustive coverage belongs. The starter keeps what a theme cannot render without — the boot, `foehn.config.php`, the four template controllers, the three menu locations its templates read, the global context provider, the theme supports, the templates and the front-end build with `@studiometa/foehn-vite-plugin` — and nothing else. Its own smoke test asks the one question it has to answer: does a project created from it boot and serve a page? The demo's theme namespace is `Demo\` rather than `App\`, which is the only thing in it a project would not copy
- **Starter:** `HeroBlock` is a native block, `theme/hero`, and the starter requires no ACF at all. It was the one thing in there that needed a paid plugin to run, so that path was never exercised end to end — ACF Pro is not installed in CI, and `AcfBlockDiscovery` reported an item that could never register. Everything it did survives without one: the sidebar controls come from the attribute schema, and `compose()` still returns a typed `HeroContext` DTO. The starter now demonstrates the default path, and its integration test covers the whole of what it ships
- `DiscoveryRunner::getDiscoveryPhases()` and `DiscoveryRunner::getAllDiscoveryClasses()` are removed, and `DiscoveryRunner::hasRun()` takes a `DiscoveryPhase` rather than a string. A discovery's phase lives on the discovery, in `#[AsDiscovery]`. Within a phase they apply in class name order, so a cold request and a warm one register in the same sequence
- Discovery is built on `tempest/discovery` — already a direct dependency — instead of Foehn's own scanner. Locations come from Composer's `installed.json`, which is what makes an installed package discoverable at all. `ClassScanner`, `DiscoveryLocation`, `DiscoveryCache`, `WpDiscoveryItems`, `AttributeCodec`, the `CacheableDiscovery` trait and the `WpDiscovery` interface are gone; discoveries implement `Tempest\Discovery\Discovery` and receive a `ClassReflector`
- Discovery items are cached as attribute instances through `symfony/cache`, so no discovery describes a cache format. The cache is written per location, and `discovery:status` reports how many locations are warm
- WP-CLI commands are registered under `wp foehn` rather than `wp tempest`
- `discovery:warm` is removed: it and `discovery:generate` did the same thing, and the deployment documentation already used `generate`
- The framework's `Models\Post` and `Models\Page` now register as Timber's class map entries for `post` and `page`, as their `#[AsTimberModel]` attributes ask. A model of your own for the same type still wins
- `Kernel::boot()`'s configuration array is a default that a `foehn.config.php` overrides wholesale
- Upgrade PHP dependencies: Tempest `^3.4` → `^3.18`, Timber `^2.0` → `^2.5`, Pest `^3.0` → `^5.1` (PHPUnit 13), Mago `^1.8` → `^1.46`, `composer/composer` `^2.0` → `^2.10`, ACF Pro stubs `^6.5` → `^6.8`, `studiometa/webpack-config` `^6.3` → `^6.4`
- Declare `tempest/support` as a direct dependency of `studiometa/foehn` — `Filesystem` and `str()` were used across ~15 files but only resolved transitively
- **Starter:** Upgrade WordPress `^6.7` → `^7.0`
- Upgrade front-end dependencies: Vite `^6` → `^8`, Vitest `^3` → `^4`, TypeScript `^5.7` → `^7.0`, `vite-plugin-dts` `^4.5` → `^5.0`, lint-staged `^15` → `^17`, oxfmt `^0.28` → `^0.63`, Tailwind `^4.0` → `^4.3`, js-toolkit `^3.4` → `^3.9`, Playwright `^1.50` → `^1.62`, Prettier `^3.6` → `^3.9`
- Widen the `@studiometa/foehn-vite-plugin` Vite peer range to `^6 || ^7 || ^8`
- Run Pest from the monorepo root with `--test-directory`; Pest 5 resolves the test path from the Composer root, not the working directory
- Bump CI to Node 24 — lint-staged 17 requires Node >= 22.22.1
- A discovered item is now the attribute instance plus the reflection facts that are not in the attribute, instead of a flattened copy of its fields. `AttributeCodec` serializes attributes for the cache in one place, so `itemToCacheable()` is gone from all 19 discoveries, along with `resolveAttribute()`, the `registerBlockFromCache()` paths and the 13-parameter `doRegisterBlock()`. Values derived from an attribute are computed in `apply()` rather than cached
- `getCacheableData()`, `restoreFromCache()` and `wasRestoredFromCache()` are part of the `WpDiscovery` interface; every discovery is cacheable, so the three `method_exists()` probes are gone
- Bump the discovery cache schema to `2`. A cache written by an earlier version is rejected and rebuilt on the next request
- Generated `make:` files are built by `ClassFileGenerator`, which takes the app path as a dependency instead of reaching `Kernel::getInstance()`, and sets attribute arguments structurally rather than by matching literals in the stub's printed source. A substitution that finds no target now fails instead of silently emitting the stub's placeholder
- Generated class files now declare `strict_types=1`
- Refactor `DiscoveryRunner` to use `runPhase()` loop, reducing code duplication ([aa22c38], [#111])
- **BREAKING:** Require PHP 8.5+ and Tempest Framework v3.0 ([#98])
- **BREAKING:** Remove static `Helpers\Log` helper — use Tempest Logger (`tempest/log`) instead ([#96])
- **BREAKING:** Remove static `Helpers\Cache` helper — use injectable `CacheInterface` instead ([#96])
- **BREAKING:** `TemplateControllerInterface::handle()` now receives typed `TemplateContext` parameter ([#107])
- **BREAKING:** `ContextProviderInterface::provide()` now receives and returns `TemplateContext` ([#107])
- **BREAKING:** `ViewEngineInterface::render()` and `renderFirst()` now accept `array|object` context ([#107])
- Replace `\Tempest\get()` with injected `Container` in `HookDiscovery` and `TwigExtensionDiscovery` ([#96])

### Fixed

- **`ConfiguresPostType` and `ConfiguresTaxonomy` had never applied.** Both discoveries asked Tempest's `$class->implements()`, which is gated on `isInstantiable()` — and `Timber\Post` and `Timber\Term` declare protected constructors, so it answered false for every model a real theme has. A post type's `configurePostType()` was silently dropped: the rewrite slug it asked for was never registered and its archive answered 404. Both now test the interface in a way that does not care whether the class can be constructed
- **The starter and the demo never enqueued their built assets.** Both shipped a configured Vite build, a Tailwind entry point and a js-toolkit entry point, and served every page with no stylesheet and no script. The themes still rendered, so the omission read as a design choice rather than a hole. Vite also built to the package root, and only `theme/` is symlinked into the web root, so nothing it produced was reachable — it builds into `theme/dist` now, and `AssetHooks` enqueues from it through `ViteManifest`. CI builds the front-end before the integration run, and the starter's smoke test asserts the stylesheet and the script are on the page and that both resolve
- `ArchiveController` rendered a post type archive with no template of its own by throwing. It uses `renderFirst` now, like `SingleController` always did, so a post type falls back to the generic index instead of fataling
- **Project configuration could not read the project's `.env`.** The generated `wp-config.php` loaded `config/wordpress.config.php` and `config/wordpress.{env}.config.php` before it loaded `.env`, so a `define()` in either saw only real environment variables. That works in a container, where the environment comes from the orchestrator, and fails silently in ddev, where it comes from `.env` — the worse way round, since the failure appears only in development and looks like the config file was never loaded at all. `.env` and the `$env()` helper now run before the config files, which is what makes `config/*.config.php` usable for anything environment-shaped. The environment selecting the second file was read with `getenv()`, which `.env` does not populate, so it went through the same hole: a project setting `WP_ENVIRONMENT_TYPE` in `.env` loaded `wordpress.production.config.php` while the security-keys guard, a few lines below and reading the same variable through `$env()`, correctly saw development. Both now read it the same way. The tests execute the generated file rather than matching strings in it, because the ordering is the thing under test and only running it proves the order
- **Every install ran on guessable WordPress security keys.** With no `config/wordpress-salts.config.php` and no keys in the environment, the generated `wp-config.php` defined them as `'change-me-' . $salt . '-' . md5(__DIR__)` — derived from the web root path, which is predictable (`/var/www/html/web`, `/home/forge/example.com/web`). Authentication cookies and nonces signed with those keys can be forged. Nothing in the starter or the documentation said to replace them. The installer now generates real keys into `.env` on a first install, and `wp-config.php` refuses to serve a production request whose keys are missing or still placeholders
- **The framework's own discoverables never registered.** Discovery scanned the theme's app directory alone, so the five bundled `#[AsTwigExtension]` classes never reached Timber and the `#[AsCliCommand]` classes never reached WP-CLI. The starter's templates call `html_attributes()`, so a stock install answered every front-end request with `Twig\Error\SyntaxError: Unknown "html_attributes" function`
- **`*.config.php` files were never read.** `Kernel::registerConfigs()` used its defaults and the `boot()` array and stopped there, so `app/foehn.config.php`, `app/timber.config.php`, `app/acf.config.php`, `app/rest.config.php` and `app/render-api.config.php` did nothing. The starter shipped a `foehn.config.php` opting into seven cleanup and security hook classes, and none of them were applied
- `#[SkipDiscovery]` was ignored by the scanner. It matters now that packages are scanned: the fourteen `make:` command stubs carry real `#[AsPostType]` and `#[AsBlock]` attributes
- **`make:field-group`:** `--post-type`, `--taxonomy` and `--page-template` were ignored. The command substituted `['post_type', '==', 'post']`, but `FieldGroupStub` declares the map `['post_type' => 'post']`, so the replacement never matched and every generated field group stayed located on `post`
- **`make:controller`:** `--templates` with more than one template generated broken code. `'dummy-template'` was replaced everywhere, so the stub's `render('dummy-template', $context)` became `render(['a', 'b'], $context)`. The attribute now takes the list and the rendered template is set separately
- Fix a fatal error in the starter config and in four doc pages: `DiscoveryCacheStrategy` was imported from `Tempest\Core`, which no longer exists — it lives in `Tempest\Discovery`
- **Vite plugin:** Import `fast-glob` as a default export — `import { glob }` is not resolvable from real Node ESM and broke every consumer build
- **Vite plugin:** Rename the `vite-plugin-dts` option `rollupTypes` to `bundleTypes` and add the now-optional `@microsoft/api-extractor` peer; without both, v5 silently emitted per-file declarations and never wrote `dist/index.d.ts`
- **Vite plugin:** Correct `.oxfmtrc.json`, which used Biome option names (`indentWidth`, `lineWidth`, …) and was therefore ignored
- **Vite plugin:** Replace `__dirname` with `import.meta.dirname` in `vite.config.ts` for Vite's native config loader
- **Starter:** Remove a duplicate `createFakeViewEngine()` in `HeroBlockTest`, which shadowed the one in `Pest.php` and made the file fatal once Pest loaded `Pest.php`
- **Starter:** Exclude `vendor/**` from Vitest — the framework's Node-only editor test was being collected into the browser suite
- Remove unpaired `restore_error_handler()` / `restore_exception_handler()` calls in `DispatchHelperTest`; nothing installs handlers any more, so they popped PHPUnit's own and PHPUnit 13 fails the run as risky
- Replace `->method()->with()` with `->expects($this->once())->method()->with()` in `RenderApiTest`, deprecated in PHPUnit 13 and removed in 14
- Fix CI lint/analyse failures by removing obsolete `mago:install-binary` step ([3699f4d], [#111])

## [0.4.1] - 2026-02-10

### Changed

- **Installer:** Copy `.env.example` to `.env` during `composer install` ([aabcca6], [#86])
- **Starter:** Remove `.env` hook from DDEV config ([aabcca6], [#86])
- **Starter:** Remove project name from DDEV config to inherit from folder ([8525092])

### Fixed

- **Starter:** Add `index.php` file required by WordPress for standalone themes ([df9c428], [#85])
- **Starter:** Disable DDEV settings management ([df9c428], [#85])
- **Starter:** Refactor menus and image sizes to use dedicated classes ([df9c428], [#85])
- **Starter:** Fix `FoehnConfig` parameter name (`discoveryCacheStrategy`) ([df9c428], [#85])
- **Starter:** Fix controllers to implement `TemplateControllerInterface` ([df9c428], [#85])
- **Starter:** Fix taxonomies to extend `Timber\Term` ([df9c428], [#85])
- **Starter:** Add `front-page` to ArchiveController templates ([df9c428], [#85])
- **Starter:** Fix deprecated `post.preview` usage in card-post template ([df9c428], [#85])
- **Starter:** Move `index.php` to correct theme folder ([f1526a3])

## [0.4.0] - 2026-02-10

### Added

- Transform repository into a monorepo with three packages ([8919d50], [#83]):
  - `studiometa/foehn` — core framework (moved to `packages/foehn/`)
  - `studiometa/foehn-installer` — Composer plugin that generates WordPress web root, symlinks, and wp-config.php ([76b2597])
  - `studiometa/foehn-starter` — starter theme with models, taxonomies, hooks, controllers, templates, and DDEV config ([5e5e3a0])
- Add `GenericLoginErrors` security hook to prevent username enumeration on login ([7825d3a])
- Add `vlucas/phpdotenv` as framework dependency for `.env` file loading ([42e807c])
- Add monorepo split CI workflow to distribute packages to read-only repos on tag push ([770a69f], [3231e72])
- Add DDEV configuration for starter theme with automated WordPress setup ([0e43f8b])

### Changed

- **BREAKING:** Repository renamed from `studiometa/foehn` to `studiometa/foehn-framework` ([960692a])
- Starter theme follows documented conventions: `Controllers/`, `ContextProviders/`, `Taxonomies/` separate from `Models/`, `templates/` with `layouts/components/pages/` ([456f5d1], [f59a3ba])
- Update all documentation to use `templates/` directory and `Taxonomies/` namespace ([30e76fd])
- Update Mago guard rules: `Models` restricted to `Timber\Post`, new `Taxonomies` rule ([30e76fd])

## [0.3.0] - 2026-02-09

### Added

- Add `Cache::tags()` for tagged cache invalidation ([a149b4f], [#78])
- Add `DiscoveryLocation` and `WpDiscoveryItems` for location-aware discovery ([3d0cd34], [#79])
- Add `ClassScanner` for dedicated PSR-4 class scanning ([553efa7], [#79])
- Add `QueryFiltersConfig` and `QueryFiltersHook` for URL-based archive filtering ([b587b62], [#77])
- Add `QueryExtension` with `query_*` Twig helpers for filter UI building ([b587b62], [#77])
- Add Render API REST endpoint for cacheable template rendering via AJAX ([504fe3b], [#67])
- Make `FoehnConfig` discoverable via `app/foehn.config.php` ([4cab60d], [#80])
- Add API documentation for all config classes, discovery system, and view engine ([20246ee], [#80])
- Add configuration and custom discovery guides ([4a35cb2], [#80])
- Add comprehensive migration guide from wp-toolkit to Føhn ([6fa9cc8], [#81])

### Changed

- **BREAKING:** Align `WpDiscovery` interface with Tempest conventions: `discover()` now receives `DiscoveryLocation`, items managed via `WpDiscoveryItems` ([3d0cd34], [#79])
- **BREAKING:** Discovery cache format changed to location-grouped structure (`array<string, array<string, list<array>>>`) ([3d0cd34], [#79])

### Fixed

- Fix user config files being overwritten by framework defaults ([73d9443], [#74])

[0.5.0]: https://github.com/studiometa/foehn-framework/releases/tag/0.5.0
[0.4.1]: https://github.com/studiometa/foehn-framework/releases/tag/0.4.1
[aabcca6]: https://github.com/studiometa/foehn-framework/commit/aabcca6
[8525092]: https://github.com/studiometa/foehn-framework/commit/8525092
[f1526a3]: https://github.com/studiometa/foehn-framework/commit/f1526a3
[3231e72]: https://github.com/studiometa/foehn-framework/commit/3231e72
[0.4.0]: https://github.com/studiometa/foehn-framework/releases/tag/0.4.0
[8919d50]: https://github.com/studiometa/foehn-framework/commit/8919d50
[#83]: https://github.com/studiometa/foehn-framework/pull/83
[76b2597]: https://github.com/studiometa/foehn-framework/commit/76b2597
[5e5e3a0]: https://github.com/studiometa/foehn-framework/commit/5e5e3a0
[7825d3a]: https://github.com/studiometa/foehn-framework/commit/7825d3a
[42e807c]: https://github.com/studiometa/foehn-framework/commit/42e807c
[770a69f]: https://github.com/studiometa/foehn-framework/commit/770a69f
[0e43f8b]: https://github.com/studiometa/foehn-framework/commit/0e43f8b
[960692a]: https://github.com/studiometa/foehn-framework/commit/960692a
[456f5d1]: https://github.com/studiometa/foehn-framework/commit/456f5d1
[f59a3ba]: https://github.com/studiometa/foehn-framework/commit/f59a3ba
[30e76fd]: https://github.com/studiometa/foehn-framework/commit/30e76fd
[a149b4f]: https://github.com/studiometa/foehn-framework/commit/a149b4f
[#78]: https://github.com/studiometa/foehn-framework/pull/78
[b587b62]: https://github.com/studiometa/foehn-framework/commit/b587b62
[b587b62]: https://github.com/studiometa/foehn-framework/commit/b587b62
[#77]: https://github.com/studiometa/foehn-framework/pull/77
[504fe3b]: https://github.com/studiometa/foehn-framework/commit/504fe3b
[#67]: https://github.com/studiometa/foehn-framework/pull/67
[3d0cd34]: https://github.com/studiometa/foehn-framework/commit/3d0cd34
[#79]: https://github.com/studiometa/foehn-framework/pull/79
[20246ee]: https://github.com/studiometa/foehn-framework/commit/20246ee
[4cab60d]: https://github.com/studiometa/foehn-framework/commit/4cab60d
[4a35cb2]: https://github.com/studiometa/foehn-framework/commit/4a35cb2
[#80]: https://github.com/studiometa/foehn-framework/pull/80
[6fa9cc8]: https://github.com/studiometa/foehn-framework/commit/6fa9cc8
[553efa7]: https://github.com/studiometa/foehn-framework/commit/553efa7
[#81]: https://github.com/studiometa/foehn-framework/pull/81
[73d9443]: https://github.com/studiometa/foehn-framework/commit/73d9443
[#74]: https://github.com/studiometa/foehn-framework/pull/74
[0.3.0]: https://github.com/studiometa/foehn-framework/releases/tag/0.3.0

## [0.2.4] - 2026-02-09

### Fixed

- Include Timber global context (`site`, `theme`, `user`, etc.) in `TimberViewEngine` ([ce9e046], [#66])

[ce9e046]: https://github.com/studiometa/foehn-framework/commit/ce9e046
[#66]: https://github.com/studiometa/foehn-framework/pull/66
[0.2.4]: https://github.com/studiometa/foehn-framework/releases/tag/0.2.4

## [0.2.3] - 2026-02-05

### Added

- Add `BlockMarkupExtension` with `wp_block_start()`, `wp_block_end()` and `wp_block()` Twig functions for block pattern templates ([66d1b3d], [#63])
- Add `Cache` helper for WordPress transients with `remember()` pattern ([b68b0d1], [#64])
- Add `Log` helper for debug logging with PSR-3 style levels ([b68b0d1], [#64])

### Removed

- Remove `Validator` helper, recommend third-party packages instead ([0c1aa8f])

### Fixed

- Fix static analysis issues ([fd7f1d3])
- Fix VitePress build by escaping Twig syntax in docs ([1b5a3bb])

[66d1b3d]: https://github.com/studiometa/foehn-framework/commit/66d1b3d
[#63]: https://github.com/studiometa/foehn-framework/pull/63
[b68b0d1]: https://github.com/studiometa/foehn-framework/commit/b68b0d1
[#64]: https://github.com/studiometa/foehn-framework/pull/64
[0c1aa8f]: https://github.com/studiometa/foehn-framework/commit/0c1aa8f
[fd7f1d3]: https://github.com/studiometa/foehn-framework/commit/fd7f1d3
[1b5a3bb]: https://github.com/studiometa/foehn-framework/commit/1b5a3bb
[0.2.3]: https://github.com/studiometa/foehn-framework/releases/tag/0.2.3

## [0.2.2] - 2026-02-05

### Added

- Add `WebpackManifest` helper for enqueuing assets from `@studiometa/webpack-config` manifests ([0898fce], [#60])
- Bundle `studiometa/twig-toolkit` extension with `html_classes()`, `html_styles()`, `html_attributes()` and `{% element %}` tag ([13e1f56], [#62])

[0898fce]: https://github.com/studiometa/foehn-framework/commit/0898fce
[#60]: https://github.com/studiometa/foehn-framework/pull/60
[13e1f56]: https://github.com/studiometa/foehn-framework/commit/13e1f56
[#62]: https://github.com/studiometa/foehn-framework/pull/62
[0.2.2]: https://github.com/studiometa/foehn-framework/releases/tag/0.2.2

## [0.2.1] - 2026-02-05

### Added

- Add `WP` helper for typed access to WordPress globals (`WP::db()`, `WP::query()`, `WP::post()`, `WP::user()`) ([4f8bfc4], [#58])
- Add `Env` helper for environment detection (`Env::isProduction()`, `Env::isDevelopment()`, `Env::isDebug()`) ([4f8bfc4], [#58])
- Add `#[AsTwigExtension]` attribute for declarative Twig extension registration ([3fcddec], [#53])

### Fixed

- Fix `ViewEngineInterface` not registered in DI container for constructor injection ([c00db03], [#57])

[3fcddec]: https://github.com/studiometa/foehn-framework/commit/3fcddec
[#53]: https://github.com/studiometa/foehn-framework/pull/53
[c00db03]: https://github.com/studiometa/foehn-framework/commit/c00db03
[#57]: https://github.com/studiometa/foehn-framework/pull/57
[4f8bfc4]: https://github.com/studiometa/foehn-framework/commit/4f8bfc4
[#58]: https://github.com/studiometa/foehn-framework/pull/58
[0.2.1]: https://github.com/studiometa/foehn-framework/releases/tag/0.2.1

## [0.2.0] - 2026-02-05

### Added

- Add bundled Mago config for theme conventions enforcement ([cbeb0d9], [#52])
- Add enhanced CLI scaffolding commands with `--dry-run` support ([ca3fb53], [#51])
- Add `#[AsImageSize]` attribute for declarative image size registration with auto theme support ([d2eb7b6], [#47])
- Add `#[AsAcfOptionsPage]` attribute for ACF options pages with auto-discovery and `AcfOptionsService` helper ([ab97f93], [#49])
- Add `#[AsAcfFieldGroup]` attribute for non-block ACF field groups with simplified location syntax ([01bdd55], [#48])
- Add `#[AsMenu]` attribute for declarative navigation menu registration with auto-context injection ([c6dcd19], [#46])
- Add theme conventions documentation with directory structure, naming rules, and migration guide ([bef4275], [#43])
- Add `DisableBlockStyles` cleanup hook to dequeue Gutenberg block styles ([29747e3], [#44])
- Add built-in ACF field fragments for reusable field groups ([b64002d], [#45]):
  - `ButtonLinkBuilder`: link with style/size options
  - `ResponsiveImageBuilder`: desktop/mobile image variants
  - `SpacingBuilder`: padding top/bottom controls
  - `BackgroundBuilder`: color, image, and overlay background

### Changed

- **BREAKING:** Rename ViewComposer to ContextProvider ([70164d6], [#50])
  - `#[AsViewComposer]` → `#[AsContextProvider]`
  - `ViewComposerInterface` → `ContextProviderInterface`
  - `compose()` method → `provide()` method
  - `ViewComposerRegistry` → `ContextProviderRegistry`
  - `ViewComposerDiscovery` → `ContextProviderDiscovery`
  - `make:view-composer` CLI → `make:context-provider`

[cbeb0d9]: https://github.com/studiometa/foehn-framework/commit/cbeb0d9
[#52]: https://github.com/studiometa/foehn-framework/pull/52
[ca3fb53]: https://github.com/studiometa/foehn-framework/commit/ca3fb53
[#51]: https://github.com/studiometa/foehn-framework/pull/51
[70164d6]: https://github.com/studiometa/foehn-framework/commit/70164d6
[#50]: https://github.com/studiometa/foehn-framework/pull/50
[d2eb7b6]: https://github.com/studiometa/foehn-framework/commit/d2eb7b6
[#47]: https://github.com/studiometa/foehn-framework/pull/47
[ab97f93]: https://github.com/studiometa/foehn-framework/commit/ab97f93
[#49]: https://github.com/studiometa/foehn-framework/pull/49
[01bdd55]: https://github.com/studiometa/foehn-framework/commit/01bdd55
[#48]: https://github.com/studiometa/foehn-framework/pull/48
[c6dcd19]: https://github.com/studiometa/foehn-framework/commit/c6dcd19
[#46]: https://github.com/studiometa/foehn-framework/pull/46
[bef4275]: https://github.com/studiometa/foehn-framework/commit/bef4275
[#43]: https://github.com/studiometa/foehn-framework/pull/43
[29747e3]: https://github.com/studiometa/foehn-framework/commit/29747e3
[#44]: https://github.com/studiometa/foehn-framework/pull/44
[b64002d]: https://github.com/studiometa/foehn-framework/commit/b64002d
[#45]: https://github.com/studiometa/foehn-framework/pull/45
[0.2.0]: https://github.com/studiometa/foehn-framework/releases/tag/0.2.0

## [0.1.0] - 2026-02-04

### Changed

- REST routes without explicit permission now require `edit_posts` capability instead of just authentication ([2d95397], [#32])

### Added

- Add `debug` config option for logging discovery failures via `trigger_error()` ([72ab351], [#31])
- Add `ValidatesFields` trait for optional ACF block field validation ([f4854d1], [#24])
- Add `rest_default_capability` config option to customize default REST route permission ([2d95397], [#32])
- Add `discovery:warm` CLI command to pre-warm discovery cache during deployment ([a2cab24], [#30])
- Add security documentation for shortcode output escaping with comprehensive XSS prevention guide ([ab0445b], [#29])
- Transform ACF block fields via Timber's ACF integration ([3654df6], [!19]):
  - Transforms raw ACF values (image IDs, post IDs) to Timber objects
  - Supports: image, gallery, file, post_object, relationship, taxonomy, user, date_picker
  - Handles nested fields recursively (repeater, flexible_content, group)
  - New `acf_transform_fields` config option (default: true) to enable/disable
- Add `make:controller` command to scaffold template controllers ([aa08615], [#20])
- Add `make:hooks` command to scaffold hook classes ([aa08615], [#20])
- Add `--fields` flag to `make:acf-block` for auto-generating ACF fields ([aa08615], [#20])
- Add `VideoEmbed` helper and Twig extension for YouTube/Vimeo URL transformation ([3b34300], [#18])
- Add opt-in reusable hook classes for common WordPress patterns ([ff3b2b3], [#13]):
  - Cleanup: `CleanHeadTags`, `CleanContent`, `CleanImageSizes`, `DisableEmoji`, `DisableFeeds`, `DisableOembed`, `DisableGlobalStyles`
  - Security: `SecurityHeaders`, `DisableVersionDisclosure`, `DisableXmlRpc`, `DisableFileEditor`, `RestApiAuth`
  - GDPR: `YouTubeNoCookieHooks`
- Add `hooks` config option in `Kernel::boot()` to activate opt-in hook classes ([ff3b2b3], [#13])
- Add `#[AsTimberModel]` attribute for Timber class map registration without post type/taxonomy registration ([c3ebb04], [#11])
- Auto-initialize Timber in Kernel bootstrap with `timber_templates_dir` config option ([c3bf7df], [#12])
- Add `hierarchical`, `menuPosition`, `labels`, `rewrite` (array|false|null) to `#[AsPostType]` ([b544790], [#7])
- Add `labels`, `rewrite` (array|false|null) to `#[AsTaxonomy]` ([b544790], [#7])
- Add WordPress function stubs for unit testing `apply()` code paths ([812aa6a], [#7])
- Add `discover()` and `apply()` tests for all 11 discovery classes — 359 tests, 1067 assertions ([d7cbe4c], [#7])
- Add discovery cache for production performance ([ffc7536], [#2])
- Add VitePress documentation with guides and API reference ([f69a8b9], [#3])
- Document `#[AsTimberModel]`, `timber_templates_dir`, `hooks` config, and built-in hooks ([a7bde4e], [!17])
- Document `VideoEmbed` helper, ACF field transformation, and `make:controller`/`make:hooks` CLI commands ([3b34300], [!21])
- Add GitHub Pages deployment workflow ([e1178b9], [#3])

### Changed

- Decouple discoveries from Tempest's `Discovery` interface, replace with `WpDiscovery` + `IsWpDiscovery` ([748aace], [#7])
- Rewrite `DiscoveryRunner` to own the full lifecycle: class scanning via Composer PSR-4, phased `apply()` at correct WP hooks ([509febf], [#7])
- Tempest is now used only for the DI container, not for discovery ([509febf], [#7])

### Fixed

- Fix discovery system conflicts with Tempest lifecycle — double discovery, incorrect timing, uninitialized properties ([748aace], [#7])
- Fix root path passed to Tempest causing "Could not locate composer.json" error ([26cb117], [#5])

[c3ebb04]: https://github.com/studiometa/foehn-framework/commit/c3ebb04
[c3bf7df]: https://github.com/studiometa/foehn-framework/commit/c3bf7df
[748aace]: https://github.com/studiometa/foehn-framework/commit/748aace
[26cb117]: https://github.com/studiometa/foehn-framework/commit/26cb117
[509febf]: https://github.com/studiometa/foehn-framework/commit/509febf
[b544790]: https://github.com/studiometa/foehn-framework/commit/b544790
[812aa6a]: https://github.com/studiometa/foehn-framework/commit/812aa6a
[d7cbe4c]: https://github.com/studiometa/foehn-framework/commit/d7cbe4c
[ffc7536]: https://github.com/studiometa/foehn-framework/commit/ffc7536
[f69a8b9]: https://github.com/studiometa/foehn-framework/commit/f69a8b9
[e1178b9]: https://github.com/studiometa/foehn-framework/commit/e1178b9
[#2]: https://github.com/studiometa/foehn-framework/pull/2
[#3]: https://github.com/studiometa/foehn-framework/pull/3
[#5]: https://github.com/studiometa/foehn-framework/pull/5
[#7]: https://github.com/studiometa/foehn-framework/pull/7
[#11]: https://github.com/studiometa/foehn-framework/pull/11
[#12]: https://github.com/studiometa/foehn-framework/pull/12
[#13]: https://github.com/studiometa/foehn-framework/pull/13
[#18]: https://github.com/studiometa/foehn-framework/pull/18
[#20]: https://github.com/studiometa/foehn-framework/pull/20
[!19]: https://github.com/studiometa/foehn-framework/pull/19
[ff3b2b3]: https://github.com/studiometa/foehn-framework/commit/ff3b2b3
[3654df6]: https://github.com/studiometa/foehn-framework/commit/3654df6
[3b34300]: https://github.com/studiometa/foehn-framework/commit/3b34300
[aa08615]: https://github.com/studiometa/foehn-framework/commit/aa08615
[aa08615]: https://github.com/studiometa/foehn-framework/commit/aa08615
[aa08615]: https://github.com/studiometa/foehn-framework/commit/aa08615
[a7bde4e]: https://github.com/studiometa/foehn-framework/commit/a7bde4e
[!17]: https://github.com/studiometa/foehn-framework/pull/17
[!21]: https://github.com/studiometa/foehn-framework/pull/21
[3b34300]: https://github.com/studiometa/foehn-framework/commit/3b34300
[ab0445b]: https://github.com/studiometa/foehn-framework/commit/ab0445b
[#29]: https://github.com/studiometa/foehn-framework/pull/29
[a2cab24]: https://github.com/studiometa/foehn-framework/commit/a2cab24
[#30]: https://github.com/studiometa/foehn-framework/pull/30
[2d95397]: https://github.com/studiometa/foehn-framework/commit/2d95397
[2d95397]: https://github.com/studiometa/foehn-framework/commit/2d95397
[#32]: https://github.com/studiometa/foehn-framework/pull/32
[f4854d1]: https://github.com/studiometa/foehn-framework/commit/f4854d1
[#24]: https://github.com/studiometa/foehn-framework/pull/33
[72ab351]: https://github.com/studiometa/foehn-framework/commit/72ab351
[#31]: https://github.com/studiometa/foehn-framework/pull/31
[0.1.0]: https://github.com/studiometa/foehn-framework/releases/tag/0.1.0
[#85]: https://github.com/studiometa/foehn-framework/pull/85
[df9c428]: https://github.com/studiometa/foehn-framework/commit/df9c428
[#86]: https://github.com/studiometa/foehn-framework/pull/86
