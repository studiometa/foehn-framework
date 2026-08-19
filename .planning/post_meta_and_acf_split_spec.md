# Typed post meta, and ACF as an optional package

Two changes that are really one: custom fields stop requiring a paid plugin, and the ACF integration becomes something a project opts into.

| Property             | Decision                                                                             |
| -------------------- | ------------------------------------------------------------------------------------ |
| New attribute        | `#[AsPostMeta]` → `register_meta()`, repeatable, on the model that owns the field.   |
| New package          | `studiometa/foehn-acf`, requiring `studiometa/foehn` and `stoutlogic/acf-builder`.   |
| Prerequisite         | `#[AsDiscovery]`, because a package cannot currently add a discovery at all. See §1. |
| Breaking             | Yes. A project using `#[AsAcfBlock]` must add one Composer requirement.              |
| Where the code lives | `packages/foehn/src/PostMeta/` and a new `packages/acf/`.                            |

## 1. The blocker: discoveries cannot be added from outside

`DiscoveryRunner::getDiscoveryPhases()` is a hardcoded static map of nineteen classes. Nothing outside that file can add to it, so an ACF package could ship `AcfBlockDiscovery` and it would never run.

The same wall makes `docs/guide/custom-discovery.md` a documented feature that has never worked: it walks the reader through writing a discovery class and then stops, because there is no way to register one. That guide is now doubly wrong — it also references `WpDiscovery`, `CacheableDiscovery` and Føhn's own `DiscoveryLocation`, all deleted on 2026-08-19.

### `#[AsDiscovery]`

A discovery declares its own phase, and the runner finds discoveries the same way it finds everything else:

```php
#[AsDiscovery(phase: DiscoveryPhase::Main)]
final class AcfBlockDiscovery implements Discovery { … }
```

- `DiscoveryRunner::getDiscoveryPhases()` and `getAllDiscoveryClasses()` are deleted. The phase comes from the attribute, defaulting to `Main`.
- The runner switches from `BootDiscovery::build($explicitClasses, $locations)` to Tempest's two-pass form, which runs `DiscoveryDiscovery` first and resolves whatever it found. Tempest already does exactly this; Føhn opted out of it in 2026-02 to keep control of timing, and timing now lives on the class instead.
- Anything implementing `Discovery` in a scanned location is picked up, which is what makes both the ACF package and the documented custom-discovery guide work. Abstract classes and stubs are excluded by the existing `isConcrete()` guard and `#[SkipDiscovery]`.

This removes a static list of nineteen class names and replaces it with nothing. Do this first; both other changes depend on it, and `wp foehn discovery:list` becomes far more useful when a discovery can come from anywhere.

## 2. `#[AsPostMeta]`

`register_meta()` is what puts a custom field in the REST API and therefore in the block editor and in block bindings. Føhn touches it in zero files today, which is why every custom field goes through ACF.

### Shape

The attribute goes on the model that owns the field, repeatable, because a model already declares its post type and already holds the accessors:

```php
#[AsPostType(name: 'product', singular: 'Product', plural: 'Products')]
#[AsPostMeta(key: 'price', type: 'number')]
#[AsPostMeta(key: 'sku', type: 'string', showInRest: false)]
#[AsPostMeta(key: 'gallery', type: 'integer', single: false)]
final class Product extends Post
{
    public function price(): ?float
    {
        return $this->meta('price');
    }
}
```

| Argument        | Default        | Notes                                                                                 |
| --------------- | -------------- | ------------------------------------------------------------------------------------- |
| `key`           | _required_     | The meta key.                                                                         |
| `type`          | `'string'`     | `string`, `boolean`, `integer`, `number`, `array`, `object`.                          |
| `single`        | `true`         | `false` registers a repeatable field.                                                 |
| `showInRest`    | `true`         | The default is on: without REST the field is invisible to the editor and to bindings. |
| `description`   | `''`           | Surfaces in the REST schema.                                                          |
| `default`       | `null`         | Must be `var_export`-safe. See below.                                                 |
| `objectType`    | `'post'`       | `post`, `term`, `user`, `comment`.                                                    |
| `objectSubtype` | inferred       | From `#[AsPostType]`, `#[AsTaxonomy]` or `#[AsTimberModel]` on the same class.        |
| `capability`    | `'edit_posts'` | Becomes `auth_callback`.                                                              |
| `sanitize`      | `null`         | A method name on the declaring class, not a closure. See below.                       |

### Two constraints that shape the API

**No closures, anywhere.** A discovery item is written to the cache through `var_export`, so an attribute argument holding a closure would break the moment caching was enabled — and would break only in production, where caching is on. `sanitize` therefore names a method on the declaring class, resolved when `apply()` runs. The same rule already governs every other Føhn attribute; it is worth stating in the guide because `register_meta()`'s own signature invites a callable.

**Subtype inference, not repetition.** `register_meta('post', 'price', […])` registers for _every_ post type unless `object_subtype` is set. Reading the subtype off the model's own `#[AsPostType]` is the difference between a correct default and a footgun.

### Registration

`PostMetaDiscovery` in the `Main` phase (`init`), which is where `register_meta()` belongs. One item per attribute; `apply()` calls `register_meta()` with the REST schema derived from `type` and `single`.

### It does not conflict with ACF

ACF stores its values in ordinary post meta. Declaring `#[AsPostMeta(key: 'price')]` for a key ACF also manages is legitimate and useful: ACF keeps the editing UI, while the declaration gives the key a REST schema and makes it bindable. The two are not alternatives at the storage layer, and the guide should say so — it is the migration path for existing projects.

## 3. Splitting ACF out

### What moves to `packages/acf`

Twenty files, wholesale:

| Group      | Files                                                                    |
| ---------- | ------------------------------------------------------------------------ |
| Attributes | `AsAcfBlock`, `AsAcfFieldGroup`, `AsAcfOptionsPage`                      |
| Discovery  | `AcfBlockDiscovery`, `AcfFieldGroupDiscovery`, `AcfOptionsPageDiscovery` |
| Contracts  | `AcfBlockInterface`, `AcfFieldGroupInterface`, `AcfOptionsPageInterface` |
| Blocks     | `AcfBlockRenderer`, `AcfFieldTransformer`                                |
| Config     | `AcfConfig`                                                              |
| Services   | `AcfOptionsService`                                                      |
| Fragments  | `Acf/Fragments/{Background,ButtonLink,ResponsiveImage,Spacing}Builder`   |
| Console    | `Make{AcfBlock,FieldGroup,OptionsPage}Command` and their three stubs     |

Plus their tests, and the `docs/guide/acf-*.md`, `docs/guide/field-fragments.md` and `docs/api/*acf*` pages.

### What stays, and why

- **`Data/{ImageData,LinkData,SpacingData}`** stay in core. Their `fromAcf()` factories take an array, not an ACF object, so they carry no dependency — only a name that implies one. Renaming them is optional and not worth a breaking change on its own.
- **`Blocks/Concerns/ValidatesFields`** is generic; only its docblock example mentions `AcfBlockInterface`. Fix the docblock.
- **`Hooks/YouTubeNoCookieHooks`** keeps its `acf/format_value/type=oembed` filter. It is opt-in and the filter simply never fires without ACF.
- **`stoutlogic/acf-builder`** is already absent from `packages/foehn/composer.json` — it moves from being an undeclared assumption to a real requirement of the new package.

### Two mechanisms the package needs, both of which already exist

This is why the split is cheap now and would not have been last week:

1. **Config defaults.** `Kernel` currently registers `new AcfConfig()` as a default. The package instead ships `src/Config/acf.config.php` returning one, and `ConfigLoader` reads vendor locations before app ones — so the package supplies the default and a project's `app/acf.config.php` still overrides it. No service-provider concept required.
2. **Services.** `Kernel` registers `AcfBlockRenderer` explicitly today. Its constructor takes `AcfConfig`, so Tempest's container autowires it once `AcfConfig` resolves. The explicit registration is deleted, not moved.

### One detail that will silently break the package

`DiscoveryLocations` builds its list from `vendor/composer/installed.json`, and a package is only scanned when it either requires a `tempest/*` package or opts in:

```json
{ "extra": { "tempest": { "can-discover": true } } }
```

`studiometa/foehn-acf` requires `studiometa/foehn`, **not** `tempest/*`, so without that `extra` block its discoveries are never found and everything fails quietly. It belongs in the package's `composer.json` from the first commit, with a comment saying why.

### Monorepo plumbing

- `packages/acf/` with its own `phpunit.xml`, a `test:acf` script, and the suite added to the CI matrix and the coverage upload.
- `.github/workflows/split.yml` gains the new package.
- Root `composer.json`: a path repository and an `autoload-dev` entry for the tests.

## 4. The starter should stop being an ACF demo

`HeroBlock` is the starter's only ACF block, and it is the reason `AcfBlockDiscovery` reports one discovered item that can never register in CI — ACF Pro is not installed there, so that path has never run end to end.

Convert `HeroBlock` to a native block backed by `#[AsPostMeta]`, and document ACF as an add-on with its own short guide. The starter then demonstrates the default path, its integration test covers the whole of what it ships, and the ACF package carries its own example.

## 5. Order and estimates

| Phase                                                                       | Estimate |
| --------------------------------------------------------------------------- | -------- |
| 1. `#[AsDiscovery]`, deleting the phase map; fix the custom-discovery guide | 1–2 days |
| 2. `#[AsPostMeta]` and `PostMetaDiscovery`, with the guide                  | 2 days   |
| 3. Extract `packages/acf`, move tests and docs, CI and split                | 2–3 days |
| 4. Convert the starter's `HeroBlock`, extend the smoke test                 | 1 day    |

Phase 1 first is not a preference: phases 3 and 4 cannot work without it.

## 6. Risks

- **`#[AsDiscovery]` widens what gets discovered.** Anything implementing `Discovery` in a scanned location now runs. That is the feature, but it means a half-written discovery in `app/` starts executing. The `isConcrete()` guard and `#[SkipDiscovery]` cover the known cases; `discovery:list` is what makes the rest visible.
- **The two-pass build costs one extra scan** when the cache is cold. Measure it on the starter before and after; the discovery cache absorbs it in production.
- **Existing projects on 0.4.x break** on upgrade if they use ACF attributes, by exactly one `composer require`. It needs a CHANGELOG breaking note and a line in the 0.5 migration guide.
