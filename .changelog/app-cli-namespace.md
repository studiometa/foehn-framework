### Added

- `cliNamespace` in `FoehnConfig` (`'cli_namespace'` in the array config) sets the WP-CLI command the `#[AsCliCommand]` classes of the project go under. The default is `app`. A name with a space, an empty name or `foehn` throws an `InvalidArgumentException`. See [Command Namespace](https://studiometa.github.io/foehn-framework/guide/cli-commands#command-namespace)
- A package declares the WP-CLI namespace of its `#[AsCliCommand]` classes with `"extra": {"foehn": {"cli-namespace": "<name>"}}` in its `composer.json`. Without it, the namespace is the package name without its vendor: `acme/shop-tools` gives `wp shop-tools <name>`. A namespace that is not one word of lowercase letters, digits, `-` and `_`, or that is `foehn`, is reported in `wp foehn inspect problems` and the commands of the package are not registered. See [The namespace of a package](https://studiometa.github.io/foehn-framework/guide/cli-commands#the-namespace-of-a-package)

### Changed

- **Breaking:** a `#[AsCliCommand]` class of the project registers as `wp app <name>` instead of `wp foehn <name>`. There is no alias for the old name: change the cron jobs, deploy scripts and runbooks that call `wp foehn <name>` for a project command to call `wp app <name>`, or set `cliNamespace` to keep another name
- **Breaking:** the commands of `studiometa/foehn-acf` register as `wp foehn-acf make:acf-block`, `wp foehn-acf make:field-group` and `wp foehn-acf make:options-page` instead of `wp foehn <name>`. The commands of another package that registered under `wp foehn` go under the namespace of that package. Only the commands of Føhn stay under `wp foehn`. There is no alias for the old names: change the scripts that call them
- When two classes declare the same WP-CLI command, neither is registered, and each `wp` run prints a warning that names both classes. Before, the class registered last replaced the other
- `wp foehn inspect cli-commands` gives the full line that runs each command, such as `wp app import:products`, `wp foehn inspect` or `wp foehn-acf make:acf-block`, instead of `foehn <name>`
- The discovery cache version is `v2`: each command item records the namespace of its package. The first request after the update scans again, and `wp foehn discovery:generate` writes the new cache
