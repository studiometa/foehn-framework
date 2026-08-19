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

## What is demonstrated where

| Attribute                                                         | File                                                         |
| ----------------------------------------------------------------- | ------------------------------------------------------------ |
| [`#[AsPostType]`](/api/as-post-type)                              | `app/Models/Product.php`, `app/Models/Testimonial.php`       |
| [`#[AsPostMeta]`](/api/as-post-meta)                              | `app/Models/Product.php` — `price`, `sale_price`             |
| [`#[AsTaxonomy]`](/api/as-taxonomy)                               | `app/Taxonomies/`                                            |
| [`#[AsBlock]`](/api/as-block)                                     | `app/Blocks/` — a container, a sidebar-driven one, a DTO one |
| [`#[AsBlockBinding]`](/api/as-block-binding)                      | `app/Bindings/ReadingTime.php`                               |
| [`#[AsSettingsPage]`](/api/as-settings-page)                      | `app/Settings/ThemeSettings.php`, with a Twig form           |
| [`#[AsRewriteRule]`](/api/as-rewrite-rule)                        | `app/Routes/HealthCheckRoute.php` — `GET /_health`           |
| [`#[AsTemplateController]`](/api/as-template-controller)          | `app/Controllers/`                                           |
| [`#[AsContextProvider]`](/api/as-context-provider)                | `app/ContextProviders/`                                      |
| [`#[AsImageSize]`](/api/as-image-size)                            | `app/ImageSizes/`                                            |
| [`#[AsMenu]`](/api/as-menu)                                       | `app/Menus/`                                                 |
| [`#[AsAction]`](/api/as-action) / [`#[AsFilter]`](/api/as-filter) | `app/Hooks/ThemeHooks.php`                                   |
| [Arrayable DTOs](/guide/arrayable-dtos)                           | `app/Data/HeroContext.php`                                   |

The theme's namespace is `Demo\` rather than `App\`, which is the only thing in it you would not copy: `App\` is what the starter uses and what a project of your own should.

## What is not here

**ACF.** It needs ACF Pro, a paid plugin CI cannot install, so an ACF block here would be a path nothing ever runs — which is exactly what it was before the integration moved out. [`studiometa/foehn-acf`](/guide/acf-blocks) carries its own examples.

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
