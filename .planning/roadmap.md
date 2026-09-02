# Roadmap

Current and proposed Føhn work, in one place. Detail lives in the linked active specifications; status lives here.

Latest release: `0.5.10`.

## Active and proposed work

| #   | Evolution                      | Status        | Spec                                                          | Est.  |
| --- | ------------------------------ | ------------- | ------------------------------------------------------------- | ----- |
| 11  | `#[AsAbility]` + AI guardrails | **Undecided** | [abilities_spec.md](abilities_spec.md)                        | 4–7 d |
| 15  | Unified cache invalidation     | **Done**      | [operations_spec.md](operations_spec.md) §3                   | 2–3 d |
| 16  | Production verification        | **Approved**  | [diagnostics-command-spec.md](diagnostics-command-spec.md) §4 | 2–3 d |
| 17  | Non-production indexing guard  | **Done**      | [operations_spec.md](operations_spec.md) §4                   | 1–2 d |
| 18  | Real WP-Cron heartbeat         | **Done**      | [operations_spec.md](operations_spec.md) §5                   | 1–2 d |
| 19  | Føhn admin cache controls      | **Approved**  | [operations_spec.md](operations_spec.md) §§6–8                | 2–3 d |
| 20  | Update verification            | **Done**      | [diagnostics-command-spec.md](diagnostics-command-spec.md) §3 | 2–3 d |

## Shipped foundation

Items 1–10 and 12–14 are done. They include the static page cache, discovery introspection, post meta, the ACF package split, rewrite rules, settings pages, block bindings, the starter/demo split, object-storage integration, and releases from `0.5.0` through `0.5.10`.

Current behavior is documented in `docs/` and demonstrated in `packages/starter/` and `packages/demo/`. The [page-cache architecture](page_cache_spec.md) and [object-storage decision](uploads_object_storage_spec.md) remain as design references.

## Delivery order

```text
15 ──→ 19
17 ─┬→ 16
18 ─┘
15 ───→ 16
20      (independent after the shared verification report exists)
```

**15 precedes 19** because the dashboard, admin bar and WP-CLI must call one invalidation service instead of implementing three deletion paths.

**17 and 18 precede the complete production profile in 16** because indexing activation and the cron heartbeat are the state that deployment verification reads. The shared verification result and command shell can land earlier, but `--profile=production` must not pass while planned checks are absent.

**20 shares the command and report with 16 but not its policy.** `wp foehn verify --profile=updates` is a CI update gate that reports process-local PHP and WordPress diagnostics. `--profile=production` is a deployment gate that rejects unsafe production configuration. There is no separate `diagnostics`, `doctor`, or public health-check command.

## Undecided

| Item                                      | Why it is not scheduled                                                                                                                                         |
| ----------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `#[AsAbility]` + AI guardrails            | The differentiating item and nobody else has it, but not yet approved. See [abilities_spec.md](abilities_spec.md).                                              |
| `#[AsAdminColumn]` / `#[AsAdminFilter]`   | WordPress 7.0 ships DataViews for content management. Confirm the classic list tables are not on the way out before automating them.                            |
| `#[AsBlockStyle]` / `#[AsBlockVariation]` | Cheap and uncontroversial. Unscheduled, not rejected.                                                                                                           |
| `blockHooks` on `#[AsBlock]`              | One argument on an existing attribute; `BlockJsonGenerator` does not emit it today.                                                                             |
| Twig `{% cache %}` fragment caching       | Complements the shipped page cache by covering logged-in users and partials. Add it only when project use proves that a second cache lifecycle is worth owning. |
| HTML API Twig filter                      | `WP_HTML_Tag_Processor` for post-processing rendered markup. Safer than regex, complements `BlockMarkupExtension`.                                              |
| Theme-level script modules and import map | Block view scripts already use `wp_register_script_module`; the theme-wide half is missing.                                                                     |
| Responsive block visibility (7.0)         | Expose through `ThemeJsonGenerator`.                                                                                                                            |
| Speculative loading                       | WordPress 7.0 defaults to moderate when caching is detected. Verify that the shipped page cache is detected correctly before adding configuration.              |

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
