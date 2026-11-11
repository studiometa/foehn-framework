### Added

- Every `make:*` command, the `foehn-acf` ones included, takes `--format=json`. It prints the files with their status (`created`, `overwritten`, `exists`, `would-create`, `would-overwrite`) and the next steps. With `--dry-run`, it also prints the contents that would be written. See [Scripts and Coding Agents](https://studiometa.github.io/foehn-framework/guide/cli-commands#scripts-and-coding-agents)
- `ClassFileGenerator::plan()` tells what `ClassFileGenerator::write()` would do, without touching the disk

### Changed

- **Breaking:** a `make:*` command exits with status 1 when the file already exists and `--force` is not given, also with `--dry-run`. Before, it printed an error and exited with status 0. A script that runs a generator twice must pass `--force` or expect the failure
- **Breaking:** `ClassFileGenerator::write()` returns a `GenerationStatus` instead of a `bool`
- **Breaking:** the `make:*` commands take a `Scaffolder` instead of a `ClassFileGenerator` in their constructor
- `make:*` commands print their next steps one line each

### Removed

- **Breaking:** `WpCli::prompt()`, `WpCli::previewGeneratedFile()` and `WpCli::reportFileExists()`. The `Scaffolder` service previews and reports generated files

### Fixed

- `make:block --interactive` names the view script Føhn loads, `assets/js/blocks/<name>.js`, instead of `assets/scripts/blocks/<name>/view.js`, which nothing loads
