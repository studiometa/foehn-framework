# Foehn Demo

Every feature [Føhn](https://github.com/studiometa/foehn-framework) ships, in one working WordPress theme — and a site worth looking at rather than a gallery of switched-on options.

It is a photographer's portfolio: a homepage, an index of six series, a page per series and an about page, set in a Swiss idiom of two colours, one grotesque and a twelve-column grid. Every attribute the framework ships is used because the site needed it, which is the only honest way to demonstrate a framework.

This is the reference project, not the one to start from. It exists to be read, to be poked at, and to be tested: the framework's end-to-end and browser suites run against it, so every attribute here is exercised against a real WordPress rather than against function stubs.

The database and the photographs are committed — see [`database/`](database/README.md) — so `./database/restore.sh` puts the whole site up in one step.

**Starting a project?** Use [`studiometa/foehn-starter`](https://github.com/studiometa/foehn-starter), which is the same theme with the demonstrations taken out.

> **Note**
> This package is part of the [Føhn Framework](https://github.com/studiometa/foehn-framework) monorepo.
> Please report issues and submit pull requests in the [main repository](https://github.com/studiometa/foehn-framework).

## Running it

```bash
composer create-project studiometa/foehn-demo foehn-demo
cd foehn-demo
ddev start
```

DDEV starts PHP 8.5, MariaDB and nginx, copies `.env.example` to `.env`, runs `composer install` (which generates `web/`, the symlinks and `wp-config.php`), installs WordPress with `admin` / `admin`, and activates the theme.

```bash
ddev launch              # the site
ddev launch /wp/wp-admin # the admin
```

### Use the monorepo packages

From `packages/demo`, a normal demo install uses the released `studiometa/foehn` packages. To test changes from this monorepo, first run `ddev start` once so Composer installs all dependencies. Then enable the local sync hook and restart:

```bash
cp .ddev/config.local.example.yaml .ddev/config.local.yaml
ddev restart
```

The hook mirrors `packages/foehn`, `packages/installer` and `packages/acf` into `vendor/`, regenerates the editor entry, and clears the full discovery cache. Run `ddev restart` after framework source changes. Without the hook, the demo can run an older released framework even while its theme uses code from the current branch.

The hook copies source only. If a package changes its Composer dependencies, resolve them through temporary path repositories before restarting. Each path repository gives its package the version the demo pins, the version of the root `package.json`: `studiometa/foehn-acf` requires that exact framework version, which a `@dev` branch is not. These commands need PHP 8.5 and Composer on the host because DDEV mounts `packages/demo` without its sibling packages:

```bash
cp composer.json composer.monorepo.json
cp composer.lock composer.monorepo.lock
version="$(node -p 'require("../../package.json").version')"
for package in foehn:foehn foehn-installer:installer foehn-acf:acf; do
  COMPOSER=composer.monorepo.json composer config "repositories.${package%%:*}" \
    "{\"type\":\"path\",\"url\":\"../${package##*:}\",\"options\":{\"symlink\":false,\"versions\":{\"studiometa/${package%%:*}\":\"$version\"}}}"
done
COMPOSER=composer.monorepo.json composer update 'studiometa/*' --with-dependencies
rm composer.monorepo.json composer.monorepo.lock
```

### Seeded content

`wp foehn seed` writes the whole site: thirty photographs, six projects, three testimonials, the Home, About and Legal pages, three journal posts in the Notes category, the menus and the theme settings. The journal post "Contact sheets for the studio" is protected by the password `foehn`.

## Where it runs

It is up at **<https://foehn-demo.fly.dev>**, so the site can be looked at without installing anything.

One Fly app carries the whole of it: `Dockerfile` builds on the `-db` variant of Føhn's runtime image, so MariaDB runs beside PHP in the same container and there is no database app to deploy alongside. The photographs are in a Tigris bucket, served under the site's own domain, and the portfolio is built by the machine itself on a boot that finds an empty volume — see `docker/entrypoint.d/35-demo-seed.sh`.

See [the guide](https://studiometa.github.io/foehn-framework/guide/deployment-fly) for what the app is made of, which values are secrets, and the two failures the first deployment found.

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

Uploads are offloaded to object storage, because `web/wp-content/uploads/` is the one directory a generated web root does not make disposable. `humanmade/s3-uploads` does the offloading and RustFS stands in for the bucket, as a ddev service in `.ddev/docker-compose.rustfs.yaml` — so the whole path runs with no credentials, no network and no bill. See [the guide](https://studiometa.github.io/foehn-framework/guide/uploads).

Both ways of sizing an image are here, because they answer different questions. The homepage cards use a registered `#[AsImageSize]`: one shape, known up front, generated at upload. The plates on a project page cannot — they crop to 3:4 standing and 3:2 lying down, and a size registered today would apply to nothing already uploaded — so `photograph.twig` asks for the crop it wants with `image_url()` and `GlideTransformer` produces it. Measured here, a plate is 356ms the first time and 24ms every time after, because `.ddev/nginx/image-cache.conf` serves the cached result straight from the bucket and WordPress never boots. See [the guide](https://studiometa.github.io/foehn-framework/guide/images).

ACF runs on the free plugin, `wpackagist-plugin/advanced-custom-fields`, which CI can install. `theme/app/Fields/ProjectPressFields.php` is a `#[AsAcfFieldGroup]` on projects, with the `ButtonLinkBuilder` fragment of [`studiometa/foehn-acf`](https://github.com/studiometa/foehn-acf), and a project page prints its press quote. ACF blocks and options pages need ACF Pro, so they are not here.

The cron jobs, the jobs and the warm-up of the page cache run on Action Scheduler, from the plugin `wpackagist-plugin/action-scheduler`. ddev and the image entrypoint activate it. Without it, Føhn schedules nothing and dispatches nothing. Locally, `DISABLE_WP_CRON` is on and nothing runs the real cron: run `ddev wp cron event run --due-now` or `ddev wp action-scheduler run` to run what is due.

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

## Tests

```bash
composer test:demo                # the PHP suite, from the monorepo root
npm run -w @studiometa/foehn-demo test:run   # browser tests
./tests/smoke/run.sh              # against a started ddev
./tests/smoke/page-cache-bucket.sh # the page cache in the RustFS bucket, from examples/
```

`tests/smoke/` is the important one. The PHP suite runs against WordPress function stubs, so a discovery that registers nothing at all still passes it — on 2026-08-19 that was 1409 passing tests and a fatal error on every front-end page. The smoke test drives real requests against a real WordPress and asserts inside it, and CI runs it twice: on a cold discovery cache and again on a warm one. `page-cache-bucket.sh` turns bucket storage on for its run with `examples/page-cache.production.config.php`, edits a project to check the purge, and puts the demo back when it ends.

Every assertion in there was checked to fail when its feature is removed. An assertion that cannot fail is worse than none.

## License

MIT
