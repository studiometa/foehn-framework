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

| Feature                                                           | File                                                                                                                           |
| ----------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------ |
| [`#[AsPostType]`](/api/as-post-type)                              | `theme/app/Models/Project.php`, `theme/app/Models/Testimonial.php`                                                             |
| [`#[AsPostMeta]`](/api/as-post-meta)                              | `theme/app/Models/Project.php` — `client`, `year`, `location`, `camera`                                                        |
| [`#[AsTaxonomy]`](/api/as-taxonomy)                               | `theme/app/Taxonomies/`                                                                                                        |
| [`#[AsBlock]`](/api/as-block)                                     | `theme/app/Blocks/` — a container, a sidebar-driven block, and a DTO block                                                     |
| [`#[AsBlockBinding]`](/api/as-block-binding)                      | `theme/app/Bindings/ReadingTime.php`                                                                                           |
| [`#[AsSettingsPage]`](/api/as-settings-page)                      | `theme/app/Settings/ThemeSettings.php`, with a Twig form                                                                       |
| [`#[AsRewriteRule]`](/api/as-rewrite-rule)                        | `theme/app/Routes/HealthCheckRoute.php` — `GET /_health`                                                                       |
| [`#[AsTemplateController]`](/api/as-template-controller)          | `theme/app/Controllers/`                                                                                                       |
| [Section rendering](/guide/section-rendering)                     | AJAX pagination in `theme/templates/sections/project-index.twig`; lazy loading in `theme/templates/sections/testimonials.twig` |
| [Page cache](/guide/page-cache)                                   | `theme/app/page-cache.config.php`                                                                                              |
| [`#[AsContextProvider]`](/api/as-context-provider)                | `theme/app/ContextProviders/`                                                                                                  |
| [`#[AsImageSize]`](/api/as-image-size)                            | `theme/app/ImageSizes/`                                                                                                        |
| [Image transforms](/guide/images)                                 | `theme/templates/components/photograph.twig`                                                                                   |
| [`#[AsMenu]`](/api/as-menu)                                       | `theme/app/Menus/`                                                                                                             |
| [`#[AsAction]`](/api/as-action) / [`#[AsFilter]`](/api/as-filter) | `theme/app/Hooks/ThemeHooks.php`                                                                                               |
| [Arrayable DTOs](/guide/arrayable-dtos)                           | `theme/app/Data/HeroContext.php`                                                                                               |

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
