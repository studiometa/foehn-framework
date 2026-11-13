# Changelog

All notable changes to this project will be documented in this file. Each pull request adds a fragment in `.changelog/`; the Release workflow folds them in here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 0.8.1 - 2026-11-13

### Added

- `studiometa/foehn-installer` copies the content of the project `public/` directory into the generated web root on `composer install`, dotfiles included. Put the files that must be at the document root (`.user.ini`, `robots.txt`, `favicon.ico`, `.well-known/`) there. `extra.foehn.public-dir` sets another directory. The installer does not copy to `index.php`, `wp-config.php`, the WordPress directory or `wp-content/`, and the next install removes a file deleted from `public/`. A Docker build must copy `public/` before `composer install`. ([#257])
- `wp foehn verify --profile=production` has a `public-files` check: it fails when a file of the public directory is missing from the web root or is different there. ([#257])
- The starter has an empty `public/` directory. ([#257])
- `PageCacheConfig::$storage`: with `PageCacheStorage::Bucket`, the page cache stores pages in the `humanmade/s3-uploads` bucket under `cache/pages/`, and the nginx snippet from `wp foehn cache:config --server=nginx` serves hits from the bucket. All instances of a site then read and purge one cache. Use it only on a site with more than one instance: hits are slower, only nginx serves them, the bucket must allow anonymous reads, and each deploy must run `wp foehn cache:clear`. ([#258])
- The starter has a seeder, `App\Seeders\MenusSeeder`: `wp foehn seed` creates the header, footer and legal menus with a Home link when they are missing, and assigns them to their locations. A second run creates nothing. ([#260])
- The demo shows more of Føhn on its pages: an interactive block on the Interactivity API, blocks with the `gallery`, `posts` and `file` controls and with `parent`, a block pattern, the hero, section and callout blocks and the reading-time binding in seeded content, a journal with categories, tags and a password-protected post, project tags, a film embedded with `video_embed()`, `ContentImageHooks`, testimonial meta, `Model::query()` in a controller, a `#[AsTimberModel]` for attachments, a `#[AsTwigExtension]`, the twig-toolkit helpers, the query helpers, a `#[AsShortcode]`, `#[AsRestRoute]` routes, a tagged cache, an `#[AsAcfFieldGroup]` and the `AGENTS.md` block of `wp foehn agents-md`. ([#261])
- The demo runs Action Scheduler. Saving a project dispatches a `#[AsJob]` that requests the image transforms of its plates, so the first visitor does not wait for them. ([#261])
- The demo schedules two `#[AsCron]` jobs: one on a daily interval that warms the plates of every project, and one on the cron expression `0 0 1 1 *` that empties the page cache at midnight on 1 January. Its seeded site timezone is Europe/Paris, so the expression runs at midnight in Paris. ([#261])
- The demo has two commands of its own under `wp app`: `projects:stats`, which only reads, and `projects:reorder`, which declares `writes: true` and so runs in production only with `--dry-run` or `--force`. ([#261])
- The demo turns on `SecurityHeaders`, `DisableFileEditor`, `RestApiAuth` and `CleanContent`. ([#261])
- The demo has a `foehn.local.config.php` that starts from `foehn.config.php` and turns on `debug`, `recordErrors` and `traceRequests`, and its footer prints the environment from `Env::get()`. ([#261])
- The demo's page cache sets `cacheControl`, repeats the `SecurityHeaders` headers in `responseHeaders`, and warms the pages a purge removes with `warmAfterPurge` and `warmExclude`. `examples/page-cache.production.config.php` shows bucket storage, and the bucket smoke test runs that file and checks that editing a project purges its page. ([#261])
- The demo has a `public/` directory with `robots.txt`, `favicon.ico` and `.well-known/security.txt`. Its Docker image copies it before `composer install` and into the final stage, so `wp foehn verify --profile=production` passes its `public-files` check. Its ddev nginx serves `/.well-known/`. ([#261])
- The demo has a `db-export.config.php`, and its database dump is made with `wp foehn db:export`. `database/restore.sh` sets the admin password after the import, since an export keeps no password hash. ([#261])

### Changed

- The starter `AGENTS.md` lists `composer test:smoke` in its checks, and tells a coding agent to put the files of the document root in `public/`. ([#260])
- The starter guide has a release checklist for production: `WP_ENVIRONMENT_TYPE=production`, `composer install --no-dev`, `npm run build`, `wp foehn cache:config --server=nginx --write`, `wp foehn cache:clear` (necessary with bucket storage), a real cron and `wp foehn verify --profile=production`. It also lists every place of the theme name to change when you rename the theme. ([#260])
- The starter `.env.example` sets `DISABLE_WP_CRON=false`, so WordPress cron, `#[AsCron]`, `#[AsJob]` and the page-cache warm-up run in a new local project. Production keeps the default (`true`) and a real cron. Add the line to the `.env` of a local project created from an earlier starter. ([#260])
- The starter `theme/app/page-cache.config.php` names the page cache options a project may need, with links to the guide: `browserMaxAge`, `cacheControl`, `responseHeaders`, `warmAfterPurge`, `warmSitemap`, `warmExclude` and bucket `storage`. The active values do not change. ([#260])
- The starter enables `DisableFileEditor` in `theme/app/foehn.config.php`: the admin has no theme or plugin file editor. A comment names `SecurityHeaders` and `RestApiAuth`, which stay opt-in. ([#260])
- The starter no longer ships the framework smoke suites in `tests/smoke/`: a created project keeps `tests/smoke/run.sh` and a short Pest test, `SiteTest.php`, that `composer test:smoke` runs against the started ddev site (home page with no PHP error, active theme). The starter `composer.json` no longer has the `Studiometa\Foehn\Smoke\` autoload. A project created from an earlier starter can delete the other files in `tests/smoke/` and that autoload entry. ([#260])
- The demo requires `studiometa/foehn-acf` and the free ACF plugin. Its projects have a `#[AsAcfFieldGroup]` with the `ButtonLinkBuilder` fragment, and the release moves and checks the demo's `studiometa/foehn-acf` pin with the other ones. ([#261])
- The demo's series archives are at `/series/<slug>/` and its project tag archives at `/project-tags/<slug>/`. `/projects/series/<slug>/` was a 404. ([#261])
- After a deploy to Fly, the demo's workflow runs one pass of the real cron, warms the page cache with `wp foehn cache:warm --sync` and runs `wp foehn verify --profile=production`, which fails the job on a failed check. ([#261])
- The demo's README and its guide page list every feature the demo shows in the same table, and have the same "Try the CLI" and "Debugging" sections, with the admin cache controls. ([#261])

### Fixed

- In a project created from the starter, `ddev start` now installs WordPress (`admin` / `admin`) and activates the theme when WordPress is not installed, and runs `composer install` when `vendor/` is missing, as the documentation says. The hook is in `.ddev/config.yaml`. A project created from an earlier starter can copy the `hooks:` block of the starter `.ddev/config.yaml`. ([#260])
- The `wp_interactive`, `wp_context`, `wp_directive`, `wp_bind`, `wp_on`, `wp_class` and `wp_text` Twig functions and the `video_embed`, `video_id`, `video_platform` and `video_is_supported` functions and filters are registered. Their extensions had no `#[AsTwigExtension]`, so a template that used them failed with "Unknown function". ([#261])
- `Model::query()->get()` and `Model::all()` return a list of posts. They returned Timber's collection object, so PHP threw a `TypeError` on the `array` return type at every call. ([#261])
- `wp_context()` and the `wp_context` filter encode `<`, `>` and `&` like WordPress does. A `>` in a value made `wptexturize` rewrite the rest of a block rendered in post content, and the block broke. ([#261])
- The view script of a block with `interactivity: true` depends on `@wordpress/interactivity`, so WordPress puts that module in the import map. Before, `import { store } from '@wordpress/interactivity'` failed on a page with no core interactive block. ([#261])
- The Twig extensions guide and the `foehn` skill name the twig-toolkit tag `{% html_element %}` … `{% end_html_element %}`. They said `{% element %}`, which Twig does not know. ([#261])
- A command with `writes: true` that does not run in production names itself as WP-CLI registers it: `` `wp app import:products` `` for a project command and `` `wp foehn-acf make:acf-block` `` for a command of foehn-acf. It said `wp foehn <name>` for each command. ([#261])
- The demo's footer links to a Legal page that exists, its banner and page-size settings change the site, and it removes the inline global styles like the starter. ([#261])

[#257]: https://github.com/studiometa/foehn-framework/pull/257
[#258]: https://github.com/studiometa/foehn-framework/pull/258
[#260]: https://github.com/studiometa/foehn-framework/pull/260
[#261]: https://github.com/studiometa/foehn-framework/pull/261

## 0.8.0 - 2026-11-12

### Added

- `wp foehn inspect` reports the project model from what discovery found: post types with their Timber model, `#[AsPostMeta]` fields and taxonomies, taxonomies, blocks, patterns, block bindings, template controllers, context providers, menus, image sizes, rewrite rules, REST routes, `wp foehn` commands, active plugins with their versions, theme supports and the Vite entry points. With no section it prints a summary, the names in each section; `wp foehn inspect <section>...` prints the named sections in full, `--all` prints every section, and `--format=json` gives the report on one line to a program or a coding agent. See [Project Inspection](https://studiometa.github.io/foehn-framework/guide/cli-commands#project-inspection) ([#218])
- `ViteManifest::entries()` lists the entry points of the build, and `ViteManifest::devServer()` gives the dev server URL while `npm run dev` runs ([#218])
- Every `make:*` command, the `foehn-acf` ones included, takes `--format=json`. It prints the files with their status (`created`, `overwritten`, `exists`, `would-create`, `would-overwrite`) and the next steps. With `--dry-run`, it also prints the contents that would be written. See [Scripts and Coding Agents](https://studiometa.github.io/foehn-framework/guide/cli-commands#scripts-and-coding-agents) ([#220])
- `ClassFileGenerator::plan()` tells what `ClassFileGenerator::write()` would do, without touching the disk ([#220])
- A `make:*` command puts its class next to the classes of the same kind the project declares: in their directory, in their namespace and under the parent class most of them extend. With none, it uses its default directory. See [Where a Generated Class Goes](https://studiometa.github.io/foehn-framework/guide/cli-commands#where-a-generated-class-goes) ([#220])
- `make:post-type` takes `--meta=<key>[:<type>],…`, which declares each field with `#[AsPostMeta]` and writes a typed accessor for it, and `--rewrite-slug=<slug>` ([#220])
- The `make:post-type` and `make:model --post-type` notes name the Twig files to create where the theme keeps its views of the same kind (`templates/pages/single-<name>.twig` in the starter), and the controller that renders each one ([#220])
- `wp foehn inspect settings` lists each `#[AsSettingsPage]` with the options it stores: option name, type, default and sanitizer. The sanitizer runs under WP-CLI too, so `wp option update` saves a value as the admin form does ([#219])
- The `blocks` section of `wp foehn inspect` gives each block attribute with its type, default and allowed values ([#219])
- The `templates` section of `wp foehn inspect` gives, for each WordPress template a controller claims, the controller that renders it and the ones it overrides. A new `views` section gives, for each Twig file of the theme, the context providers that match it in the order they run. Both list the context keys known without rendering: the keys Føhn and Timber always set, and the keys a controller or provider sets with a literal `with()` call. See [Template context](https://studiometa.github.io/foehn-framework/guide/cli-commands#template-context) ([#219])
- `wp foehn skill` lists the Agent Skills of the installed packages, with their package and installed version, and prints one `SKILL.md` (`wp foehn skill foehn`) or one reference file (`--reference=<file>`, `--list-references`). It reads every Composer package with a `skills/<name>/SKILL.md`, third-party packages included, and `node_modules/@studiometa/foehn-vite-plugin/skills`. See [Agent Skills](https://studiometa.github.io/foehn-framework/guide/cli-commands#agent-skills) ([#221])
- The starter ships an `AGENTS.md`, and a `CLAUDE.md` that imports it. It tells a coding agent to read the installed skills, run `wp foehn inspect` and preview `make:*` commands with `--dry-run --format=json` before it writes code. A project made from an earlier starter can copy it from `packages/starter/AGENTS.md` ([#221])
- The `foehn` skill starts with the steps to follow before writing code: `wp foehn inspect`, then `make:*` with `--dry-run`, then `wp foehn discovery:clear` ([#221])
- `wp foehn db:export [<file>|-] [--from=<date>]` writes a light, anonymized, gzipped dump of the database, to import on a local copy of the site. Users, user meta, comments and WooCommerce orders are anonymized, transients and old revisions are left out, and a table that no rule covers is exported without rows and listed in a warning. A project declares its own tables in `app/db-export.config.php` with `DatabaseExportConfig`. See [Database Export](https://studiometa.github.io/foehn-framework/guide/database-export) ([#227])
- Seeders: a class with `#[AsSeeder(order:, description:)]` that implements `SeederInterface` writes the sample content of a site in `run()`. `wp foehn seed` runs every seeder by `order` (then by class name), `--class=<name>` runs one, and `--dry-run` lists them. The command stops with exit status 1 at the first seeder that throws. A seeder must be idempotent. See [Seeders](https://studiometa.github.io/foehn-framework/guide/seeders) ([#226])
- `Upsert::post($type, $slug, $data, $meta)` updates the post of this type that has this slug, or creates it, so a seeder can run again without duplicating content ([#226])
- `wp foehn make:seeder <name>` generates a seeder in `app/Seeders/`, with `--order`, `--description`, `--dry-run` and `--format=json` ([#226])
- `wp foehn inspect` has a `seeders` section with the class, order and description of each seeder, in run order ([#226])
- The starter `AGENTS.md` tells coding agents to put sample content and menus in a seeder and run `wp foehn seed`, never to create them only in the database. A project made from an earlier starter can copy the section from `packages/starter/AGENTS.md` ([#226])
- `wp foehn inspect` starts its summary with a `problems` section, and `wp foehn inspect problems` gives the `class`, `attribute`, `reason` and `fix` of each one: the classes discovery skipped, and a post type or taxonomy that names a taxonomy or post type no attribute declares and WordPress does not register. See [Problems and Errors](https://studiometa.github.io/foehn-framework/guide/troubleshooting) ([#237])
- `wp foehn discovery:status` lists the classes discovery skipped, with the reason and the fix ([#237])
- `wp foehn errors` prints the last errors of the site in a few lines: uncaught exceptions, fatal errors, and the warnings and notices of the theme's own code, with the file and line, the Twig template and line, a short stack of the theme's frames, the URL, the WordPress template, the controller, the view and its context keys. `--limit`, `--level`, `--since`, `--format=json` and `--clear` filter and empty the log. See [The error log](https://studiometa.github.io/foehn-framework/guide/troubleshooting#the-error-log) ([#238])
- Errors are recorded in the `local` and `development` environments, to `wp-content/cache/foehn/errors/errors.jsonl` (3 files of 256 KB at most). `FoehnConfig::$recordErrors` (`'record_errors'` in the array config) turns recording on in another environment, or off ([#238])
- `wp foehn trace [<path>|<id>|last|list]` prints what a front-end request ran: the status, time and query count, the template files WordPress tried and the one it found, the template controller, each Twig view with the views `renderFirst()` tried, its context providers and its context keys, the blocks, and the errors of the request. With a path, the command requests the page on the site; `--format=json` prints every field. ([#239])
- In the `local` and `development` environments, each front-end request sends its id in the `X-Foehn-Trace` response header and stores its trace in `wp-content/cache/foehn/traces` (the last 100). `FoehnConfig::$traceRequests` (`trace_requests`) turns tracing on or off in any environment. ([#239])
- `wp foehn errors --request=<id>` prints only the errors of one traced request. An error entry now carries the id of its request in `requestId`. ([#239])
- `@studiometa/foehn-vite-plugin` turns on Vite 8's `server.forwardConsole` in dev, unless `vite.config.js` sets it: the page sends its console errors and warnings, uncaught errors and unhandled promise rejections to the dev server. The plugin also writes them to `browser-errors.jsonl` in `outDir` (`theme/dist/` in the starter), with the source file and line mapped through the source map, in at most 3 files of 256 KB. Set `server.forwardConsole: false` to turn it off. Vite 6 and 7 send nothing. ([#241])
- `wp foehn errors --browser` prints the entries of that browser log, with the `--limit`, `--level=error,rejection,console`, `--since`, `--format=json` and `--clear` options. ([#241])
- `wp foehn log` reads the WordPress debug log (`wp-content/debug.log`, or the path `WP_DEBUG_LOG` holds) as entries: level, message, file and line, and the stack trace of a fatal error. Entries that repeat are one group with a count and the time of the first and the last. It prints the last 20 groups; `--level`, `--since`, `--grep`, `--limit` and `--format=json` select and format them. When `WP_DEBUG_LOG` is off, it says how to turn it on: `WP_DEBUG=true` in `.env`. ([#240])
- `#[AsCliCommand(writes: true)]` marks a command that changes content or files. When `wp_get_environment_type()` is `production`, such a command does not run unless `--force` or `--dry-run` is given, and the error says why and how. A project command that sets it must declare `[--force]` in its `longDescription`. See [Production Environment](https://studiometa.github.io/foehn-framework/guide/cli-commands#production-environment) ([#243])
- `wp foehn salts:generate --dry-run` names the file that would get the keys and the keys it would replace, and writes nothing ([#243])
- `wp foehn inspect templates` gives each template a `url` to test it with: the newest published post of its type, the post type archive, the term with the most posts, the front page, the posts page, a search, a missing page, or the author or month of the newest post. A template that no such URL renders has `url: null` and a `noUrl` reason, such as `Its sample URLs render 'single-*' instead.` or, for a pattern like `tax-*`, `Føhn gives no request a template name like this.`. See [Test URLs](https://studiometa.github.io/foehn-framework/guide/cli-commands#test-urls) ([#242])
- `wp foehn agents-md` writes the Føhn block in the project's `AGENTS.md`, between `<!-- foehn:begin -->` and `<!-- foehn:end -->`: the first commands an agent runs (inspect, `make:*` with `--dry-run`, seeders, `errors`, `log`, `trace`) and one line for each installed skill and reference file, with the command that prints it. It changes nothing outside the markers, adds the block at the end of a file without them, creates a missing file, and writes nothing when the block is current. Run it after `composer update` or `npm update` and commit the change. Like the other commands that write, it refuses to run in production without `--force` or `--dry-run`. See [AGENTS.md Block](https://studiometa.github.io/foehn-framework/guide/cli-commands#agents-md-block) ([#244])
- `cliNamespace` in `FoehnConfig` (`'cli_namespace'` in the array config) sets the WP-CLI command the `#[AsCliCommand]` classes of the project go under. The default is `app`. A name with a space, an empty name or `foehn` throws an `InvalidArgumentException`. See [Command Namespace](https://studiometa.github.io/foehn-framework/guide/cli-commands#command-namespace) ([#248])
- A package declares the WP-CLI namespace of its `#[AsCliCommand]` classes with `"extra": {"foehn": {"cli-namespace": "<name>"}}` in its `composer.json`. Without it, the namespace is the package name without its vendor: `acme/shop-tools` gives `wp shop-tools <name>`. A namespace that is not one word of lowercase letters, digits, `-` and `_`, or that is `foehn`, is reported in `wp foehn inspect problems` and the commands of the package are not registered. See [The namespace of a package](https://studiometa.github.io/foehn-framework/guide/cli-commands#the-namespace-of-a-package) ([#248])
- `#[AsCron]` accepts a cron expression with five fields, for example `#[AsCron('0 0 * * *')]` for every day at midnight. The expression runs in the site timezone (`wp_timezone()`), not in UTC as with `as_schedule_cron_action()`. An invalid expression skips the class and records a discovery problem. ([#249])
- `#[AsCron]` schedules a job again when its interval or its expression changed, or when an interval became an expression. Before, a changed schedule kept the old one until somebody removed the pending action by hand. ([#249])
- `PageCacheConfig` takes `cacheControl`, the whole `Cache-Control` value of a page-cache hit. The drop-in sends it and `wp foehn cache:config --server=nginx` writes it, in place of `public, max-age=<browserMaxAge>, must-revalidate`. A site that migrates from WP Rocket can keep `no-cache, no-store, must-revalidate` ([#251])
- `PageCacheConfig` takes `responseHeaders`, static headers that the drop-in and the generated nginx snippet send on every hit. List there the headers your server sets at server level, such as the `X-Frame-Options`, `X-XSS-Protection` and `X-Content-Type-Options` of the Laravel Forge template: the cache location of the nginx snippet has its own `add_header` lines, so nginx drops the server-level ones on a hit. See [The headers of a hit](https://studiometa.github.io/foehn-framework/guide/page-cache#the-headers-of-a-hit) ([#251])
- `wp foehn cache:config` refuses a `cacheControl` or `responseHeaders` value it cannot write safely, for nginx and for Apache, and `wp foehn cache:status` warns about it: the drop-in serves no page while such a value is set ([#251])
- `PageCacheConfig::$warmAfterPurge` queues the warm-up of the pages that a purge removed, through Action Scheduler: one `WarmUrl` job for each purged URL after a content change, and one job that warms every sitemap URL after a full flush. Off by default. ([#250])
- `PageCacheConfig::$warmSitemap` names the sitemap or sitemap index that `wp foehn cache:warm` and the warm-up after a flush read, for example `https://example.com/sitemap_index.xml` on a site where Rank Math or Yoast turn the core sitemap off. ([#250])
- `PageCacheConfig::$warmExclude` lists regular expressions, matched anywhere in the full URL, of URLs that are never warmed. ([#250])

### Changed

- **Breaking:** a `make:*` command exits with status 1 when the file already exists and `--force` is not given, also with `--dry-run`. Before, it printed an error and exited with status 0. A script that runs a generator twice must pass `--force` or expect the failure ([#220])
- **Breaking:** `ClassFileGenerator::write()` returns a `GenerationStatus` instead of a `bool` ([#220])
- **Breaking:** the `make:*` commands take a `Scaffolder` instead of a `ClassFileGenerator` in their constructor. `make:post-type` and `make:model` also take `ProjectConventions` ([#220])
- **Breaking:** `make:post-type` writes `app/Models/<Name>.php`, a class named after the post type (`Event`, not `EventPost`) that extends `Studiometa\Foehn\Models\Post`, instead of `app/PostTypes/<Name>Post.php` extending `Timber\Post` ([#220])
- A generated class imports its parent class (`extends Post`, not `extends \Timber\Post`) ([#220])
- `make:*` commands print their next steps one line each ([#220])
- `@studiometa/foehn-vite-plugin` publishes its `skills/` directory to npm, so its skill is in `node_modules/` after `npm install` ([#221])
- The demo writes its portfolio with six seeders in `theme/app/Seeders/` instead of `database/seed.php`. `database/restore.sh` and the Fly entrypoint run `wp foehn seed` ([#226])
- A class whose attribute contract is broken (`#[AsPostType]` on a class that does not extend `Timber\Post`, `#[AsBlock]` without `BlockInterface`, an invalid `#[AsPostMeta]`…) is skipped and recorded instead of stopping discovery with an exception, so every other class still registers. The records are kept in the discovery cache, and `debug` raises each one as a PHP warning instead of logging only the failures of opt-in hooks. A project that relied on the exception must now read `problems` ([#237])
- A class that `#[AsCliCommand]`, `#[AsSeeder]`, `#[AsTwigExtension]`, `#[AsCron]` or `#[AsJob]` skipped, and a class in `hooks` that does not exist, are now recorded as problems instead of being skipped without a word ([#237])
- `TimberViewEngine` throws `Studiometa\Foehn\Views\ViewRenderingFailed`, still a `RuntimeException`. A Twig error keeps its message, its template and its line, names the view that was asked for, and is the previous exception. A missing view names every view tried, the directories Timber searched and the file to create ([#237])
- `TimberViewEngine::exists()` asks Timber's loader, so it finds a template exactly when rendering finds it ([#237])
- `wp foehn seed`, every `make:*` command (the `foehn-acf` ones included) and `wp foehn salts:generate` refuse to run in production without `--force` or `--dry-run`. A deploy script or a production task that runs one of them must add `--force`. `seed` takes a new `--force` option. On `make:*` and `salts:generate`, `--force` still also overwrites a file or replaces keys ([#243])
- The starter's `AGENTS.md` ships with the generated block. The block replaces the "Before you write code" and "When WordPress cannot boot" sections. A project made from an earlier starter runs `wp foehn agents-md`, which adds the block at the end of its `AGENTS.md`, and can then remove the steps the block repeats ([#244])
- **Breaking:** a `#[AsCliCommand]` class of the project registers as `wp app <name>` instead of `wp foehn <name>`. There is no alias for the old name: change the cron jobs, deploy scripts and runbooks that call `wp foehn <name>` for a project command to call `wp app <name>`, or set `cliNamespace` to keep another name ([#248])
- **Breaking:** the commands of `studiometa/foehn-acf` register as `wp foehn-acf make:acf-block`, `wp foehn-acf make:field-group` and `wp foehn-acf make:options-page` instead of `wp foehn <name>`. The commands of another package that registered under `wp foehn` go under the namespace of that package. Only the commands of Føhn stay under `wp foehn`. There is no alias for the old names: change the scripts that call them ([#248])
- When two classes declare the same WP-CLI command, neither is registered, and each `wp` run prints a warning that names both classes. Before, the class registered last replaced the other ([#248])
- `wp foehn inspect cli-commands` gives the full line that runs each command, such as `wp app import:products`, `wp foehn inspect` or `wp foehn-acf make:acf-block`, instead of `foehn <name>` ([#248])
- The discovery cache version is `v2`: each command item records the namespace of its package. The first request after the update scans again, and `wp foehn discovery:generate` writes the new cache ([#248])
- The first argument of `#[AsCron]` is now `schedule`, not `interval`. Projects that write `#[AsCron(interval: …)]` must write `#[AsCron(schedule: …)]`. `AsCron::$intervalSeconds` is `null` for a cron expression; `AsCron::$expression` holds the expression. ([#249])
- **Breaking:** `studiometa/foehn` and `studiometa/foehn-acf` are now released under the GNU General Public License v3.0 or later (`GPL-3.0-or-later`), in place of the MIT License. A project that distributes code that includes them must follow the GPL. A site that is only hosted does not distribute it. Versions released before this one stay MIT. ([#254])
- `studiometa/foehn-starter`, `studiometa/foehn-installer` and `@studiometa/foehn-vite-plugin` stay MIT. `studiometa/foehn`, `studiometa/foehn-acf`, `studiometa/foehn-installer` and `@studiometa/foehn-vite-plugin` now ship their own `LICENSE` file. ([#254])
- The `# policy:` hash of the generated nginx and Apache snippets includes `cacheControl` and `responseHeaders`, so `wp foehn cache:status` reports an installed snippet as outdated until you run `wp foehn cache:config --write` again ([#251])
- `wp foehn cache:warm` no longer requests URLs that the page cache does not store: another host, a query string that the cache does not key, or a path under `excludedPaths`. ([#250])

### Removed

- **Breaking:** `WpCli::prompt()`, `WpCli::previewGeneratedFile()` and `WpCli::reportFileExists()`. The `Scaffolder` service previews and reports generated files ([#220])

### Fixed

- The starter's ddev image builds WP-CLI as the runtime image does, with the `php-cli-tools` and `react/promise` versions that PHP 8.5 does not deprecate. `ddev wp` no longer prints a `Deprecated:` line ahead of its output, so `ddev wp foehn inspect --format=json` gives valid JSON. A project made from an earlier starter can copy `.ddev/web-build/prepend.Dockerfile.wp-cli` and `.ddev/web-build/Dockerfile.wp-cli`, then run `ddev restart` ([#218])
- `make:block --interactive` names the view script Føhn loads, `assets/js/blocks/<name>.js`, instead of `assets/scripts/blocks/<name>/view.js`, which nothing loads ([#220])
- `make:menu` and `make:image-size` give Twig hints that are valid Twig: `menus['<location>'].items` and `post.thumbnail.src('<size>')`. A class call such as `PrimaryMenu::get()` is now named as PHP ([#220])
- When several `#[AsTemplateController]` classes claim the same template, the one with the lowest `priority` renders it, as the attribute documents; on a tie, the one discovered last. Wildcard patterns are tried in `priority` order. Before, `priority` was ignored and the controller discovered last always won ([#219])
- The context provider guide and API pages said that providers match WordPress template names such as `single-product`. They match the view name passed to `render()`, such as `pages/single-product` ([#219])
- In a DDEV project, `@studiometa/foehn-vite-plugin` adds the DDEV site to `server.cors`, unless `vite.config.js` sets it. A page opened on `https://<project>.ddev.site` can now load `/@vite/client` from the dev server, so HMR works there too. ([#241])

### Dependencies

- `studiometa/foehn` requires `druidfi/mysqldump-php` `^2`, which dumps the database with PDO, without a `mysqldump` binary ([#227])
- `@studiometa/foehn-vite-plugin` requires `@jridgewell/trace-mapping` `^0.3.31`. ([#241])

[#218]: https://github.com/studiometa/foehn-framework/pull/218
[#219]: https://github.com/studiometa/foehn-framework/pull/219
[#220]: https://github.com/studiometa/foehn-framework/pull/220
[#221]: https://github.com/studiometa/foehn-framework/pull/221
[#226]: https://github.com/studiometa/foehn-framework/pull/226
[#227]: https://github.com/studiometa/foehn-framework/pull/227
[#237]: https://github.com/studiometa/foehn-framework/pull/237
[#238]: https://github.com/studiometa/foehn-framework/pull/238
[#239]: https://github.com/studiometa/foehn-framework/pull/239
[#240]: https://github.com/studiometa/foehn-framework/pull/240
[#241]: https://github.com/studiometa/foehn-framework/pull/241
[#242]: https://github.com/studiometa/foehn-framework/pull/242
[#243]: https://github.com/studiometa/foehn-framework/pull/243
[#244]: https://github.com/studiometa/foehn-framework/pull/244
[#248]: https://github.com/studiometa/foehn-framework/pull/248
[#249]: https://github.com/studiometa/foehn-framework/pull/249
[#250]: https://github.com/studiometa/foehn-framework/pull/250
[#251]: https://github.com/studiometa/foehn-framework/pull/251
[#254]: https://github.com/studiometa/foehn-framework/pull/254

## 0.7.1 - 2026-11-12

### Dependencies

- Pin dependencies ([#223])
- Pin dependencies ([#224])

[#223]: https://github.com/studiometa/foehn-framework/pull/223
[#224]: https://github.com/studiometa/foehn-framework/pull/224

## 0.7.0 - 2026-11-11

### Changed

- **Breaking:** the generated `wp-config.php` reads `DISABLE_WP_CRON` from the environment and turns the pseudo-cron off by default. A host must run `wp cron event run --due-now` on a schedule (the runtime image does), or set `DISABLE_WP_CRON=false` to get the pseudo-cron back. Run `composer install` to regenerate `wp-config.php`. See [The real cron](https://studiometa.github.io/foehn-framework/guide/verification#the-real-cron) ([#216])
- Føhn writes the cron heartbeat itself: a `foehn_cron_heartbeat` event, scheduled every minute on a new `foehn_every_minute` recurrence, writes `foehn_cron_last_run` each time a real cron runs it. Any host that runs `wp cron event run --due-now` (a Forge scheduled job, a system crontab) now passes `cron-heartbeat`, not only the runtime image ([#216])
- The `real-cron` check of `wp foehn verify --profile=production` passes when `DISABLE_WP_CRON` is on. `cron-heartbeat` proves that a real cron runs ([#216])
- The runtime image's cron runs only `wp cron event run --due-now`, and logs nothing when only the heartbeat event ran ([#216])

### Removed

- **Breaking:** `FOEHN_CRON_ENABLED`. The generated `wp-config.php`, the runtime image and production verification no longer read it, and the image's cron can no longer be turned off. Use `DISABLE_WP_CRON=false` for the pseudo-cron ([#216])

[#216]: https://github.com/studiometa/foehn-framework/pull/216

## 0.6.5 - 2026-10-22

### Fixed

- A project without a lock file could not install Føhn: every `tempest/generation` release requires `nette/php-generator` at exactly `4.2.1`, which is no longer on Packagist ([tempestphp/tempest-framework#2313](https://github.com/tempestphp/tempest-framework/issues/2313)). The starter and the demo now install `4.2.3` in its place with the root alias `"nette/php-generator": "4.2.3 as 4.2.1"`, and the [installation guide](https://studiometa.github.io/foehn-framework/guide/installation) tells existing projects to add it. The code generation of the `make` commands gives the same files with `4.2.3` ([#215])

[#215]: https://github.com/studiometa/foehn-framework/pull/215

## 0.6.4 - 2026-10-13

### Changed

- Each merge into `main` releases with the `Release` workflow: it folds the `.changelog/` fragments into `CHANGELOG.md` with the version that they give, moves every package version and inter-package pin, commits, tags and creates the GitHub release, then publishes the Vite plugin, splits the packages, builds the runtime image and deploys the demo. Pull requests add a fragment in `.changelog/` instead of editing `CHANGELOG.md`, and a `Changelog` check asks for one when `packages/` or `docker/` change ([#208])

[#208]: https://github.com/studiometa/foehn-framework/pull/208

## [0.6.3] - 2026-10-06

Agents get the framework's manual, and dev mode reaches WordPress.

### Added

- Agent Skills for coding agents: `foehn`, `foehn-acf` and `foehn-vite-plugin`, one in each package. `npx skills add studiometa/foehn-framework#<version>` installs them at the release in `composer.lock`, and the [AI Agents](https://studiometa.github.io/foehn-framework/guide/ai-agents) guide documents the install, the pin and `llms.txt` ([#196])
- `#[AsBlockCategory]` registers its categories: a discovery adds them to `block_categories_all`, ahead of the existing ones, and skips a slug that is already registered. The attribute existed and was documented, but nothing read it ([#205])

### Fixed

- `npm run dev` never reached WordPress. The Vite plugin wrote the hot file at the project root while `ViteManifest::fromTheme()` reads it in the build directory, and the DDEV proxy forwarded entry requests to WordPress before Vite could serve them. The hot file now lives in `outDir` (a file name, matching `ViteManifest`'s argument), carries the address the server really listens on, and is removed on Ctrl+C and on a closed terminal; the proxy forwards only what Vite cannot serve ([#199])
- A password-protected post answered with a 500, and a protected page or a post card showed the protected content. The starter and the demo ship `pages/password.twig`, and the starter's card hides the excerpt of a protected post. Projects made from an earlier starter must add both ([#200])
- ACF blocks received ACF's raw, flat storage (`items_0_heading`, attachment IDs, unformatted values), and an empty array for field-key data in the editor preview, patterns and templates. `$fields` now holds the nested, formatted values of `get_fields()` in block context. Code that read flat keys must read the nested values, and `SpacingData::fromAcf()` takes the group value: `SpacingData::fromAcf($fields['spacing'] ?? null)` ([#201])
- `foehn-acf` required `studiometa/foehn` `^0.5`, so it could not be installed with the 0.6 framework. Every inter-package dependency now requires the exact release, and the release workflow refuses a tag that any package version or inter-package constraint does not match ([#204])

### Changed

- ACF blocks without a `category` default to `widgets`, like `#[AsBlock]`, instead of the legacy `common` that WordPress maps to `text` ([#202])
- The block docs say that `theme` is a core block category ([#198]), and the field fragments guide composes fragments with `addGroup()->addFields()` instead of an `appendFields()` acf-builder does not have ([#203])
- Pull requests are reviewed by `weareikko/code-review`, with each setting named in the workflow rather than every secret dumped, which GitHub holds as a potentially malicious workflow ([#197])

[#196]: https://github.com/studiometa/foehn-framework/pull/196
[#197]: https://github.com/studiometa/foehn-framework/pull/197
[#198]: https://github.com/studiometa/foehn-framework/pull/198
[#199]: https://github.com/studiometa/foehn-framework/pull/199
[#200]: https://github.com/studiometa/foehn-framework/pull/200
[#201]: https://github.com/studiometa/foehn-framework/pull/201
[#202]: https://github.com/studiometa/foehn-framework/pull/202
[#203]: https://github.com/studiometa/foehn-framework/pull/203
[#204]: https://github.com/studiometa/foehn-framework/pull/204
[#205]: https://github.com/studiometa/foehn-framework/pull/205
[0.6.3]: https://github.com/studiometa/foehn-framework/releases/tag/0.6.3

## [0.6.2] - 2026-09-24

The development stack outlives MinIO, and the image stops shouting.

### Fixed

- `ddev start` on the demo and the backup check in CI failed at the pull: MinIO deleted `minio/minio` from Docker Hub on 2026-09-11. RustFS takes its place as the S3 store in development and in CI — same port, path-style addressing, bucket policies for anonymous reads ([#189])
- WP-CLI in the runtime image raised some 100 `PHP Deprecated:` lines per `wp foehn verify` on PHP 8.5, from two dependencies the published 2.12.0 phar bundles. The image now builds the phar itself with `php-cli-tools` 0.12.9 and `composer/composer` 2.10, and the workflow asserts it raises none ([#188])
- The monorepo split raced itself on a release: the push of `main` and the push of the tag each started a run, and the loser left `foehn-demo` a version behind. Runs are now serialised per target repository ([#187])
- A keyed query arg with a hyphen produced an nginx variable nginx rejects, and two names meeting in one variable (`a-b`, `a_b`) are now refused by the configuration rather than keyed under each other's filter ([#193])
- `wp foehn cache:status` reported an include generated by an older release as current: the `# policy:` hash now carries a generator version ([#193])
- A demo smoke assertion compared two `WP_Query` results positionally and failed when fixture posts shared a date ([#194])
- Aborted smoke runs left `.ddev/nginx/foehn-page-cache.conf` and `.phpunit.smoke.cache/` behind as untracked files in the starter and the demo; both are ignored ([#191])

### Changed

- The bracketed spelling of a multi-value filter, `?genre[]=rock&genre[]=jazz`, is served by nginx like the comma spelling, instead of falling through to the `advanced-cache.php` drop-in. nginx computes exactly the key PHP computes or declines: mixed spellings, repeated bare args, empty members and more than five members go to PHP ([#193])
- Codecov grades the page-cache paths, whose serve path only the integration job exercises, under a separate `pagecache` patch status at 70%, and excludes them from the default patch status ([#192])
- The roadmap names the current release, and issue #151 (ImageTransformer) is closed as shipped in 0.5.4 ([#190])

[#187]: https://github.com/studiometa/foehn-framework/pull/187
[#188]: https://github.com/studiometa/foehn-framework/pull/188
[#189]: https://github.com/studiometa/foehn-framework/pull/189
[#190]: https://github.com/studiometa/foehn-framework/pull/190
[#191]: https://github.com/studiometa/foehn-framework/pull/191
[#192]: https://github.com/studiometa/foehn-framework/pull/192
[#193]: https://github.com/studiometa/foehn-framework/pull/193
[#194]: https://github.com/studiometa/foehn-framework/pull/194
[0.6.2]: https://github.com/studiometa/foehn-framework/releases/tag/0.6.2

## [0.6.1] - 2026-09-03

A new project gets the version on the tag, and a suite it can run.

### Fixed

- `composer create-project studiometa/foehn-starter` installed the previous minor: the starter required `^0.5`, and a caret on a `0.x` version pins the minor ([#185])
- A project created from the starter could not run the smoke suite it was given — no Pest, no PSR-4 mapping for its support classes, and a bootstrap that resolved outside the project ([#185])
- `composer test` in a generated project failed on the starter's own packaging test, which reads a file the archive does not carry ([#185])
- Two smoke teardowns ran for cases they had skipped, one warning and one writing a config file into the project ([#185])

### Changed

- The starter and the demo pin `studiometa/foehn` and `studiometa/foehn-installer` to an exact version rather than a range, so `create-project` installs a combination somebody has run. The release workflow refuses a tag its own starter would not install ([#185])

[#185]: https://github.com/studiometa/foehn-framework/pull/185
[0.6.1]: https://github.com/studiometa/foehn-framework/releases/tag/0.6.1

## [0.6.0] - 2026-09-03

A deploy can refuse to finish.

### Added

- Page-local HTML section rendering through `?foehn_sections=...`, with `foehn_section()` and `foehn_section_url()` Twig helpers, active Twig context, atomic multi-section responses, and lazy loading through `@studiometa/ui` `LazyInclude` ([#164])
- One page-cache invalidation service, `PageCache\Invalidator`, with full, section-only and per-URL clearing. Every runtime caller uses it ([#174])
- Non-production sites are kept out of the search index: `noindex, nofollow`, an `X-Robots-Tag`, `Disallow: /` and no core sitemaps. Production registers nothing ([#175])
- The runtime image's cron records a `foehn_cron_last_run` heartbeat after a successful pass ([#176])
- `wp foehn verify --profile=updates`, a CI gate that fails on the PHP and WordPress diagnostics raised while the site boots ([#177])
- A Føhn admin page and admin-bar cache controls for `manage_options`, POST-only and nonce-protected ([#178])
- `wp foehn verify --profile=production`, a deployment gate over environment, debug, indexing, salts, cron and page-cache storage ([#179])

### Changed

- **Breaking:** `Env::get()` resolves `wp_get_environment_type()`, the `WP_ENVIRONMENT_TYPE` constant, that environment variable, the `WP_ENV` alias, then `production`; `PageCacheConfig::environment()` delegates to it ([#174], [#183])
- **Breaking:** `Env::isLocal()` is true for `local` only, no longer for `development` ([#174])
- **Breaking:** `Purger::__construct()` and `PageCacheClearCommand::__construct()` take a `PageCache\Invalidator` instead of a `PageCache\Store` ([#174])
- **Breaking:** a deletion count means stored response bodies, so `Store::flush()`, `forget()`, `forgetPaginated()`, `sweep()` and `wp foehn cache:clear` report about half what they did ([#174])
- CI runs on every pull request, not only those targeting `main` ([#174])

### Removed

- **Breaking:** the REST/JSON Render API, its configuration, hook, and `/wp-json/foehn/v1/render` route. Section rendering now uses normal page URLs and permanently reserves the namespaced `foehn_sections` query parameter. See the [migration guide](docs/guide/section-rendering.md#migrate-from-the-render-api) ([#164])
- **Breaking:** `APP_ENV` is no longer read. Set `WP_ENVIRONMENT_TYPE`, or `WP_ENV` which is kept as an alias — a site left on `APP_ENV` resolves to `production` silently, which makes the page cache eligible and stands the indexing guard down ([#174], [#183])

[#164]: https://github.com/studiometa/foehn-framework/pull/164
[#174]: https://github.com/studiometa/foehn-framework/pull/174
[#175]: https://github.com/studiometa/foehn-framework/pull/175
[#176]: https://github.com/studiometa/foehn-framework/pull/176
[#177]: https://github.com/studiometa/foehn-framework/pull/177
[#178]: https://github.com/studiometa/foehn-framework/pull/178
[#179]: https://github.com/studiometa/foehn-framework/pull/179
[#183]: https://github.com/studiometa/foehn-framework/pull/183
[0.6.0]: https://github.com/studiometa/foehn-framework/releases/tag/0.6.0

## [0.5.10] - 2026-08-26

The first backup of a site's life finishes.

### Fixed

- Enabling backups on a site whose bucket did not exist yet produced a job that never finished ([#162])

  The order was the intuitive one — ask `restic cat config` whether a repository is there, create one if not. But when the _bucket_ is missing that question is not answered "no": it returns an error Restic classes as worth retrying, and retries it with backoff, unbounded. Measured against a missing bucket, `cat config` had still not returned after ten minutes while `restic init` answered in two seconds.

  `init` runs first now, and its refusal to touch an existing repository is read as the "yes, there is one" it actually is. Anything else — a bad key, an unreachable endpoint — still stops the run, because the alternative is backing up into nowhere and reporting success. `init` also creates the bucket, which is what makes a first run work where nothing has created one yet.

### Changed

- CI runs a backup end to end, against a real MinIO and a real MariaDB, instead of only checking that one was scheduled — the gap the above fell through. It asserts that the first run creates the bucket inside a time bound the old code could not meet, that the second run does not claim to create anything, that the restore drill recognises the snapshot, that a dump dying halfway stores no snapshot, and that unusable credentials fail loudly ([#162])

[#162]: https://github.com/studiometa/foehn-framework/pull/162
[0.5.10]: https://github.com/studiometa/foehn-framework/releases/tag/0.5.10

## [0.5.9] - 2026-08-26

The embedded database survives its first restart.

### Fixed

- The `-db` variant lost its data directory to the platform on every boot after the first. A mounted volume does not keep the ownership the image gave it — Fly applies `uid: 0, gid: 0` and `chmod 0755` to the mount point each time it mounts — so a directory `mariadb-install-db` had handed to `mysql` was root's again by the next start, and MariaDB died on the first file it had to create ([#161])

  It failed in a way that hid the cause: the files inside kept their ownership, so the server read the whole dataset perfectly and aborted with `Can't create/write to file './ddl_recovery.log' (Errcode: 13 "Permission denied")`, which reads like corruption and is a directory mode. The first boot always worked, so the failure waited for the first restart — by which time the volume held data.

  A docker volume inherits the image's ownership and never reproduces it, which is why this reached a real machine before it was caught. The data directory is now handed back to `mysql` on every boot, and CI re-owns the mount point to root between two boots on purpose and asserts the data comes back.

[#161]: https://github.com/studiometa/foehn-framework/pull/161
[0.5.9]: https://github.com/studiometa/foehn-framework/releases/tag/0.5.9

## [0.5.8] - 2026-08-26

A site can back its database up, and can carry it.

### Added

- Scheduled database backups: a `mariadb-dump` streamed into a [Restic](https://restic.net) repository on S3-compatible storage, with grandfather-father-son retention, a weekly prune and a weekly restore drill. Off unless a site sets `BACKUP_ENABLED=true`, and reached through the `DB_*` secrets a site already defines, so it works against an external database and keeps working unchanged when the database moves into the container ([#158])

  One dump stream, not one per tier: a snapshot at midnight on the first of the month is the hourly, daily, weekly and monthly backup at once, and `restic forget` decides which tiers each satisfies afterwards. Verified against a spread of 94 snapshots — the policy keeps exactly 12 hourly, 7 daily, 8 weekly and 12 monthly.

  The dump is handed to Restic with `--stdin-from-command` and never piped into it. A pipeline takes its exit status from the last command, so a dump that dies halfway is stored as a valid snapshot and found during a restore; measured both ways, the pipeline stores the truncated file and exits 0 while this stores nothing and exits 1.

  A weekly restore drill comes with it, because every failure mode here is silent and `restic check` cannot see any of them — it verifies the repository holds what it claims, not that what it holds is a database. `BACKUP_VERIFY_ENABLED=false` opts out; switch it off last.

- A `-db` image variant, `ghcr.io/studiometa/foehn-wordpress:<version>-db`, that runs MariaDB in the site container — so a small site is one app instead of an `<app>`/`<app>-db` pair ([#159])

  A tag and not a runtime flag: the server is 187 MB installed, which in the base image would be charged to every site including the ones that will never start a database, and a flag puts a topology decision somewhere it can be set wrong. Both tags are built and checked by the same workflow run before either is pushed, so they cannot ship from different commits.

  The variant creates the database and user from the same `DB_*` secrets and points `DB_HOST` at its own socket, so moving a site is one line in its Dockerfile. Rehearsed on a real site: 31 tables restored with production's row counts, the page cache still served by nginx, and 277 MB peak for the whole container under 6368 requests forced through PHP and MariaDB. It needs a volume, `kill_timeout = "30s"` so InnoDB shuts down cleanly, and a health-check `grace_period`.

  Small sites only — tens of megabytes, read-dominated, one machine. Larger projects stay on the plain image, which is the default and unchanged: no `mariadbd` binary, no extra process, nothing to opt out of.

### Fixed

- `wp db` works. `mariadb-client` was missing from the runtime image, so every `wp db` subcommand failed with `env: can't execute 'mysql'` — WP-CLI shells out to the legacy `mysql` and `mysqldump` names. Confirmed against the published 0.5.7 and against this release ([#158])

[#158]: https://github.com/studiometa/foehn-framework/pull/158
[#159]: https://github.com/studiometa/foehn-framework/pull/159
[0.5.8]: https://github.com/studiometa/foehn-framework/releases/tag/0.5.8

## [0.5.7] - 2026-08-25

The page cache installs itself.

### Added

- The image installs Føhn's page cache rules itself, generated at boot from the site's own `page-cache.config.php` — so a project neither writes them nor commits them, and they cannot fall behind the configuration beside them ([#157])

  Generated rather than shipped, because they are not fixed: the cache path, the query arguments that are keyed and the ones ignored all come from that configuration, and the file carries a hash of it. A copy baked into the image would be right for the default and quietly wrong for anything else — and serving one visitor's page to another is the failure a page cache has.

  If generating fails, most likely a database that is not up yet, the site falls back to the drop-in at `wp-content/advanced-cache.php`, which serves the same stored files and needs no webserver configuration. Measured on one site: 1.3ms through nginx, 2.8ms through the drop-in, 95ms rendering. `FOEHN_PAGE_CACHE_CONFIG=false` opts out, and a project that generates the rules itself keeps them.

[#157]: https://github.com/studiometa/foehn-framework/issues/157
[0.5.7]: https://github.com/studiometa/foehn-framework/releases/tag/0.5.7

## [0.5.6] - 2026-08-25

A runtime to deploy onto, so a project stops writing the webserver rules the framework already generates.

### Added

- Add a WordPress runtime image, published to `ghcr.io/studiometa/foehn-wordpress` as `latest`, with a release number to pin one. A project inherits it in a `FROM` line, copies its own code, and configures nothing ([#156])

  Føhn generates webserver rules and spells its image cache into a path a webserver can assemble, but neither is worth anything until something installs them — and until now that was every project, by hand, once each. The image carries the media proxy and the `/_image/` cache, both rendered at boot from the `S3_UPLOADS_*` variables WordPress already reads; the scheme and the client's address recovered from the proxy in front; WP-CLI; and a health endpoint that fails when PHP is down rather than only when nginx is. Anything a project leaves in `config/nginx/*.conf` is picked up, which is where `wp foehn cache:config --server=nginx --write` writes the page cache.

[#156]: https://github.com/studiometa/foehn-framework/issues/156
[0.5.6]: https://github.com/studiometa/foehn-framework/releases/tag/0.5.6

## [0.5.5] - 2026-08-25

Two faults that only appear against object storage, and one that only appears on a library which has outlived its own image sizes.

### Added

- Add `ContentImageHooks`, so the images pasted into the editor are sized by the configured transformer instead of by the intermediate files WordPress cut at upload time. Opt-in in `foehn.config.php`, and inert with no transformer configured ([#155])

  The sizes an editor's image is offered at are decided the day it is uploaded, from whatever was registered then — a new size does nothing for a library that already exists. Worse, the metadata that lists them is a claim rather than a fact: it outlives the files it names, so a library that has lost its intermediate files goes on advertising widths that 404, in `srcset`, where no page reports it and no editor can see it. Every candidate is now derived from the original, so nothing is claimed that cannot be produced.

  It hooks `wp_content_img_tag` and not `wp_calculate_image_srcset`, which looks like the right one and is not: that filter returns early when the metadata lists no sizes, which is exactly the case this repairs.

### Fixed

- Serve a transform on the request that builds it, against a bucket whose reads cannot be streamed. `GlideRoute` used `Server::outputImage()`, which reads the freshly written image back with `readStream()` — that asks the S3 adapter for `@http.stream`, and the body it returns is not seekable on every provider. Against Tigris the SDK's own rewind throws `Stream is not seekable`, so the miss could never be answered, while the transform itself sat in the bucket perfectly intact. The route now reads the image rather than streaming it, which is bounded work: `maxSize` caps both sides of a transform ([#154])

  The symptom was worse than a failed image, and worth recognising: `outputImage()` sends `Content-Length` _before_ it reads, so the failure left the length of an image promised and a short error body delivered. nginx waited for bytes that never came and logged `upstream prematurely closed FastCGI request`; the reader got no response at all, not a 404. Every error this route could raise arrived that way. The image is now built and read before a single header goes out, so the failure path can still answer.

[#154]: https://github.com/studiometa/foehn-framework/issues/154
[#155]: https://github.com/studiometa/foehn-framework/issues/155
[0.5.5]: https://github.com/studiometa/foehn-framework/releases/tag/0.5.5

## [0.5.4] - 2026-08-24

Image transforms at an arbitrary size, for the crops `#[AsImageSize]` cannot register ahead of time.

### Added

- Add an `ImageTransformer` abstraction so a template asks for a size without naming who produces it, and a project swaps provider in `foehn.config.php` rather than in every template. `NullTransformer` is the default and returns the URL it was given, so nothing changes until a project asks ([#151])
- Add `GlideTransformer`, a self-hosted driver over `league/glide` — a `suggest`, so nothing pulls `intervention/image` into a project that does not transform images. It follows the uploads: directories on local disk, prefixes of the same bucket under `humanmade/s3-uploads`, reading where the plugin writes and caching beside it. It reuses the plugin's own S3 client, so the endpoint, path-style addressing and checksum settings a non-AWS bucket needs — none of which are constants — apply to transforms too ([#151])
- Add `image_url()` to Twig, and `ImageCacheHooks` to forget an image's transforms when the image itself changes — a cache key is built from the path and the transform, never from the content, so a cropped image would otherwise serve its old pixels indefinitely ([#151])
- **Demo:** Use both ways of sizing an image, because they answer different questions. Homepage cards keep a registered `#[AsImageSize]`; the plates on a project page crop to two ratios and so ask for the crop with `image_url()`. Measured there, a plate is ~220ms the first time and ~22ms after, served from the bucket by `.ddev/nginx/image-cache.conf` with no WordPress boot ([#151])

  URLs carry no signature, and that is the design rather than an omission: a signature needs a secret, so a browser could only ever replay sizes chosen in advance — which is the thing a responsive image is trying not to do. What stops `?w=9999` is that the parameters are bounded. `GlideConfig::normalise()` snaps `w` and `h` onto a grid, holds `fit` and `fm` to short lists, and drops every other key; `image_url()` and the route both run it, so a URL is valid by construction on one side and refused on the other. The allowlist is on the keys and not only their values because `Server::getAllParams()` ends with `array_merge($all, $params)` — an unknown parameter is not ignored, it wins.

  GD is the default driver because, measured on a 2777x1973 photograph, it is about twice as fast as Imagick _and_ produces smaller files — and it is the extension that is always present. See `docs/guide/images.md` for the webserver rule that serves cache hits without booting WordPress, for the rate limit that bounds how fast the parameter space can be walked, and for the one case where this transformer should not be enabled at all.

### Fixed

- Dispatch a `#[AsRewriteRule]` whose query variables all come from the URL. `matchableVars()` skipped any variable whose value was a `$matches[n]` capture, so a rule like `index.php?foehn_image=$matches[1]` — every variable captured, nothing constant — was left with nothing to match on and never reached its handler. The rewrite still matched, so the symptom was an ordinary WordPress 404 on a URL that `wp rewrite list` showed as correctly registered. A captured variable is now recorded as required-and-non-empty rather than dropped ([#151])

[#151]: https://github.com/studiometa/foehn-framework/issues/151
[0.5.4]: https://github.com/studiometa/foehn-framework/releases/tag/0.5.4

## [0.5.3] - 2026-08-21

Nine fixes to what a project actually receives from the starter. Three of them made a freshly scaffolded project unusable in ways a monorepo checkout never shows, because CI lints and tests the Vite plugin only — never the starter as a consumer sees it.

### Fixed

- **Starter:** Stop wiping the WordPress security keys on every `create-project`. The installer generated all eight into `.env` and reported doing so, then the starter's own `post-create-project-cmd` ran `copy('.env.example', '.env')` — after the installer, by definition — and put the empty template back. Every scaffold ended with no keys, silently, until the first production request, which `wp-config.php` refuses while any key is empty ([#142])
- **Starter:** Resolve `tests/php/bootstrap.php` from wherever the autoloader is, rather than by counting directory levels. It used `dirname(__DIR__, 3)` and `dirname(__DIR__, 4)`, which land inside this monorepo and outside an installed project, so the Pest suite could not start at all. The WordPress stubs do ship with `studiometa/foehn`; only the paths were wrong ([#143])
- **Starter:** Point `.oxlintrc.json` at the project's own `node_modules`. It extended `../../node_modules/@studiometa/oxlint-config`, this monorepo's hoisted install, so `oxlint` exited with a config error in any scaffolded project ([#144])
- **Starter:** Keep monorepo-only files out of the published package. `composer.local.json`/`.lock` declare `path` repositories pointing at `../foehn` and `../installer` with `minimum-stability: dev`, and `.ddev/config.yaml` shipped `post-start` hooks whose own comment says they cannot work elsewhere. The Composer-local files are now `export-ignore`d, and the ddev hooks live in `.ddev/config.monorepo.yaml` — tracked, so a fresh clone still bootstraps, and export-ignored, so a project does not inherit it ([#145])
- **Starter:** Fall back to `pages/archive` when a post type has no dedicated template. `ArchiveController` rendered one exact name, so any post type registered with `hasArchive: true` returned HTTP 500 on its archive URL until the theme also added `pages/archive-<type>.twig` — and the exception named the missing file rather than the missing fallback. `SingleController` already used `renderFirst()`. Also reads the post type from `get_queried_object()->name`, since `get_query_var('post_type')` can be an array that interpolates as `Array` ([#146])
- **Installer:** Remove theme symlinks left behind by a previous `theme-name`. Renaming it created the new link and kept the old one, so `wp theme list` showed the same directory twice and `wp theme activate` could pick a name nobody uses. Only links pointing at the theme directory are removed ([#148])
- **Installer:** Report the security keys accurately. `Generated: security keys in .env` printed on every install, including runs that correctly left existing keys alone — which made the output useless for spotting a run that did rewrite them. The no-op path now says `Security keys: already set` and writes nothing ([#148])
- **Demo:** Drop the duplicate `humanmade/s3-uploads` key in `composer.json`. Legal JSON and harmless to resolution, but flagged by editors and schema validators ([#149])

### Added

- **Starter:** A `PageController` and `pages/page.twig`. Pages — the one content type every WordPress site has — had no controller, so they bypassed the view layer entirely: WordPress rendered them its own way and a `pages/page.twig` added to the theme was never read. Resolves by slug rather than by full path, which stops being right the moment a page moves in the tree ([#147])
- **Starter:** Enable `Hooks\Cleanup\DisableGlobalStyles` by default, and document the cascade conflict it solves. WordPress prints the styles it derives from `theme.json` inline and _after_ the theme's stylesheet, so core wins on load order; with no `theme.json` those are core's defaults, and `body { padding: 0 }` flattens a gutter the theme sets. The symptom is a theme rule struck through by an inline sheet the theme never enqueued, which reads as a broken stylesheet rather than a load-order fight. Drop it from `foehn.config.php` if you adopt `theme.json` presets ([#150])
- **Starter:** A packaging test asserting the archive contains no path reaching outside the project, and no `post-create-project-cmd` overwriting `.env`

[#142]: https://github.com/studiometa/foehn-framework/issues/142
[#143]: https://github.com/studiometa/foehn-framework/issues/143
[#144]: https://github.com/studiometa/foehn-framework/issues/144
[#145]: https://github.com/studiometa/foehn-framework/issues/145
[#146]: https://github.com/studiometa/foehn-framework/issues/146
[#147]: https://github.com/studiometa/foehn-framework/issues/147
[#148]: https://github.com/studiometa/foehn-framework/issues/148
[#149]: https://github.com/studiometa/foehn-framework/issues/149
[#150]: https://github.com/studiometa/foehn-framework/issues/150
[0.5.3]: https://github.com/studiometa/foehn-framework/releases/tag/0.5.3

## [0.5.2] - 2026-08-20

### Added

- Add the `gallery` block attribute control: several attachments in one field, ordered, with thumbnails in the sidebar. `image` could not serve — `MediaUpload`'s `multiple` changes what `onSelect` receives and the value stops being a scalar
- Add the `file` block attribute control, for an attachment of any kind. `image` hard-codes `allowedTypes: ['image']`, which left audio, video and documents unreachable from a block; `file` reads the types from the schema and restricts nothing by default
- Add the `posts` block attribute control, a searchable picker for related content. It stores post ids **in the order the author arranged them** and offers move-up, move-down and remove, because a relation list is authored rather than queried. `postTypes` narrows the search; without it every viewable post type is searched. This is the one control with no core equivalent, and the main thing a theme needed ACF for
- Add the `allowedTypes` and `postTypes` editor-only schema keys, feeding the three controls above and stripped before the schema reaches WordPress

### Fixed

- Warn in debug mode when `gallery` or `posts` is declared on an attribute that is not an array. WordPress validates a block attribute against its schema and replaces a mismatch with the default, so a list control on a scalar type kept every selection on screen until the next reload and then lost all but the first — a data loss that reads as an editor bug

[0.5.2]: https://github.com/studiometa/foehn-framework/releases/tag/0.5.2

## [0.5.1] - 2026-08-20

### Added

- Publish `@studiometa/foehn-vite-plugin` to npm from the release workflow, through npm trusted publishing: the job authenticates with an OIDC token and no `NPM_TOKEN` exists anywhere. The plugin now carries the same version as every other package, `0.5.1`, and the job refuses to publish when that version does not match the tag ([#140])

### Fixed

- **Starter, demo:** Require `@studiometa/foehn-vite-plugin` at `^0.5.1` rather than `*`. `*` resolved only through the monorepo's own workspaces, so `npm install` in a project created from the starter ended on a 404 for a package that had never been published once — and `npm run build` could not have worked either, `vite.config.js` importing it ([#140])

[#140]: https://github.com/studiometa/foehn-framework/pull/140
[0.5.1]: https://github.com/studiometa/foehn-framework/releases/tag/0.5.1

## [0.5.0] - 2026-08-20

### Added

- Add `ViteManifest` for enqueuing Vite builds, with dev-server, imported-CSS and ES-module handling ([#137])
- Add `StudiometaUi` hook class registering the `@ui` and `@svg` Twig namespaces; `studiometa/ui` stays a `suggest` ([#138])
- **Starter, demo:** Autoload js-toolkit components from a manifest, and `@studiometa/ui` components through `@studiometa/ui/autoload` ([#138])
- **Demo:** A photographer's portfolio — four page types, Swiss design, 30 credited Unsplash photographs — with the database and media committed and `database/restore.sh` to mount it ([#137])
- Serve uploads from the site's own domain with `S3_UPLOADS_DISABLE_REPLACE_UPLOAD_URL` and a webserver rule, instead of the bucket hostname ([#137])
- Offload uploads to object storage by integrating `humanmade/s3-uploads`: `wp-config.php` defines the `S3_UPLOADS_*` constants from the environment, and `S3UploadsEndpoint` supplies the endpoint filter for R2, Scaleway and MinIO ([#135])
- Add `#[AsBlockBinding]` to register a block bindings source for computed attribute values; declared `#[AsPostMeta]` needs none, being bindable through core's `core/post-meta` ([#129])
- Add `#[AsSettingsPage]`, with a Twig form and `Setting::string()`/`bool()`/`int()`/`number()`; no field abstraction, by decision ([#128])
- Add `#[AsRewriteRule]` and `RewriteHandlerInterface`, flushing only when the declared set changes; `wp foehn rewrite:flush` forces it ([#127])
- **Breaking:** Move the ACF layer to `studiometa/foehn-acf`. Classes keep their namespaces, so a project adds one `composer require` and changes no imports ([#125])
- Add `#[AsPostMeta]` for typed post meta with a REST schema, so custom fields no longer need ACF ([#123])
- Add `wp foehn discovery:list`, reporting every discovery, its phase, its items and their attribute arguments ([#122])
- Add `#[AsDiscovery]`, and discover discovery classes themselves, so a package or a theme can add one ([#121])
- Add a static page cache serving stored HTML before WordPress boots ([#124])
- Page cache: nginx, Apache and a PHP drop-in reader, all computing the same filename; `wp foehn cache:config --server` emits the snippet ([#124])
- Page cache: event-driven invalidation on post, term, menu, option and theme changes ([#124])
- Page cache: query args are normalised and `ignoredQueryArgs` dropped, so tracking parameters do not fragment the cache ([#124])
- Page cache: non-ASCII permalinks resolve to a single cache file across percent-escape casing ([#124])
- Add `wp foehn cache:clear`, `cache:status`, `cache:config` and `cache:warm` ([#124])
- WordPress security keys are read from the environment, listed in `.env.example`, or set in `config/wordpress-salts.config.php` ([#120])
- Add `wp foehn salts:generate` for rotating WordPress security keys ([#120])
- The discovery cache fills itself: a request that scans writes what it found, and `composer install` clears it ([#119])
- Config files may be named for an environment — `foehn.production.config.php` — and are read only there ([#118])
- **Starter:** An integration smoke test run in CI against a real WordPress in ddev, on a cold and a warm discovery cache
- Add tests for `studiometa/foehn-installer`, which had none, and run every package's suite in CI
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

- **Breaking:** Split `packages/starter` in two. `studiometa/foehn-demo` takes the demonstrations and the exhaustive test suites; the starter keeps only what a new project cannot start without ([#130])
- **Starter:** `HeroBlock` is a native block and the starter requires no ACF at all ([#126])
- **Breaking:** `DiscoveryRunner::getDiscoveryPhases()` and `getAllDiscoveryClasses()` are removed; `hasRun()` takes a `DiscoveryPhase`. A discovery's phase lives on `#[AsDiscovery]`, and within a phase they apply in class-name order
- **Breaking:** Discovery is built on `tempest/discovery` rather than Foehn's own scanner, with locations from Composer's `installed.json`. `ClassScanner`, `DiscoveryLocation`, `DiscoveryCache`, `WpDiscoveryItems`, `AttributeCodec`, `CacheableDiscovery` and `WpDiscovery` are gone
- Discovery items are cached as attribute instances through `symfony/cache`, so no discovery describes a cache format. The cache is written per location, and `discovery:status` reports how many locations are warm
- WP-CLI commands are registered under `wp foehn` rather than `wp tempest`
- `discovery:warm` is removed: it and `discovery:generate` did the same thing, and the deployment documentation already used `generate`
- The framework's `Models\Post` and `Models\Page` now register as Timber's class map entries for `post` and `page`, as their `#[AsTimberModel]` attributes ask. A model of your own for the same type still wins
- `Kernel::boot()`'s configuration array is a default that a `foehn.config.php` overrides wholesale
- Upgrade PHP dependencies: Tempest `^3.4` → `^3.18`, Timber `^2.0` → `^2.5`, Pest `^3.0` → `^5.1` (PHPUnit 13), Mago `^1.8` → `^1.46`, `composer/composer` `^2.0` → `^2.10`, ACF Pro stubs `^6.5` → `^6.8`, `studiometa/webpack-config` `^6.3` → `^6.4`
- Declare `tempest/support` as a direct dependency of `studiometa/foehn` — `Filesystem` and `str()` were used across ~15 files but only resolved transitively
- **Starter:** Upgrade WordPress `^6.7` → `^7.0`
- Upgrade front-end dependencies: Vite `^8`, Vitest `^4`, TypeScript `^7.0`, Tailwind `^4.3`, js-toolkit `^3.9`, Playwright `^1.62`, and others
- Widen the `@studiometa/foehn-vite-plugin` Vite peer range to `^6 || ^7 || ^8`
- Run Pest from the monorepo root with `--test-directory`; Pest 5 resolves the test path from the Composer root, not the working directory
- Bump CI to Node 24 — lint-staged 17 requires Node >= 22.22.1
- A discovered item is the attribute instance plus the reflection facts it lacks, rather than a flattened copy, so `itemToCacheable()` and the per-discovery cache plumbing are gone
- `getCacheableData()`, `restoreFromCache()` and `wasRestoredFromCache()` are part of the `WpDiscovery` interface; every discovery is cacheable, so the three `method_exists()` probes are gone
- Bump the discovery cache schema to `2`. A cache written by an earlier version is rejected and rebuilt on the next request
- Generated `make:` files are built by `ClassFileGenerator`, setting attribute arguments structurally rather than by matching literals; a substitution that finds no target fails instead of emitting the placeholder
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

- `ConfiguresPostType` and `ConfiguresTaxonomy` never applied, so a post type's rewrite slug was dropped and its archive answered 404 ([#137])
- **Starter, demo:** Built assets were never enqueued, and Vite built outside the theme where nothing is served ([#137])
- **Demo:** `ArchiveController` threw on a post type archive with no template of its own; it falls back now ([#137])
- `config/*.config.php` loaded before `.env`, so project config could not read the project's environment ([#133])
- **Security:** Every install ran on WordPress security keys derived from the web root path, which are guessable and forgeable ([#120])
- The framework's own discoverables never registered, so its Twig extensions and CLI commands were absent ([#118])
- `*.config.php` files were never read, so every project config file did nothing ([#118])
- `#[SkipDiscovery]` was ignored by the scanner, so `make:` command stubs registered their example attributes ([#118])
- **`make:field-group`:** `--post-type`, `--taxonomy` and `--page-template` were ignored, leaving every generated field group located on `post`
- **`make:controller`:** `--templates` with more than one template generated broken code
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

[#89]: https://github.com/studiometa/foehn-framework/pull/89
[#91]: https://github.com/studiometa/foehn-framework/pull/91
[#96]: https://github.com/studiometa/foehn-framework/pull/96
[#97]: https://github.com/studiometa/foehn-framework/pull/97
[#98]: https://github.com/studiometa/foehn-framework/pull/98
[#100]: https://github.com/studiometa/foehn-framework/pull/100
[#101]: https://github.com/studiometa/foehn-framework/pull/101
[#107]: https://github.com/studiometa/foehn-framework/pull/107
[#110]: https://github.com/studiometa/foehn-framework/pull/110
[#111]: https://github.com/studiometa/foehn-framework/pull/111
[#118]: https://github.com/studiometa/foehn-framework/pull/118
[#119]: https://github.com/studiometa/foehn-framework/pull/119
[#120]: https://github.com/studiometa/foehn-framework/pull/120
[#121]: https://github.com/studiometa/foehn-framework/pull/121
[#122]: https://github.com/studiometa/foehn-framework/pull/122
[#123]: https://github.com/studiometa/foehn-framework/pull/123
[#124]: https://github.com/studiometa/foehn-framework/pull/124
[#125]: https://github.com/studiometa/foehn-framework/pull/125
[#126]: https://github.com/studiometa/foehn-framework/pull/126
[#127]: https://github.com/studiometa/foehn-framework/pull/127
[#128]: https://github.com/studiometa/foehn-framework/pull/128
[#129]: https://github.com/studiometa/foehn-framework/pull/129
[#130]: https://github.com/studiometa/foehn-framework/pull/130
[#133]: https://github.com/studiometa/foehn-framework/pull/133
[#135]: https://github.com/studiometa/foehn-framework/pull/135
[#137]: https://github.com/studiometa/foehn-framework/pull/137
[#138]: https://github.com/studiometa/foehn-framework/pull/138
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
