### Added

- `#[AsCliCommand(writes: true)]` marks a command that changes content or files. When `wp_get_environment_type()` is `production`, such a command does not run unless `--force` or `--dry-run` is given, and the error says why and how. A project command that sets it must declare `[--force]` in its `longDescription`. See [Production Environment](https://studiometa.github.io/foehn-framework/guide/cli-commands#production-environment)
- `wp foehn salts:generate --dry-run` names the file that would get the keys and the keys it would replace, and writes nothing

### Changed

- `wp foehn seed`, every `make:*` command (the `foehn-acf` ones included) and `wp foehn salts:generate` refuse to run in production without `--force` or `--dry-run`. A deploy script or a production task that runs one of them must add `--force`. `seed` takes a new `--force` option. On `make:*` and `salts:generate`, `--force` still also overwrites a file or replaces keys
