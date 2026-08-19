# Five framework additions

`discovery:list`, `#[AsBlockBinding]`, `#[AsRewriteRule]`, Settings API pages, and `studiometa/foehn-testing`. Specced together because they share the same constraints and would otherwise repeat them.

Prerequisite for the attributes: `#[AsDiscovery]`, from `post_meta_and_acf_split_spec.md` §1.

## 0. Rules every one of these obeys

**Attribute arguments must survive `var_export`.** Discovery items are written to the cache that way, so an attribute cannot hold a closure. Every callback below is therefore a **method name on the discovered class**, resolved when `apply()` runs. This is not a style preference: a closure in an attribute works in development and fails only once caching is enabled, which is to say only in production.

**Registration happens on the WordPress hook that owns it**, through the discovery phase, never at boot. `register_block_bindings_source()`, `register_meta()`, `add_rewrite_rule()` and `register_setting()` all want `init` — the `Main` phase. Admin menus want `admin_menu`, which needs a fourth phase or an `add_action` from within `apply()`; prefer the latter, since one discovery wanting a different hook is not a reason to add a phase.

**A missing platform function is a skip, not a fatal.** The ACF discoveries already guard with `function_exists()`. Anything targeting a newer WordPress does the same, and says so when `FoehnConfig::debug` is on.

## 1. `wp foehn discovery:list`

The framework cannot currently say what it found. On 2026-08-19 that cost an hour on a bug whose whole symptom was "nothing registered": `discovery:status` reports cache warmth and nothing reports items.

```
$ wp foehn discovery:list --discovery=Hook

HookDiscovery — 18 items
  App\                     Studiometa\Foehn\ (vendor)
  ─────────────────────────────────────────────────────────
  App\Hooks\ThemeHooks     AsAction   after_setup_theme  setupTheme
  App\Hooks\ThemeHooks     AsFilter   excerpt_length     excerptLength
  …

12 discoveries with items, 7 empty. Locations: App\ (scanned), Studiometa\Foehn\ (cached).
```

- `--discovery=<name>` filters by class or short name, `--location=<namespace>` by origin, `--format=table|json|count`.
- Whether each location was **scanned or restored from cache** is part of the output. That single line is what makes a stale cache diagnosable.
- Empty discoveries are counted, not hidden: "PostTypeDiscovery — 0 items" is the answer to most "why is my post type missing" questions.

**How to describe an item without touching nineteen classes.** An item is an array holding an attribute instance plus reflection facts. The renderer reflects the attribute's promoted constructor and prints its scalar arguments, exactly as `AttributeCodec` reflects it for the cache. No interface method to add, and third-party discoveries render for free.

Reads `DiscoveryRunner::getDiscoveries()`, which discovers without applying. Nothing is registered by listing.

## 2. `#[AsBlockBinding]`

```php
register_block_bindings_source(string $source_name, array $source_properties): WP_Block_Bindings_Source|false
// label (string, required), get_value_callback (callable, required), uses_context (string[])
// get_value_callback($source_args, WP_Block $block_instance, string $attribute_name): mixed
// must be called on init
```

### What it is actually for

Core already ships `core/post-meta`. A field declared with `#[AsPostMeta]` — `single`, `show_in_rest` — is bindable through it with **no custom source at all**. So `#[AsBlockBinding]` is not the way to get meta into a block; it is the way to bind a block to a value that is _computed_: a formatted price, a reading time, a figure from an external service, a value assembled from several meta keys.

Saying that plainly in the guide matters more than the attribute does. The common case needs no code.

### Shape

```php
#[AsBlockBinding(
    name: 'theme/reading-time',
    label: 'Reading time',
    usesContext: ['postId'],
)]
final readonly class ReadingTime implements BlockBindingInterface
{
    public function value(array $args, WP_Block $block, string $attribute): ?string
    {
        …
    }
}
```

`apply()` registers the source on `init` with `get_value_callback` pointing at the container-resolved instance's `value` method — a callable built at apply time, never stored in the item.

| Argument      | Default    | Notes                                           |
| ------------- | ---------- | ----------------------------------------------- |
| `name`        | _required_ | `namespace/name`; WordPress requires the slash. |
| `label`       | _required_ | Shown in the editor.                            |
| `usesContext` | `[]`       | Block context keys the callback needs.          |

**Verify before building:** which block attributes accept bindings is version-dependent and the reference page does not enumerate them. Check against the target WordPress version rather than trusting a list written today.

## 3. `#[AsRewriteRule]`

Zero files touch `add_rewrite_rule` today, so every webhook, form handler and signed URL is hand-rolled — usually as a `template_redirect` hook sniffing `$_SERVER['REQUEST_URI']`, which is the thing rewrite rules exist to avoid.

### One class, both halves

A rule without a handler is half a feature. The ergonomic win over raw WordPress is that one class declares the URL and answers it:

```php
#[AsRewriteRule(
    regex: '^webhook/stripe/?$',
    query: 'index.php?foehn_route=stripe-webhook',
    queryVars: ['foehn_route'],
)]
final readonly class StripeWebhook implements RewriteHandlerInterface
{
    public function handle(WP $wp): void
    {
        …
        exit;
    }
}
```

- `apply()` calls `add_rewrite_rule()` on `init`, adds `queryVars` through the `query_vars` filter, and hooks `parse_request` to dispatch to `handle()` when the query var matches.
- `after` (`'top'` or `'bottom'`) defaults to `'top'`, matching what a webhook wants.
- Handling is optional: a rule that only rewrites to an existing template needs no interface.

### Flushing, which is the whole difficulty

`flush_rewrite_rules()` rewrites an option and is slow enough that calling it per request is a well-known way to ruin a site. Rules registered in code also do nothing until the rules are flushed once.

The design: hash the discovered rule set, store it in `foehn_rewrite_rules_hash`, and flush exactly when the hash changes. The rules come from cached discovery items, so the hash costs nothing to compute. `wp foehn rewrite:flush` forces it.

That gives the behaviour a developer expects — add a rule, reload, it works — without a flush on every request. Document that plain permalinks bypass rewrite rules entirely, because someone will test on a fresh install and file a bug.

## 4. Settings API pages

Needed because ACF is becoming optional: `#[AsAcfOptionsPage]` is currently the only way Føhn offers a settings screen, and `register_setting` appears in zero files.

### Shape, deliberately mirroring the ACF one

```php
#[AsOptionsPage(
    slug: 'theme-settings',
    title: 'Theme settings',
    parent: 'themes.php',
    capability: 'manage_options',
)]
final readonly class ThemeSettings implements OptionsPageInterface
{
    /** @return list<Field> */
    public static function fields(): array
    {
        return [
            Field::text('contact_email', 'Contact email', sanitize: 'sanitize_email'),
            Field::textarea('footer_note', 'Footer note'),
            Field::checkbox('show_banner', 'Show the banner', default: false),
            Field::select('layout', 'Layout', options: ['wide' => 'Wide', 'narrow' => 'Narrow']),
        ];
    }
}
```

`fields()` returning a list mirrors `AcfOptionsPageInterface::fields()` returning a `FieldsBuilder`, so migrating a page off ACF is mechanical rather than a rewrite.

- **The framework renders the form.** `settings_fields()`, `do_settings_sections()` and the submit button come from Føhn; a project never writes a render callback. That is the boilerplate worth deleting — the Settings API's own ergonomics are the reason people reach for ACF.
- **v1 field types**: text, textarea, number, checkbox, select, radio. Native inputs only. A media picker needs editor JS and belongs in a later pass.
- **Sanitisation defaults by type** (`sanitize_text_field`, `absint`, a boolean cast), overridable per field with a function name or a method on the page class.
- **`show_in_rest` defaults to `false`**, unlike `#[AsPostMeta]`. Settings are configuration, sometimes credentials; exposure is opt-in. Note that `description` only has an effect when `show_in_rest` is set.
- **Reading**: `Settings::get('contact_email')` typed from the field definition, wrapping `get_option` with the declared default. One helper, not a facade.

Registration spans two hooks: `register_setting()` on `init`, the menu and sections on `admin_menu`. `apply()` adds the second itself, as §0 says.

## 5. `studiometa/foehn-testing`

### What it is

The framework maintains 1332 lines of WordPress function stubs and a set of Pest helpers, and its own 1390 tests run on them without a WordPress install. That asset is currently private to the repository.

Contents: the stubs and their `wp_stub_*` recorders, the container helpers (`bootTestContainer`, `tearDownTestContainer`), the discovery helpers (`testDiscoveryLocation`, `testVendorLocation`, `testAppPath`, `discoverFixture`, `testDiscoveryRunner`, `restoreThroughCacheFile`), and a documented `phpunit.xml` fragment.

### Why it is worth publishing

A theme developer can unit-test their own blocks, hooks, controllers and discoveries with no WordPress, no database and no fixtures. Neither Acorn nor Sage offers that. It is also the honest answer to the lesson of 2026-08-19: unit tests over stubs are load-bearing but not sufficient, and the package should say so in its own README.

### Decisions

- **One home.** The stubs move to the package; `studiometa/foehn` requires it under `require-dev`. Copying them would guarantee divergence.
- **Its surface becomes public.** Which functions are stubbed, and what each records, turn into promises. Adding a stub is a minor; removing one or changing what it records is breaking. Worth a short compatibility note in the package README.
- **Scope boundary, stated loudly.** These are stubs, not a WordPress test harness. `wp_insert_post()` will not write to a database. Integration testing means a real WordPress, which is what `packages/starter/tests/smoke/` is for. Without that sentence the package invites the exact false confidence that let this year's fatal error ship.

## 6. Order and estimates

| Item                       | Estimate | Why here                                                              |
| -------------------------- | -------- | --------------------------------------------------------------------- |
| `discovery:list`           | 1 day    | Makes everything after it debuggable. Do it first.                    |
| `#[AsRewriteRule]`         | 2 days   | Self-contained; the flush hash is the only subtle part.               |
| Settings API pages         | 2–3 days | Needed before ACF can be described as optional.                       |
| `#[AsBlockBinding]`        | 1–2 days | After `#[AsPostMeta]`, so the guide can explain when _not_ to use it. |
| `studiometa/foehn-testing` | 2–3 days | Mostly extraction, CI and documentation; no new behaviour.            |

## 7. Risks

- **`discovery:list` output on a large project** could be thousands of lines. `--format=count` and per-discovery filtering are not conveniences.
- **Rewrite flushing is stateful.** The hash option makes it idempotent, but a project that flushes elsewhere can still fight it. The command exists so the answer to "my rule is not matching" is one line long.
- **Settings pages are a slope.** Text and checkboxes are a day; repeaters, conditional fields and media pickers are ACF's actual product. v1 must stop at native inputs and say so, or it becomes a field builder nobody asked Føhn to maintain.
- **Publishing the stubs freezes them.** Today they can change with the framework's needs; afterwards they cannot, without a version bump.
