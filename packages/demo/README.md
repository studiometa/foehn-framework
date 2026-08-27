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

## What is demonstrated where

| Attribute                           | Here                                                                                                                    |
| ----------------------------------- | ----------------------------------------------------------------------------------------------------------------------- |
| `#[AsPostType]`                     | `Models/Project.php`, `Models/Testimonial.php`                                                                          |
| `#[AsPostMeta]`                     | `Models/Project.php` — `client`, `year`, `location`, `camera`                                                           |
| `#[AsTaxonomy]`                     | `Taxonomies/ProjectCategory.php`, `Taxonomies/ProjectTag.php`                                                           |
| `#[AsBlock]`                        | `Blocks/HeroBlock.php`, `CalloutBlock.php`, `SectionBlock.php`                                                          |
| `#[AsBlockBinding]`                 | `Bindings/ReadingTime.php`                                                                                              |
| `#[AsSettingsPage]`                 | `Settings/ThemeSettings.php`, with a Twig form                                                                          |
| `#[AsRewriteRule]`                  | `Routes/HealthCheckRoute.php` — `GET /_health`                                                                          |
| `#[AsTemplateController]`           | `Controllers/` — front page, projects index, project, page                                                              |
| Page cache                          | `theme/app/page-cache.config.php`                                                                                       |
| Section rendering / AJAX pagination | `templates/pages/archive-project.twig`, `templates/sections/project-index.twig`, `templates/components/pagination.twig` |
| `#[AsContextProvider]`              | `ContextProviders/GlobalContextProvider.php`                                                                            |
| `#[AsImageSize]`                    | `ImageSizes/`, used by `components/card-project.twig`                                                                   |
| `ImageTransformer` (Glide)          | `foehn.config.php`, used by `components/photograph.twig`                                                                |
| `#[AsMenu]`                         | `Menus/`                                                                                                                |
| `#[AsAction]` / `#[AsFilter]`       | `Hooks/ThemeHooks.php`                                                                                                  |
| Arrayable DTOs                      | `Data/HeroContext.php`                                                                                                  |

The framework's own cleanup and security hooks are opted into from `theme/app/foehn.config.php`, `S3UploadsEndpoint` among them.

Uploads are offloaded to object storage, because `web/wp-content/uploads/` is the one directory a generated web root does not make disposable. `humanmade/s3-uploads` does the offloading and MinIO stands in for the bucket, as a ddev service in `.ddev/docker-compose.minio.yaml` — so the whole path runs with no credentials, no network and no bill. See [the guide](https://studiometa.github.io/foehn-framework/guide/uploads).

Both ways of sizing an image are here, because they answer different questions. The homepage cards use a registered `#[AsImageSize]`: one shape, known up front, generated at upload. The plates on a project page cannot — they crop to 3:4 standing and 3:2 lying down, and a size registered today would apply to nothing already uploaded — so `photograph.twig` asks for the crop it wants with `image_url()` and `GlideTransformer` produces it. Measured here, a plate is 356ms the first time and 24ms every time after, because `.ddev/nginx/image-cache.conf` serves the cached result straight from the bucket and WordPress never boots. See [the guide](https://studiometa.github.io/foehn-framework/guide/images).

ACF is **not** demonstrated here. It needs ACF Pro, a paid plugin CI cannot install, so anything relying on it would be a path nothing ever runs — which is the reason the demo has no ACF block. [`studiometa/foehn-acf`](https://github.com/studiometa/foehn-acf) carries its own examples.

## Tests

```bash
composer test:demo                # the PHP suite, from the monorepo root
npm run -w @studiometa/foehn-demo test:run   # browser tests
./tests/smoke/run.sh              # against a started ddev
```

`tests/smoke/` is the important one. The PHP suite runs against WordPress function stubs, so a discovery that registers nothing at all still passes it — on 2026-08-19 that was 1409 passing tests and a fatal error on every front-end page. The smoke test drives real requests against a real WordPress and asserts inside it, and CI runs it twice: on a cold discovery cache and again on a warm one.

Every assertion in there was checked to fail when its feature is removed. An assertion that cannot fail is worse than none.

## License

MIT
