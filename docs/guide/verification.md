# Verification

`wp foehn verify` runs one named release gate against the booted site, writes a deterministic JSON report, and exits with a status your CI job or deployment script can act on.

```bash
wp foehn verify --profile=updates --output=build/foehn-verification.json
```

Today there is one profile. `updates` is the gate you run after WordPress core or a plugin has been updated: it boots the site through WP-CLI and fails when that process raised a PHP or WordPress diagnostic somebody has to act on.

::: warning `--profile=production` does not exist yet
The deployment gate that rejects unsafe production configuration is specified but not implemented, so the name is refused with exit status `2` rather than accepted with half its checks. It arrives with roadmap item 16, once the indexing guard and the cron heartbeat it reads are in place. A gate that passed while some of its checks were missing would be worse than no gate.
:::

## Options

| Option                 | Rule                                                                                                                                                                    |
| ---------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `--profile=updates`    | Required. A missing or unknown name exits `2`. There is no default, because a gate that ran the wrong subset silently is the failure this option exists to prevent.     |
| `--output=<path>`      | Required for `updates`. Relative paths resolve from `ABSPATH`, not from the working directory. Written atomically, on a pass and on a failure alike.                    |
| `--format=table\|json` | Terminal output only. Default `table`. `--format=json` prints the same bytes the report file holds, so a line copied out of a log matches a later grep of the artifact. |

There is no flag to select or skip individual checks. The profile owns what it runs.

## Exit status

| Status | Meaning                                                                                         |
| ------ | ----------------------------------------------------------------------------------------------- |
| `0`    | The profile passed.                                                                             |
| `1`    | Actionable diagnostics, or a failed check. The site has something to look at.                   |
| `2`    | Invalid arguments, a report that could not be written, or verification that reached no verdict. |

`1` and `2` are deliberately different: `1` means "your update broke something", `2` means "this gate did not run". A CI job that treats them the same cannot tell a real regression from a misconfigured step.

## What the `updates` profile observes

From the moment your theme calls `Kernel::boot()`, Føhn records four sources for the rest of the process:

- PHP errors, through `set_error_handler()`;
- `deprecated_function_run`;
- `deprecated_hook_run`;
- `doing_it_wrong_run`.

That covers Føhn's own initialization, discovery, every WordPress lifecycle hook the command reaches, and the command itself.

Collection never changes behaviour. The error handler records and then calls whatever handler was installed before it, passing that handler's verdict through; with no previous handler it returns `false` so PHP's normal handling runs. Nothing is suppressed, converted or raised twice.

Findings are deduplicated by type, symbol, message, file and line, and carry a `count`. Diagnostics raised inside the WP-CLI Phar stay in the report under `ignored` and do not affect the exit status: WP-CLI's own vendored code raises deprecations on new PHP versions and no project can act on them. That is the only ignore rule that ships, and there is no project allowlist.

## What it cannot observe

Read this list before you treat a clean report as a compatibility claim. The profile watches **one WP-CLI process**, and it cannot record:

- anything raised before your theme starts Føhn — WordPress core loading, mu-plugins and plugins all run first;
- diagnostics from any other process: an HTTP, REST, cron, queue or block-editor request raises its own, and this command never sees them;
- a fatal error that stops the theme or the command from loading, which ends the process before there is a report to write.

So a passing run means "this WP-CLI process raised nothing actionable", not "the update is safe". Two things stay your CI job's responsibility:

- **A boot failure or a missing command is an infrastructure failure of its own.** WordPress that cannot boot fails before `wp foehn verify` exists, so a non-zero status from a step that never printed a report is not a clean gate — classify it yourself.
- **Collect PHP-FPM and WordPress logs anyway.** They are where the diagnostics from every other process end up, and they remain part of reviewing an update.

`wp foehn verify` does not replace Pest, the smoke suite, HTTP checks, or browser tests.

## The report

```json
{
  "schema": 1,
  "profile": "updates",
  "status": "fail",
  "summary": {
    "passed": 0,
    "failed": 1,
    "ignored": 0
  },
  "checks": [
    {
      "name": "runtime-diagnostics",
      "status": "fail",
      "summary": "1 actionable diagnostic in this process.",
      "details": {
        "diagnostics": [
          {
            "type": "deprecated_function",
            "symbol": "old_function",
            "message": "Use new_function() instead.",
            "version": "7.0.0",
            "file": "wp-content/plugins/example/plugin.php",
            "line": 42,
            "count": 1
          }
        ],
        "ignored": []
      }
    }
  ]
}
```

`schema` is `1`; bump-aware consumers should refuse a version they do not know. `status` is `fail` when any check failed, and `pass` otherwise — an `ignored` finding never fails a report.

The report is deterministic, and that is a requirement rather than a nicety: two runs of an unchanged site produce the same bytes, so a diff between two CI artifacts is a change in the site.

- Checks are sorted by name, and diagnostics are sorted over the fields their identity is built from.
- Key order comes from the report, not from the order a check happened to fill it.
- **No timestamps, absolute paths, stack traces, environment URLs, keys or salts.** Paths are relative to the install — `wp-content/plugins/example/plugin.php` — and a Phar is named without being located: `phar://wp/php/WP_CLI/Runner.php`. A file that can be placed nowhere is reported by name alone.

The hook-based sources carry no file or line of their own, so Føhn derives them from the backtrace: the first frame that is neither WordPress core nor the collector. That is the code you have to change, rather than the core function that reported it.

## In CI, after a WordPress update

```yaml
name: WordPress updates

on:
  schedule:
    - cron: "0 6 * * 1"
  workflow_dispatch:

jobs:
  updates:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Update WordPress core and plugins
        run: composer update roots/wordpress "wpackagist-plugin/*"

      - name: Boot the site
        run: ./bin/ci-install.sh

      # A step of its own, so a boot failure is not read as a clean gate. The command
      # cannot report on a WordPress that never started.
      - name: Verify the command exists
        run: wp foehn verify --help > /dev/null

      - name: Verify the update
        run: |
          mkdir -p build
          wp foehn verify --profile=updates --output=build/foehn-verification.json

      # `always()`, because the report on a failing run is the one worth keeping.
      - name: Keep the report
        if: always()
        uses: actions/upload-artifact@v4
        with:
          name: foehn-verification
          path: build/foehn-verification.json

      # Everything the command could not see: another process's diagnostics.
      - name: Keep the logs
        if: always()
        run: cat web/wp-content/debug.log php-fpm.log || true
```

Keep the passing reports too. The artifact from the last good run is what makes the first failing one readable: `git diff` between the two says exactly which finding is new.

## See also

- [CLI Commands](/guide/cli-commands) — the other built-in commands
- [Discovery Cache](/guide/discovery-cache) — the cold and warm states a verification run covers
