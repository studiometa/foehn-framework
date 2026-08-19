# Roadmap

Every planned evolution of Føhn, in one place. Detail lives in the linked specs; status lives here.

`task_plan.md` is the record of how the framework was built and is finished. This is what comes next.

## Status at a glance

| #   | Evolution                      | Status        | Spec                                                                  | Est.   |
| --- | ------------------------------ | ------------- | --------------------------------------------------------------------- | ------ |
| 1   | Static page cache              | In flight     | [page_cache_spec.md](page_cache_spec.md)                              | 8–11 d |
| 2   | `#[AsDiscovery]`               | **Done**      | [post_meta_and_acf_split_spec.md](post_meta_and_acf_split_spec.md) §1 | 1–2 d  |
| 3   | `wp foehn discovery:list`      | **Done**      | [framework_additions_spec.md](framework_additions_spec.md) §1         | 1 d    |
| 4   | `#[AsPostMeta]`                | Approved      | [post_meta_and_acf_split_spec.md](post_meta_and_acf_split_spec.md) §2 | 2 d    |
| 5   | `studiometa/foehn-acf` split   | Approved      | [post_meta_and_acf_split_spec.md](post_meta_and_acf_split_spec.md) §3 | 2–3 d  |
| 6   | Starter off ACF                | Approved      | [post_meta_and_acf_split_spec.md](post_meta_and_acf_split_spec.md) §4 | 1 d    |
| 7   | `#[AsRewriteRule]`             | Approved      | [framework_additions_spec.md](framework_additions_spec.md) §3         | 2 d    |
| 8   | `#[AsSettingsPage]`            | Approved      | [framework_additions_spec.md](framework_additions_spec.md) §4         | 2–3 d  |
| 9   | `#[AsBlockBinding]`            | Approved      | [framework_additions_spec.md](framework_additions_spec.md) §2         | 1–2 d  |
| 10  | `#[AsAbility]` + AI guardrails | **Undecided** | [abilities_spec.md](abilities_spec.md)                                | 4–7 d  |
| 11  | Release `0.5.0`                | Blocked       | —                                                                     | —      |

Done and shipped: the block editor layer ([editor_layer_spec.md](editor_layer_spec.md)), and on 2026-08-19 the discovery rewrite onto `tempest/discovery`, `*.config.php` loading, the self-warming discovery cache, and generated WordPress security keys.

## Order, and why

```
2 ─┬─→ 3
   ├─→ 5 ──→ 6
   └─→ 7, 8, 9       (any order)
4 ─┴─→ 9
1 ────→ 11
```

**2 first, and it is not a preference.** `DiscoveryRunner::getDiscoveryPhases()` is a hardcoded map of nineteen classes, so nothing outside that file can add a discovery. Every attribute below needs it, the ACF package cannot exist without it, and `docs/guide/custom-discovery.md` documents a feature that has never worked because of it.

**3 second, because it costs a day and makes the rest debuggable.** Nothing can currently say what discovery found. On 2026-08-19 that turned a one-line bug into an hour.

**4 before 9**, so the block bindings guide can explain when _not_ to write a binding source: meta declared with `#[AsPostMeta]` is already bindable through core's `core/post-meta`.

**11 waits on 1**, by decision on 2026-08-19. Note the cost: everything merged that day is unreleased, and `packages/starter` requires `studiometa/foehn: ^0.4`, so `composer create-project` still installs `0.4.1` — the version whose front-end fatals and whose auth keys are `md5()` of the web root. No live project off the published starter until a tag exists. Cutting `0.5.0` now and shipping the page cache as `0.6.0` remains available; nothing technical couples them.

## Undecided

| Item                                      | Why it is not scheduled                                                                                                              |
| ----------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| `#[AsAbility]` + AI guardrails            | The differentiating item and nobody else has it, but not yet approved. See [abilities_spec.md](abilities_spec.md).                   |
| `#[AsAdminColumn]` / `#[AsAdminFilter]`   | WordPress 7.0 ships DataViews for content management. Confirm the classic list tables are not on the way out before automating them. |
| `#[AsBlockStyle]` / `#[AsBlockVariation]` | Cheap and uncontroversial. Unscheduled, not rejected.                                                                                |
| `blockHooks` on `#[AsBlock]`              | One argument on an existing attribute; `BlockJsonGenerator` does not emit it today.                                                  |
| Twig `{% cache %}` fragment caching       | Complements the page cache — it covers logged-in users and partials, which a page cache cannot. Decide with item 1.                  |
| HTML API Twig filter                      | `WP_HTML_Tag_Processor` for post-processing rendered markup. Safer than regex, complements `BlockMarkupExtension`.                   |
| Theme-level script modules and import map | Block view scripts already use `wp_register_script_module`; the theme-wide half is missing.                                          |
| Responsive block visibility (7.0)         | Expose through `ThemeJsonGenerator`.                                                                                                 |
| Speculative loading                       | 7.0 defaults to moderate when caching is detected. Belongs in item 1, not as its own feature.                                        |

## Rejected, with reasons

Recorded so the questions are not reopened without new information.

| Rejected                                 | Because                                                                                                                                                                                                                                                                                                                                                                                             |
| ---------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| An ORM or DB layer                       | `PostQueryBuilder` and Timber models cover the WordPress data model. A second ORM over `wp_posts` fights the platform.                                                                                                                                                                                                                                                                              |
| A full router                            | Template hierarchy, `#[AsTemplateController]` and `#[AsRestRoute]` cover it. Item 7 is the missing 10%.                                                                                                                                                                                                                                                                                             |
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
