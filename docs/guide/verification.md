# Verification

`wp foehn verify` runs one named release gate against the booted site, writes a deterministic JSON report, and exits with a status your CI job or deployment script can act on.

```bash
wp foehn verify --profile=updates --output=build/foehn-verification.json
```

There are two profiles, for two different gates:

| Profile      | Run by                | Asks                                                                            |
| ------------ | --------------------- | ------------------------------------------------------------------------------- |
| `updates`    | CI                    | Did booting this site raise a PHP or WordPress diagnostic somebody must act on? |
| `production` | The deployment script | Is this booted site configured as a safe production installation?               |

The profile is required. There is no default, because a release gate that quietly ran the wrong subset is the failure this option exists to prevent.

## Options

| Option                 | Rule                                                                                                                                                                    |
| ---------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `--profile=<name>`     | Required, `updates` or `production`. A missing or unknown name exits `2`.                                                                                               |
| `--output=<path>`      | Required for `updates`, optional for `production`. Relative paths resolve from `ABSPATH`, not the working directory. Written atomically, on a pass and a failure alike. |
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

## What the `production` profile checks

```bash
wp foehn verify --profile=production
```

Eight checks, and the profile exists only because all eight do — a gate shipped with some of its checks missing would report a pass that means less than the name on it.

| Check                | Passes when                                                                                                                      |
| -------------------- | -------------------------------------------------------------------------------------------------------------------------------- |
| `environment`        | Resolved `WP_ENVIRONMENT_TYPE` is exactly `production`.                                                                          |
| `debug`              | `WP_DEBUG` and `WP_DEBUG_DISPLAY` are both off.                                                                                  |
| `indexing`           | WordPress indexing is enabled (`blog_public`) and Føhn's [non-production guard](./security#non-production-indexing) is inactive. |
| `salts`              | All eight keys and salts exist, are unique, are long enough, and are not generated placeholders.                                 |
| `real-cron`          | `DISABLE_WP_CRON` is on **and** a real cron runner is configured.                                                                |
| `cron-heartbeat`     | [`foehn_cron_last_run`](./docker-image#the-heartbeat) is a timestamp inside the window this cadence allows.                      |
| `cron-backlog`       | No scheduled event is overdue past that same window.                                                                             |
| `page-cache-storage` | If caching is on for production, its root resolves inside itself and is writable.                                                |

**It does not adapt to the environment it finds.** Run against staging, it fails at the first check, on purpose: a gate that relaxed its rules when the site said `staging` would wave through a production machine whose `WP_ENVIRONMENT_TYPE` was simply wrong — and that is the misconfiguration most worth catching, because the page cache and the indexing guard key off the same value.

`--output` is optional here. A deployment script wants a verdict, and its verdict is the exit status.

### The cron window

`cron-heartbeat` and `cron-backlog` are judged against one number: twice the configured cadence plus five minutes. `FOEHN_CRON_SCHEDULE` sets the cadence and defaults to `15min`, so the default window is 35 minutes.

Two intervals rather than one, because busybox's `run-parts` fires on a fixed period and a pass takes as long as its events take — a deploy landing between two ticks can legitimately see one interval of silence. One number for both checks, because if the runner has been passing on schedule then nothing can be overdue by longer than the window in which it should have run: a stale heartbeat and a backlog are two symptoms of one fault.

**Scale-to-zero deployments must arrange an external scheduler.** A stopped machine records no heartbeat, so this check fails — correctly, because its scheduled events genuinely are not running. See [the scheduler contract](./deployment-fly#scale-to-zero-and-the-scheduler-you-then-owe-the-site).

### What it cannot prove

- **That no CDN or web server adds an `X-Robots-Tag`.** `indexing` reads WordPress and Føhn. A deployment that needs the end-to-end guarantee has to inspect the public HTTP response.
- **That every scheduled callback succeeded.** The heartbeat says the runner completed a pass. A job that throws on every run leaves a fresh heartbeat behind it; Action Scheduler monitoring is a separate concern.
- **That the keys are secret.** `salts` checks that they exist, differ and are not placeholders. It cannot know whether one was committed to a repository.

### Secrets

The report never contains a key or salt — not a value, not a fragment, not even a length, because a length is a hint about a secret and the report is a file CI keeps. What it names is which of the eight constants had a problem, and those names are public.

For the same reason it carries no filesystem paths and no timestamps. The heartbeat is reported as a freshness state (`fresh`, `stale`, `missing`, `invalid`) and the window it was judged against, so two runs of an unchanged site produce the same bytes and a diff between two artifacts is a change in the site.

## In a deployment script

```bash
composer install --no-dev --optimize-autoloader
wp foehn cache:config --write
wp foehn cache:clear

# Refuse to finish the deploy on unsafe production configuration.
wp foehn verify --profile=production
```

Exit `1` means a check failed and the deploy should stop. Exit `2` means the gate could not run at all, which is not a passing site either. A WordPress that cannot boot fails before the command exists, so the script must treat a missing command as its own infrastructure failure.

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
