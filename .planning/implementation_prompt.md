# Implementation prompt — roadmap items 2 to 9

Hand this to the agent implementing the approved roadmap. It is written to be read cold, with no memory of the review that produced the specs.

---

## Mission

Implement items **2 through 9** of `.planning/roadmap.md`, one phase per pull request, in the order given there. Each phase ends merged into `main` with CI green. Stop and ask before deviating from a spec.

Read first, in this order: `.planning/roadmap.md`, then the spec each item links to. The roadmap's **"Rules that apply across all of it"** section is not advisory — every one of those rules was learned by breaking it.

## The loop, per phase

1. Branch from an up-to-date `main`: `feature/<short-name>` (or `fix/` when it is a fix).
2. Implement the phase as its spec describes.
3. Tests. Unit tests always; **plus an assertion in `packages/starter/tests/smoke/assertions.php` whenever the phase registers anything with WordPress.** See "Why the smoke test is not optional" below.
4. Documentation: a `docs/guide/` page or section, the matching `docs/api/` page, and a `CHANGELOG.md` entry under `[Unreleased]`.
5. Verify locally: `composer lint && composer analyse && composer test`, plus `npm run fmt:check`.
6. Commit in **atomic commits** — one logical change each, explicit paths staged, never `git add -A`.
7. Open a PR against `main` with a body that explains _why_, not just what.
8. Wait for CI. All eleven checks must pass, `codecov/patch` and `codecov/project` included.
9. Merge with `gh pr merge <n> --merge --delete-branch`, then start the next phase from the new `main`.

If CI fails, fix it on the branch and push again. Do not merge with a red or pending check. Do not disable a check or add a coverage threshold to make one pass.

## Order is fixed, and why

```
2 ─┬─→ 3
   ├─→ 5 ──→ 6
   └─→ 7, 8, 9   (any order)
4 ─┴─→ 9
```

Item 2 (`#[AsDiscovery]`) is a hard prerequisite: `DiscoveryRunner::getDiscoveryPhases()` is a hardcoded map of nineteen classes, so until it is gone nothing outside that file can add a discovery — which is what items 5 through 9 all need. Item 3 (`discovery:list`) is second because it costs a day and makes every later phase debuggable.

## Traps, all of which have already cost someone hours

**The starter's vendor copy is a mirror, not a symlink.** `packages/starter/vendor/studiometa/foehn/` is a Composer path-repository _copy_. Editing `packages/foehn/src/` does not change it, so the ddev site keeps running old code and produces failures that look like framework bugs. Before any local smoke run:

```bash
rsync -a --delete packages/foehn/src/       packages/starter/vendor/studiometa/foehn/src/
rsync -a --delete packages/foehn/resources/ packages/starter/vendor/studiometa/foehn/resources/
cd packages/starter && ddev exec 'cd /var/www/html && wp foehn discovery:clear'
```

The cache clear matters: a warm discovery cache predates your new class, so a new attribute or command silently does not exist.

**Attribute arguments cannot hold closures.** Discovery items reach the cache through `var_export`. A closure works in development and fails only once caching is on — which is to say only in production. Every callback is a method name resolved when `apply()` runs.

**`isInstantiable()` is the wrong guard for "can be registered".** `Timber\Post` and `Timber\Term` declare protected constructors, so every post type and taxonomy model fails it. Use the existing `IsWpDiscovery::isConcrete()`, which tests abstract, trait and enum.

**`WpCli::confirm()` never returns `false`.** WP-CLI ends the process when the answer is no. Branching on its return value is dead code, and `codecov/patch` will fail the PR for it.

**`grep -q` inside a pipeline under `set -o pipefail` fails the pipeline it just matched.** It closes the pipe on first match, the writer takes SIGPIPE. Capture output into a variable and match with `case` instead. This produced a CI failure whose own error message contained the proof it had succeeded.

**`wp cli cmd-dump` does not boot WordPress**, so it cannot see Føhn's commands. To check a command is registered, run it.

**`git add -A` sweeps up unrelated work.** It has already committed someone's in-progress planning documents into an unrelated commit. Stage explicit paths.

## Why the smoke test is not optional

On 2026-08-19 this repository had 1409 passing unit tests and every front-end page of the starter returned a fatal error. The tests run against a file of WordPress function stubs, so a discovery that registers _nothing at all_ passes them.

`packages/starter/tests/smoke/run.sh` drives a real request against a real WordPress in ddev and then asserts inside it. CI runs it twice per PR — cold cache and warm. If your phase makes something appear in WordPress, prove it there. An assertion that cannot fail is worse than none: check yours fails when the feature is removed.

## Commands

```bash
composer test              # foehn + starter + installer
composer test:foehn        # or test:starter / test:installer
composer lint              # mago lint + fmt --check
composer fix               # mago lint --fix + fmt
composer analyse           # mago analyse
npm run fmt                # markdown
npm run -w @studiometa/foehn-vite-plugin test

cd packages/starter && ./tests/smoke/run.sh     # needs ddev started and the rsync above
```

Pest runs from the monorepo root with `--test-directory`; the Composer scripts already do this. Running `pest` inside a package fails with a misleading error.

Commit messages: English, imperative subject, a body explaining why, and the trailer `Co-authored-by: Claude <claude@anthropic.com>`. A pre-commit hook runs mago and oxfmt on staged files.

## Guardrails

- **Do not touch the page cache work.** `feature/static-page-cache`, the locked worktree under `.claude/worktrees/`, and `page_cache_spec.md` belong to someone else.
- **Do not edit other `.planning/` documents** except to tick an item's status in `roadmap.md` as you land it.
- **Do not tag or release.** `0.5.0` is deliberately held until the page cache lands.
- **Do not implement item 10** (`#[AsAbility]`). It is undecided.
- **Do not add anything to `packages/foehn/src/Acf/`** — it is moving out in item 5.
- **Do not revisit anything in the roadmap's "Rejected" table** without new evidence. A testing package, an ORM, a router and a wrapper around the WordPress AI Client have each already been considered and declined, with reasons.

## Reporting

After each merged phase, report in a few lines: what shipped, the test count delta, anything found that the spec got wrong, and anything you chose not to do. If a spec turns out to be mistaken — and two of them already were, on the settings page scope and on a testing package that duplicated `brain/monkey` — say so and propose the correction rather than implementing something you believe is wrong.

Be accurate about state. "Tests pass" means you ran them. If a check is pending, say pending.
