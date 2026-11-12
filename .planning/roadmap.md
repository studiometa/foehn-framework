# Roadmap

Current and proposed Føhn work, in one place. Detail lives in the linked active specifications; status lives here.

## Active and proposed work

| #   | Evolution                      | Status        | Spec                                   | Est.  |
| --- | ------------------------------ | ------------- | -------------------------------------- | ----- |
| 11  | `#[AsAbility]` + AI guardrails | **Undecided** | [abilities_spec.md](abilities_spec.md) | 4–7 d |

## Shipped foundation

Items 1–10 and 12–22 are done. They include the static page cache, discovery introspection, post meta, the ACF package split, rewrite rules, settings pages, block bindings, the starter/demo split, object-storage integration, unified cache invalidation, the non-production indexing guard, the real-cron heartbeat, the Føhn admin cache controls, `wp foehn verify` with its `updates` and `production` profiles, and the coding-agent tooling (#21): `wp foehn inspect` with the controller, providers and context keys of each template, `make:*` commands that never prompt and take `--dry-run` and `--format=json`, `wp foehn skill` to read the skills of the installed packages, and the starter's `AGENTS.md`; and seeders (#22): `#[AsSeeder]` classes that `wp foehn seed` runs by order to write the sample content of a site, `make:seeder`, `Upsert::post()` for idempotent writes, a `seeders` section in `wp foehn inspect`, and the demo portfolio written by six seeders instead of `database/seed.php`. `wp foehn db:export` writes a light, anonymized dump of production to import locally: core rules, WooCommerce, Gravity Forms and log presets, fail-closed on every other table, project rules in `DatabaseExportConfig`.

Current behavior is documented in `docs/` and demonstrated in `packages/starter/` and `packages/demo/`. The [page-cache architecture](page_cache_spec.md) and [object-storage decision](uploads_object_storage_spec.md) remain as design references.

## Undecided

| Item                                      | Why it is not scheduled                                                                                                                                                                               |
| ----------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `#[AsAbility]` + AI guardrails            | The differentiating item and nobody else has it, but not yet approved. See [abilities_spec.md](abilities_spec.md).                                                                                    |
| `#[AsAdminColumn]` / `#[AsAdminFilter]`   | WordPress 7.0 ships DataViews for content management. Confirm the classic list tables are not on the way out before automating them.                                                                  |
| `#[AsBlockStyle]` / `#[AsBlockVariation]` | Cheap and uncontroversial. Unscheduled, not rejected.                                                                                                                                                 |
| `blockHooks` on `#[AsBlock]`              | One argument on an existing attribute; `BlockJsonGenerator` does not emit it today.                                                                                                                   |
| Twig `{% cache %}` fragment caching       | Complements the shipped page cache by covering logged-in users and partials. Add it only when project use proves that a second cache lifecycle is worth owning.                                       |
| HTML API Twig filter                      | `WP_HTML_Tag_Processor` for post-processing rendered markup. Safer than regex, complements `BlockMarkupExtension`.                                                                                    |
| Theme-level script modules and import map | Block view scripts already use `wp_register_script_module`; the theme-wide half is missing.                                                                                                           |
| Responsive block visibility (7.0)         | Expose through `ThemeJsonGenerator`.                                                                                                                                                                  |
| Speculative loading                       | WordPress 7.0 defaults to moderate when caching is detected. Verify that the shipped page cache is detected correctly before adding configuration.                                                    |
| Local dev MCP server                      | Deferred. `wp foehn inspect` covers the read side from WP-CLI, and its `ProjectInspector` service is the seam a local MCP adapter would wrap. Revisit when an agent needs what WP-CLI cannot give it. |

## Rejected, with reasons

Recorded so the questions are not reopened without new information.

| Rejected                                 | Because                                                                                                                                                                                                                                                                                                                                                                                             |
| ---------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| An ORM or DB layer                       | `PostQueryBuilder` and Timber models cover the WordPress data model. A second ORM over `wp_posts` fights the platform.                                                                                                                                                                                                                                                                              |
| A full router                            | Template hierarchy, `#[AsTemplateController]`, `#[AsRestRoute]`, and the shipped `#[AsRewriteRule]` cover it.                                                                                                                                                                                                                                                                                       |
| Service providers                        | Discovery is the provider mechanism. Two ways to register one thing is worse than one.                                                                                                                                                                                                                                                                                                              |
| Sessions, broadcasting, mail, validation | WordPress or a plugin owns each; REST already validates through `args` schemas.                                                                                                                                                                                                                                                                                                                     |
| A testing package, in any form           | `brain/monkey` (2.7.0, maintained) and `10up/wp_mock` already mock WordPress functions; `php-stubs/wordpress-stubs` covers static analysis. The Føhn-specific helpers stay in `packages/foehn/tests/`, private, where they already work — publishing sixty lines to serve people writing custom discoveries is a public API surface bought for a niche. Revisit if that niche turns out to be real. |
| Wrapping the WordPress AI Client         | `wp_ai_client_prompt()` is already provider-agnostic. Guardrails yes, a wrapper no. See [abilities_spec.md](abilities_spec.md) §4.                                                                                                                                                                                                                                                                  |
| An MCP server                            | `WordPress/mcp-adapter` is that, maintained by the WordPress project. Abilities are the seam.                                                                                                                                                                                                                                                                                                       |

## Rules that apply across all of it

Written once here because every spec depends on them.

- **No closures in attribute arguments.** Discovery items reach the cache through `var_export`, so a callback is a method name resolved when `apply()` runs. A closure works in development and fails only in production, where caching is on.
- **Register on the hook that owns the API**, through the discovery phase — never at boot.
- **A missing platform function is a skip, not a fatal**, with a notice when `FoehnConfig::debug` is on.
- **Unit tests over stubs are load-bearing but not sufficient.** The 2026-08-19 fatal error passed 1409 of them. Anything that registers with WordPress needs a line in `packages/starter/tests/smoke/`.
