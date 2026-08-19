# Foehn Demo

Every feature [Føhn](https://github.com/studiometa/foehn-framework) ships, in one working WordPress theme.

This is the reference project, not the one to start from. It exists to be read, to be poked at, and to be tested: the framework's end-to-end and browser suites run against it, so every attribute here is exercised against a real WordPress rather than against function stubs.

**Starting a project?** Use [`studiometa/foehn-starter`](../starter), which is the same theme with the demonstrations taken out.

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

| Attribute                     | Here                                                           |
| ----------------------------- | -------------------------------------------------------------- |
| `#[AsPostType]`               | `Models/Product.php`, `Models/Testimonial.php`                 |
| `#[AsPostMeta]`               | `Models/Product.php` — `price`, `sale_price`                   |
| `#[AsTaxonomy]`               | `Taxonomies/ProductCategory.php`, `Taxonomies/ProductTag.php`  |
| `#[AsBlock]`                  | `Blocks/HeroBlock.php`, `CalloutBlock.php`, `SectionBlock.php` |
| `#[AsBlockBinding]`           | `Bindings/ReadingTime.php`                                     |
| `#[AsSettingsPage]`           | `Settings/ThemeSettings.php`, with a Twig form                 |
| `#[AsRewriteRule]`            | `Routes/HealthCheckRoute.php` — `GET /_health`                 |
| `#[AsTemplateController]`     | `Controllers/`                                                 |
| `#[AsContextProvider]`        | `ContextProviders/GlobalContextProvider.php`                   |
| `#[AsImageSize]`              | `ImageSizes/`                                                  |
| `#[AsMenu]`                   | `Menus/`                                                       |
| `#[AsAction]` / `#[AsFilter]` | `Hooks/ThemeHooks.php`                                         |
| Arrayable DTOs                | `Data/HeroContext.php`                                         |

The framework's own cleanup and security hooks are opted into from `theme/app/foehn.config.php`, `S3UploadsEndpoint` among them.

Uploads are offloaded to object storage, because `web/wp-content/uploads/` is the one directory a generated web root does not make disposable. `humanmade/s3-uploads` does the offloading and MinIO stands in for the bucket, as a ddev service in `.ddev/docker-compose.minio.yaml` — so the whole path runs with no credentials, no network and no bill. See [the guide](../../docs/guide/uploads.md).

ACF is **not** demonstrated here. It needs ACF Pro, a paid plugin CI cannot install, so anything relying on it would be a path nothing ever runs — which is the reason the demo has no ACF block. [`studiometa/foehn-acf`](../acf) carries its own examples.

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
