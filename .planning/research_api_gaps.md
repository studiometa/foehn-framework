# API gaps: what Føhn is missing

Two questions, asked on 2026-08-19. What do comparable frameworks give that Føhn does not, and what has WordPress itself shipped that a theme should be able to reach through an attribute.

The framing that decides both: Acorn's premise is Laravel inside WordPress. Føhn's is attributes and discovery over WordPress-native APIs. So the useful gap is never "which Laravel component is missing" — it is "which WordPress API still needs hand-written boilerplate in every project".

## 1. Decided

| Feature                    | Shape                                                                | Notes                                                                                      |
| -------------------------- | -------------------------------------------------------------------- | ------------------------------------------------------------------------------------------ |
| Typed custom fields        | `#[AsPostMeta]` → `register_meta()`                                  | 0 files touch `register_meta` today. Removes ACF Pro as a hard requirement.                |
| ACF as an optional package | Move `AsAcf*`, `Acf/`, `AcfOptionsService` to `studiometa/foehn-acf` | Follows from the above: ACF becomes a choice, not a dependency.                            |
| Block bindings             | `#[AsBlockBinding]` → `register_block_bindings_source()`             | WP 6.5+. Binds core blocks to meta or a custom source. Pairs with `#[AsPostMeta]`.         |
| Discovery introspection    | `wp foehn discovery:list`                                            | Nothing can say what was discovered. The 2026-08-19 fatal error would have been obvious.   |
| Custom URLs                | `#[AsRewriteRule]`, query vars, `wp foehn rewrite:flush`             | 0 files touch `add_rewrite_rule`. Webhooks and form handlers without hand-rolled rewrites. |
| Testable themes            | `studiometa/foehn-testing`                                           | Publish the 1300-line WP stub set. Neither Acorn nor Sage offers a unit-testing story.     |
| Options pages without ACF  | Settings API (`register_setting`)                                    | 0 files today. Required once ACF is optional.                                              |

### Not decided

- `#[AsAdminColumn]` / `#[AsAdminFilter]` — see §2.5 before building this.
- `#[AsBlockStyle]` / `#[AsBlockVariation]` — cheap, uncontroversial, unscheduled.
- Twig `{% cache %}` fragment caching — complements the page cache; belongs in that discussion.

### Deliberately not copied from Acorn

- **Eloquent or any DB layer.** `PostQueryBuilder` and Timber models cover the WordPress data model. A second ORM over `wp_posts` fights the platform.
- **A full router.** The template hierarchy plus `#[AsTemplateController]` and `#[AsRestRoute]` cover it; rewrite rules are the missing 10%, not a router.
- **Service providers.** Discovery _is_ the provider mechanism. Two ways to register one thing is worse than one.
- **Sessions, broadcasting, mail, notifications, validation.** WordPress or a plugin owns each, and REST already validates through `args` schemas.

## 2. What WordPress shipped that Føhn cannot reach

WordPress 6.9 (December 2025) introduced the Abilities API and MCP support. WordPress 7.0 (20 May 2026) merged the Abilities API into core and added the AI Client, DataViews, browser-side media processing and responsive block visibility controls.

### 2.1 Abilities API — `#[AsAbility]`

The flagship, and the one nobody else has ergonomics for.

```php
wp_register_ability('namespace/ability-name', [
    'label'               => …,
    'description'         => …,
    'input_schema'        => …,   // JSON Schema
    'output_schema'       => …,   // JSON Schema
    'permission_callback' => …,
    'execute_callback'    => …,
    'meta'                => ['mcp' => ['public' => true]],
]);
```

An ability is a named, typed, permission-checked unit of site functionality. With `WordPress/mcp-adapter` installed it becomes an MCP tool an AI agent can discover and invoke.

The Føhn fit is unusually good. An ability is a class with one job, a capability, and typed input and output — which is a discovery item with extra steps:

```php
#[AsAbility(
    name: 'theme/summarise-post',
    label: 'Summarise a post',
    capability: 'edit_posts',
    mcp: true,
)]
final readonly class SummarisePost implements AbilityInterface
{
    public function __invoke(SummariseInput $input): SummariseOutput { … }
}
```

Both schemas come from reflecting the promoted constructors of the input and output DTOs. Føhn already does exactly this reflection in `AttributeCodec`, and `src/Data/` already holds DTOs of that shape. Hand-written JSON Schema is the worst part of the native API and the part Føhn can delete.

This is the difference between "a theme with attributes" and "a theme an agent can operate".

### 2.2 AI Client — guardrails, not a wrapper

WordPress 7.0 ships `wp_ai_client_prompt()`, a fluent `PromptBuilder`, a Connectors screen for credentials, and a `prompt_ai` capability. It is already provider-agnostic, so wrapping it would be an abstraction over an abstraction.

What a framework should add instead, because a template that issues a paid, slow, non-deterministic call per request is a real failure mode:

- caching by prompt hash through the existing `CacheInterface`
- capability gating that defaults to `prompt_ai` rather than to nothing
- a deliberate answer to whether Twig may issue a prompt at all — the default should be no

Small config plus one service. Not a new API surface.

### 2.3 Block hooks — one argument on `#[AsBlock]`

`blockHooks` in `block.json` auto-inserts a block relative to another. `BlockJsonGenerator` does not emit it. In 7.0 the logic moved to the REST controller, which does not change the theme-side declaration.

### 2.4 Speculative loading — belongs to the page cache

7.0 moves the default from conservative to moderate **when caching is detected**, and makes it configurable through constants and environment variables. That is an interaction with `page_cache_spec.md`, not a separate feature: a page cache that ignores speculation rules will prefetch pages it then serves from a different path.

### 2.5 DataViews — check before automating the classic admin

7.0 ships DataViews for content management. Before building `#[AsAdminColumn]` on `manage_*_posts_columns`, confirm which screens a project actually uses. Automating a surface WordPress is replacing would be effort spent on a legacy path. This is why admin columns stay undecided.

### 2.6 Smaller, real

| Feature                             | Today                                                                   | Worth it                                                                                                        |
| ----------------------------------- | ----------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------- |
| HTML API (`WP_HTML_Tag_Processor`)  | No usage                                                                | A Twig filter for post-processing rendered block markup — safer than regex, complements `BlockMarkupExtension`. |
| Script modules beyond blocks        | `BlockAssets` already uses `wp_register_script_module` for view scripts | Theme-level modules and an import map from the Vite plugin.                                                     |
| Responsive block visibility (7.0)   | Not in `ThemeJsonGenerator`                                             | Expose through the generator.                                                                                   |
| Browser-side media processing (7.0) | —                                                                       | Core behaviour; nothing for a theme framework to add.                                                           |

## 3. Order

1. `#[AsPostMeta]` and the `studiometa/foehn-acf` split — unblocks everything that assumes ACF is optional.
2. `#[AsAbility]` — the differentiator, and it needs the DTO-to-schema reflection that `#[AsPostMeta]` will already have exercised.
3. `#[AsBlockBinding]` — completes the "typed meta, no ACF, core blocks" story.
4. `discovery:list` — an afternoon, and it makes the framework legible.
5. `#[AsRewriteRule]`, the testing package, Settings API pages.

## Sources

- [Abilities API and MCP](https://developer.wordpress.org/news/2026/02/from-abilities-to-ai-agents-introducing-the-wordpress-mcp-adapter/) · [WordPress/mcp-adapter](https://github.com/WordPress/mcp-adapter)
- [AI Client in WordPress 7.0](https://make.wordpress.org/core/2026/03/24/introducing-the-ai-client-in-wordpress-7-0/) · [WordPress/wp-ai-client](https://github.com/WordPress/wp-ai-client)
- [WordPress 7.0 Field Guide](https://make.wordpress.org/core/2026/05/14/wordpress-7-0-field-guide/)
- [roots/acorn](https://github.com/roots/acorn)
