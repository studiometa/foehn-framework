### Added

- `cliNamespace` in `FoehnConfig` (`'cli_namespace'` in the array config) sets the WP-CLI command the `#[AsCliCommand]` classes of the project go under. The default is `app`. See [Command Namespace](https://studiometa.github.io/foehn-framework/guide/cli-commands#command-namespace)

### Changed

- **Breaking:** a `#[AsCliCommand]` class of the project registers as `wp app <name>` instead of `wp foehn <name>`. The commands of Føhn and of the other packages stay under `wp foehn`. There is no alias for the old name: change the cron jobs, deploy scripts and runbooks that call `wp foehn <name>` for a project command to call `wp app <name>`, or set `cliNamespace` to keep another name
- `wp foehn inspect cli-commands` gives the full line that runs each command, such as `wp app import:products` or `wp foehn inspect`, instead of `foehn <name>`
- The discovery cache version is `v2`: each command item records whether it comes from a package. The first request after the update scans again, and `wp foehn discovery:generate` writes the new cache
