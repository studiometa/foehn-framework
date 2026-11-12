# Demo Project

`studiometa/foehn-demo` is every feature Føhn ships, in one working WordPress theme.

It exists to be read and to be run. It is also what the framework's end-to-end tests run against, so every attribute in it is exercised against a real WordPress rather than against function stubs — which means the examples here are known to work, not merely known to compile.

**Starting a project?** Use [the starter](/guide/starter-theme) instead. It is the same theme with the demonstrations taken out.

## Running it

```bash
composer create-project studiometa/foehn-demo foehn-demo
cd foehn-demo
ddev start
ddev launch
```

Admin at `/wp/wp-admin`, with `admin` / `admin`.

A normal install uses released Føhn packages. In a monorepo checkout, run `ddev start` once, then copy `.ddev/config.local.example.yaml` to `.ddev/config.local.yaml` and run `ddev restart`. This mirrors the local framework packages and clears discovery. Restart again after framework source changes.

## What is demonstrated where

The paths are relative to `packages/demo`. The links go to the guide.

| Feature                                                                                                                | Here                                                                                                                                                                    |
| ---------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| [`#[AsPostType]`](https://studiometa.github.io/foehn-framework/api/as-post-type)                                       | `theme/app/Models/Project.php`, `theme/app/Models/Testimonial.php`                                                                                                      |
| [`#[AsPostMeta]`](https://studiometa.github.io/foehn-framework/api/as-post-meta)                                       | `theme/app/Models/Project.php` (`client`, `year`, `location`, `camera`, `film`), `theme/app/Models/Testimonial.php` (`author_name`, `author_role`, `company`, `rating`) |
| [`#[AsTaxonomy]`](https://studiometa.github.io/foehn-framework/api/as-taxonomy)                                        | `theme/app/Taxonomies/ProjectCategory.php` (`/series/<slug>/`), `theme/app/Taxonomies/ProjectTag.php` (`/project-tags/<slug>/`)                                         |
| [`#[AsTimberModel]`](https://studiometa.github.io/foehn-framework/api/as-timber-model)                                 | `theme/app/Models/Photograph.php`, the class of every attachment                                                                                                        |
| [Model queries](https://studiometa.github.io/foehn-framework/guide/querying-posts)                                     | `Project::query()`, `Project::count()` and `Project::all()` in `theme/app/Controllers/FrontPageController.php` and `theme/app/Rest/`                                    |
| [`#[AsBlock]`](https://studiometa.github.io/foehn-framework/api/as-block)                                              | `theme/app/Blocks/`: hero, section (a container), callout, facts and fact (`parent`), related projects (`posts`), press kit (`file`)                                    |
| [Interactive blocks](https://studiometa.github.io/foehn-framework/guide/native-blocks#interactive-blocks)              | `theme/app/Blocks/ContactSheetBlock.php` and `theme/templates/blocks/contact-sheet.twig`, with the `wp_*` Twig helpers                                                  |
| [`#[AsBlockCategory]`](https://studiometa.github.io/foehn-framework/api/as-block-category)                             | `theme/app/Blocks/BlockCategories.php`                                                                                                                                  |
| [`#[AsBlockPattern]`](https://studiometa.github.io/foehn-framework/api/as-block-pattern)                               | `theme/app/Patterns/CommissionPattern.php`, on the About page                                                                                                           |
| [`#[AsBlockBinding]`](https://studiometa.github.io/foehn-framework/api/as-block-binding)                               | `theme/app/Bindings/ReadingTime.php`; each project also binds its `camera` meta through `core/post-meta`                                                                |
| [`#[AsShortcode]`](https://studiometa.github.io/foehn-framework/api/as-shortcode)                                      | `theme/app/Shortcodes/EditionShortcode.php`, on the About page                                                                                                          |
| [`#[AsSettingsPage]`](https://studiometa.github.io/foehn-framework/api/as-settings-page)                               | `theme/app/Settings/ThemeSettings.php`, with a Twig form: the banner and the page size                                                                                  |
| [`#[AsRewriteRule]`](https://studiometa.github.io/foehn-framework/api/as-rewrite-rule)                                 | `theme/app/Routes/HealthCheckRoute.php`: `GET /_health`                                                                                                                 |
| [`#[AsRestRoute]`](https://studiometa.github.io/foehn-framework/api/as-rest-route)                                     | `theme/app/Rest/ProjectsApi.php`: `GET /wp-json/demo/v1/projects`, and a drafts route with its own permission method                                                    |
| [`#[AsTemplateController]`](https://studiometa.github.io/foehn-framework/api/as-template-controller)                   | `theme/app/Controllers/`: front page, archives, single posts and projects, pages, search, 404                                                                           |
| [`#[AsContextProvider]`](https://studiometa.github.io/foehn-framework/api/as-context-provider)                         | `theme/app/ContextProviders/GlobalContextProvider.php`                                                                                                                  |
| [`#[AsTwigExtension]`](https://studiometa.github.io/foehn-framework/api/as-twig-extension)                             | `theme/app/Twig/PortfolioExtension.php`: a `plural` filter                                                                                                              |
| [Twig helpers](https://studiometa.github.io/foehn-framework/guide/twig-extensions#built-in-extensions)                 | `html_classes()`, `html_styles()` and `{% html_element %}` in `theme/templates/blocks/`; `video_embed()` in `pages/single-project.twig`                                 |
| [Query filters](https://studiometa.github.io/foehn-framework/guide/query-filters)                                      | `theme/app/query-filters.config.php`, the filter form and its chips in `sections/project-filters.twig`, the search in `pages/search.twig`                               |
| [Section rendering](https://studiometa.github.io/foehn-framework/guide/section-rendering)                              | AJAX pagination in `theme/templates/sections/project-index.twig`; lazy loading in `theme/templates/sections/testimonials.twig`                                          |
| [Page cache](https://studiometa.github.io/foehn-framework/guide/page-cache)                                            | `theme/app/page-cache.config.php`: keyed filters, `cacheControl`, `responseHeaders`, `warmAfterPurge`; bucket storage in `examples/`                                    |
| [Object cache with tags](https://studiometa.github.io/foehn-framework/guide/caching)                                   | `theme/app/Controllers/FrontPageController.php`, flushed by `theme/app/Hooks/ProjectCacheHooks.php`                                                                     |
| [`#[AsImageSize]`](https://studiometa.github.io/foehn-framework/api/as-image-size)                                     | `theme/app/ImageSizes/`, used by `theme/templates/components/card-project.twig` and the hero                                                                            |
| [Image transforms](https://studiometa.github.io/foehn-framework/guide/images)                                          | `GlideTransformer` in `theme/app/foehn.config.php`, used by `theme/templates/components/photograph.twig`; `ContentImageHooks` in posts                                  |
| [`#[AsMenu]`](https://studiometa.github.io/foehn-framework/api/as-menu)                                                | `theme/app/Menus/`                                                                                                                                                      |
| [`#[AsAction]`](https://studiometa.github.io/foehn-framework/api/as-action) / `#[AsFilter]`                            | `theme/app/Hooks/`                                                                                                                                                      |
| [`#[AsJob]` and `dispatch()`](https://studiometa.github.io/foehn-framework/api/helpers#dispatch)                       | `theme/app/Jobs/WarmPlatesHandler.php`, dispatched by `theme/app/Hooks/ProjectCacheHooks.php` when a project is saved                                                   |
| [`#[AsCron]`](https://studiometa.github.io/foehn-framework/guide/cron)                                                 | `theme/app/Jobs/WarmAllPlates.php` (a daily interval), `theme/app/Jobs/FlushPageCacheAtNewYear.php` (`0 0 1 1 *`, in the site timezone)                                 |
| [`#[AsCliCommand]`](https://studiometa.github.io/foehn-framework/api/as-cli-command)                                   | `theme/app/Console/`: `wp app projects:stats`, and `wp app projects:reorder`, which declares `writes: true`                                                             |
| [`#[AsSeeder]`](https://studiometa.github.io/foehn-framework/guide/seeders)                                            | `theme/app/Seeders/`: the whole site, written by `wp foehn seed`                                                                                                        |
| [Arrayable DTOs](https://studiometa.github.io/foehn-framework/guide/arrayable-dtos)                                    | `theme/app/Data/HeroContext.php`                                                                                                                                        |
| [`#[AsAcfFieldGroup]`](https://studiometa.github.io/foehn-framework/api/as-acf-field-group)                            | `theme/app/Fields/ProjectPressFields.php`, with the `ButtonLinkBuilder` fragment, filled by `theme/app/Seeders/PressSeeder.php`                                         |
| [Opt-in hooks](https://studiometa.github.io/foehn-framework/guide/hooks)                                               | `theme/app/foehn.config.php`: cleanup, security headers, REST and file editor hardening, uploads, image cache                                                           |
| [Config files per environment](https://studiometa.github.io/foehn-framework/guide/configuration#per-environment-files) | `theme/app/foehn.local.config.php`; the footer prints `Env::get()`                                                                                                      |
| [Uploads in a bucket](https://studiometa.github.io/foehn-framework/guide/uploads)                                      | `S3UploadsEndpoint` in `theme/app/foehn.config.php`, RustFS in `.ddev/docker-compose.rustfs.yaml`, Tigris on Fly                                                        |
| [Assets](https://studiometa.github.io/foehn-framework/guide/assets)                                                    | `vite.config.js`, a js-toolkit component in `theme/assets/js/components/CopyLink.js`, a `@ui` accordion in `pages/page.twig`                                            |
| [Public files](https://studiometa.github.io/foehn-framework/guide/public-files)                                        | `public/`: `robots.txt`, `favicon.ico`, `.well-known/security.txt`                                                                                                      |
| [Database export](https://studiometa.github.io/foehn-framework/guide/database-export)                                  | `theme/app/db-export.config.php`, which makes `database/demo.sql.gz`                                                                                                    |
| [Verification](https://studiometa.github.io/foehn-framework/guide/verification)                                        | `wp foehn verify --profile=production` after each deploy, in `.github/workflows/deploy-demo.yml`                                                                        |
| [Runtime image](https://studiometa.github.io/foehn-framework/guide/deployment-fly)                                     | `Dockerfile`, `fly.toml` and `docker/entrypoint.d/35-demo-seed.sh`                                                                                                      |
| [AI agents](https://studiometa.github.io/foehn-framework/guide/ai-agents)                                              | The block of `wp foehn agents-md` in `AGENTS.md`                                                                                                                        |

The theme's namespace is `Demo\` rather than `App\`. Do not copy that part: the starter and your own project use `App\`.

## What is not here

**ACF blocks and options pages.** They need ACF Pro, a paid plugin CI cannot install, so they would be a path nothing ever runs. The demo runs the free ACF plugin instead: `theme/app/Fields/ProjectPressFields.php` is an [`#[AsAcfFieldGroup]`](/guide/acf-blocks) on projects with the `ButtonLinkBuilder` fragment.

## Try the CLI

Run these from `packages/demo` with ddev started.

```bash
ddev wp app projects:stats                 # a command of the project, under `wp app`
ddev wp app projects:reorder --dry-run     # a command that writes, without writing
ddev wp foehn seed --dry-run               # the seeders, in their order
ddev wp foehn inspect                      # what discovery found: post types, blocks, routes, commands
ddev wp foehn inspect templates            # each template, with its controller and a sample URL
ddev wp foehn cache:status                 # the page cache: on or off here, and why
ddev wp action-scheduler action list --group=demo   # the cron jobs and the queued jobs
ddev wp foehn db:export demo.sql.gz        # an anonymized copy of the database
```

`projects:reorder` declares `writes: true`. In production, it stops and says why, unless you add `--dry-run` or `--force`. Try it with `ddev exec 'WP_ENVIRONMENT_TYPE=production wp app projects:reorder'`.

## Debugging

The demo runs in the `local` environment, so `theme/app/foehn.local.config.php` replaces `foehn.config.php`. It turns on `debug`, `recordErrors` and `traceRequests`.

```bash
ddev wp foehn inspect problems             # the classes discovery skipped, with the reason and the fix
ddev wp foehn errors                       # the last PHP errors, with the template, controller and view
ddev wp foehn errors --browser             # the console errors of the pages, while `npm run dev` runs
curl -skI https://foehn-demo.ddev.site/about/ | grep -i x-foehn-trace
ddev wp foehn trace <id>                   # that request: template hierarchy, controller, views, queries
ddev wp foehn log                          # the WordPress debug log, grouped
ddev wp foehn verify --profile=production  # the release gate; here, the environment checks fail on purpose
```

In the admin, the **Føhn** page shows the environment, the page cache, its last purge and the last real cron run, with a button to clear the cache. The **Føhn Cache** menu of the admin bar clears the page you are on. The page cache runs in production only: to watch it in ddev, run `./tests/smoke/page-cache-bucket.sh`, or add a `theme/app/page-cache.local.config.php` with `environments: ['local']`.

## The tests are the point

```bash
composer test:demo                            # the PHP suite
npm run -w @studiometa/foehn-demo test:run    # browser tests
./tests/smoke/run.sh                          # against a started ddev
```

`tests/smoke/` is what makes this package worth having. The PHP suites run against WordPress function stubs, so a discovery that registers nothing at all still passes them — on 2026-08-19 that was 1409 passing tests and a fatal error on every front-end page of the site.

The smoke test drives real requests against a real WordPress and asserts inside it: that the post types exist, that the meta is registered against the right subtype, that a bound block renders its computed value, that `GET /_health` reaches its handler, that the settings page appears under Appearance. CI runs it twice per change, on a cold discovery cache and again on a warm one, because restoring items and scanning them are different code paths.

Every assertion in it was checked to fail when its feature is removed. An assertion that cannot fail is worse than none.

## Related

- [Starter Theme](/guide/starter-theme)
- [Getting Started](/guide/getting-started)
