# `wp foehn verify`

One WP-CLI command with two explicit profiles for two different release gates:

```bash
wp foehn verify --profile=updates --output=build/foehn-verification.json
wp foehn verify --profile=production
```

The `updates` profile validates WordPress core and plugin updates in CI. The `production` profile runs in the deployment script and rejects unsafe production configuration.

This is a closed, built-in verification feature. It does not add project health-check discovery, browser testing, manifests, or a general testing framework.

## 1. Command interface

```text
wp foehn verify --profile=updates|production [--output=<path>] [--format=table|json]
```

| Option                          | Rule                                                                                                                                        |
| ------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------- |
| `--profile=updates\|production` | Required. A required profile prevents a release gate from silently running the wrong subset.                                                |
| `--output=<path>`               | Required for `updates`; optional for `production`. Writes the versioned JSON report atomically. Relative paths resolve from `ABSPATH`.      |
| `--format=table\|json`          | Controls terminal output only. Default: `table`. JSON on stdout is for inspection; `--output` creates the stable CI or deployment artifact. |

Do not add independent `--diagnostics`, `--doctor`, or check-selection flags. The two profiles define stable product use cases rather than a collection of optional checks.

Exit status:

- `0`: the selected profile passes;
- `1`: one or more actionable update diagnostics or failed production checks;
- `2`: invalid arguments, report write failure, or verification infrastructure failure.

A WordPress boot failure occurs before the command exists. The surrounding CI or deployment script must classify a missing command or failed boot as an infrastructure failure.

## 2. Shared result model

Both profiles use one deterministic report schema, renderer, atomic writer, and exit-code policy:

```json
{
  "schema": 1,
  "profile": "production",
  "status": "fail",
  "summary": {
    "passed": 6,
    "failed": 1,
    "ignored": 0
  },
  "checks": []
}
```

Each check contains:

```json
{
  "name": "wp-debug",
  "status": "fail",
  "summary": "WP_DEBUG is enabled in production.",
  "details": {}
}
```

Check order and JSON key order are stable. Reports do not contain timestamps, absolute paths, stack traces, environment URLs, salts, or other secrets. A cron heartbeat can be reported as an age or freshness state, not as an unstable report timestamp.

The implementation can use an internal check interface. It does not expose `#[AsHealthCheck]`, custom project checks, groups, retries, dependencies, or an HTTP endpoint.

## 3. Updates profile

### Purpose

The updates profile gives CI one stable way to fail on PHP and WordPress compatibility notices after a WordPress core or plugin update:

```bash
wp foehn verify --profile=updates --output=build/foehn-verification.json
```

It does not replace Pest, the smoke suite, HTTP checks, browser tests, or PHP log collection. It observes one WP-CLI process only.

### Collected diagnostics

A `DiagnosticsCollector` records these events from the point at which Føhn starts it:

- PHP errors handled by `set_error_handler()`;
- `deprecated_function_run`;
- `deprecated_hook_run`;
- `doing_it_wrong_run`.

Each unique item contains:

```json
{
  "type": "deprecated_function",
  "symbol": "old_function",
  "message": "Use new_function() instead.",
  "version": "7.0.0",
  "file": "wp-content/plugins/example/plugin.php",
  "line": 42,
  "count": 1
}
```

Paths are relative to `ABSPATH` when possible. Items are deduplicated by type, symbol, message, file, and line. Output order is stable.

The PHP error handler must call the previous handler and preserve normal PHP behavior. Collection must not suppress, convert, or duplicate an error.

Diagnostics whose source is inside the WP-CLI Phar are ignored for the exit status but remain in the report. No other default ignore rule ships in v1. A project allowlist remains out of scope until real update runs establish a stable need and fingerprint format.

### Collector lifecycle and limits

The collector starts during `Kernel::bootstrap()`, immediately after configuration and before Timber initialization or lifecycle hook registration, when:

- `WP_CLI` is defined and true;
- the collector has not already started.

It records diagnostics from Føhn initialization, discovery application, WordPress lifecycle hooks reached by the command, and the command itself.

It cannot record:

- errors raised while WordPress core or plugins load before the theme starts Føhn;
- diagnostics from another process;
- diagnostics from HTTP, REST, cron, queue, or editor requests;
- a fatal error that prevents the theme or command from loading.

CI must still detect boot failure and collect PHP-FPM and WordPress logs for other processes.

### Updates result

The profile emits one `runtime-diagnostics` check. Its details contain `diagnostics` and `ignored` arrays. Any actionable item fails the check and exits with status `1`. The report is still written when diagnostics exist.

## 4. Production profile

### Purpose

The deployment script runs:

```bash
wp foehn verify --profile=production
```

This profile asserts that the booted site is configured as a safe production installation. It does not adapt its expectations to the current environment: if `WP_ENVIRONMENT_TYPE` is not `production`, it fails.

### Initial checks

| Check              | Pass condition                                                                                                                                                          |
| ------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Environment        | Resolved `WP_ENVIRONMENT_TYPE` is exactly `production`.                                                                                                                 |
| Debug              | `WP_DEBUG` and `WP_DEBUG_DISPLAY` are false.                                                                                                                            |
| Indexing           | WordPress indexing is enabled and Føhn's non-production indexing protection is inactive.                                                                                |
| Salts              | All eight WordPress keys and salts exist, are non-empty, are unique, and do not match generated-project placeholders. The report never includes their values.           |
| Real cron          | `DISABLE_WP_CRON` and the real-cron configuration are enabled.                                                                                                          |
| Cron heartbeat     | `foehn_cron_last_run` exists, is numeric, and is recent enough for the configured cadence plus documented scheduling jitter.                                            |
| Cron backlog       | WordPress does not report events overdue beyond the accepted threshold.                                                                                                 |
| Page-cache storage | When page caching is enabled for production, its cache root resolves inside the configured root and is writable. Stale files remain clearable when caching is disabled. |

The first version contains only checks backed by stable WordPress, installer, Docker runtime, or Føhn state. Do not add shallow checks for every PHP or WordPress setting.

### Indexing limit

The indexing check can inspect WordPress and Føhn state. It cannot prove that a CDN or web server does not add `X-Robots-Tag`. A deployment that needs this guarantee must inspect the public HTTP response separately.

### Cron limit

The heartbeat proves that the configured runner completed recently. It does not prove that every scheduled callback succeeded internally. Action Scheduler monitoring and hosting alerts remain separate concerns.

Scale-to-zero deployments must arrange an external scheduler. The same heartbeat contract applies.

## 5. Files

The expected module shape is:

```text
packages/foehn/src/Verification/VerificationProfile.php
packages/foehn/src/Verification/VerificationStatus.php
packages/foehn/src/Verification/VerificationResult.php
packages/foehn/src/Verification/VerificationReport.php
packages/foehn/src/Verification/VerifyCommand.php
packages/foehn/src/Verification/Updates/DiagnosticsCollector.php
packages/foehn/src/Verification/Updates/RuntimeDiagnosticsCheck.php
packages/foehn/src/Verification/Production/EnvironmentCheck.php
packages/foehn/src/Verification/Production/DebugCheck.php
packages/foehn/src/Verification/Production/IndexingCheck.php
packages/foehn/src/Verification/Production/SaltsCheck.php
packages/foehn/src/Verification/Production/CronCheck.php
packages/foehn/src/Verification/Production/PageCacheCheck.php
```

Nearby production assertions can share one check class when they use one source of truth. The final class split follows testable responsibilities rather than this list mechanically.

The collector is a container singleton. `VerifyCommand` validates arguments, selects one fixed profile, runs it, writes the report, renders the summary, and selects the exit status.

## 6. Tests

Shared unit tests cover:

- required and invalid profiles;
- deterministic check and JSON ordering;
- atomic report writes;
- table and JSON rendering;
- exit statuses `0`, `1`, and `2`;
- report redaction and relative paths.

Updates-profile tests cover:

- all four diagnostic sources;
- delegation to the previous PHP error handler;
- duplicate counting;
- relative path normalization;
- WP-CLI Phar classification;
- a report written on pass and failure;
- cold discovery and restored discovery cache;
- an isolated `_doing_it_wrong()` fixture that makes verification fail.

Production-profile tests cover:

- wrong environment;
- enabled debug or debug display;
- disabled WordPress indexing;
- missing, repeated, or placeholder salts without exposing values;
- disabled pseudo-cron without a real runner;
- missing, invalid, fresh, and stale heartbeat values;
- overdue cron events;
- disabled page cache with stale files;
- enabled page cache with unusable storage;
- a valid generated production configuration.

The real WordPress smoke suite runs both profiles. Docker end-to-end tests prove that real cron records the heartbeat consumed by production verification.

## 7. Delivery

1. Add the shared result, report, writer, renderer, and `VerifyCommand`.
2. Move the diagnostics collector design into the `updates` profile.
3. Add the production checks that only depend on current generated configuration.
4. Add indexing-protection and page-cache checks with the operational features.
5. Add cron heartbeat verification after the Docker runner records it.
6. Add CI and deployment script examples.

A partially implemented production profile must not pass by silently omitting planned checks. Add the profile only when all initial checks in this specification are present.

## 8. Out of scope

- `wp foehn diagnostics` and `wp foehn doctor` aliases;
- `#[AsHealthCheck]` or project-defined checks;
- browser, HTTP, REST, queue, and editor diagnostics;
- cross-process diagnostic storage;
- a registration manifest;
- project diagnostic allowlists in v1;
- automatic fixes or rollback;
- a claim of full WordPress or plugin compatibility;
- CDN, reverse-proxy, SPF, DKIM, DMARC, or other infrastructure validation.

## 9. Acceptance

The feature is complete when:

- CI can run the `updates` profile and receive a deterministic empty actionable report for clean cold and restored discovery-cache states;
- an injected WordPress deprecation makes the `updates` profile write a useful report and exit `1`;
- a generated safe production installation passes the `production` profile;
- each unsafe environment, debug, indexing, salts, cron, or cache fixture fails with a specific result and no secret disclosure;
- the Docker cron heartbeat is accepted when fresh and rejected when missing or stale;
- boot failure remains an infrastructure failure owned by the surrounding script;
- no second diagnostics or doctor command and no public health-check API is added.
