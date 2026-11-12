### Added

- Every `make:*` command, the `foehn-acf` ones included, takes `--format=json`. It prints the files with their status (`created`, `overwritten`, `exists`, `would-create`, `would-overwrite`) and the next steps. With `--dry-run`, it also prints the contents that would be written. See [Scripts and Coding Agents](https://studiometa.github.io/foehn-framework/guide/cli-commands#scripts-and-coding-agents)
- `ClassFileGenerator::plan()` tells what `ClassFileGenerator::write()` would do, without touching the disk
- A `make:*` command puts its class next to the classes of the same kind the project declares: in their directory, in their namespace and under the parent class most of them extend. With none, it uses its default directory. See [Where a Generated Class Goes](https://studiometa.github.io/foehn-framework/guide/cli-commands#where-a-generated-class-goes)
- `make:post-type` takes `--meta=<key>[:<type>],…`, which declares each field with `#[AsPostMeta]` and writes a typed accessor for it, and `--rewrite-slug=<slug>`
- The `make:post-type` and `make:model --post-type` notes name the Twig files to create where the theme keeps its views of the same kind (`templates/pages/single-<name>.twig` in the starter), and the controller that renders each one

### Changed

- **Breaking:** a `make:*` command exits with status 1 when the file already exists and `--force` is not given, also with `--dry-run`. Before, it printed an error and exited with status 0. A script that runs a generator twice must pass `--force` or expect the failure
- **Breaking:** `ClassFileGenerator::write()` returns a `GenerationStatus` instead of a `bool`
- **Breaking:** the `make:*` commands take a `Scaffolder` instead of a `ClassFileGenerator` in their constructor. `make:post-type` and `make:model` also take `ProjectConventions`
- **Breaking:** `make:post-type` writes `app/Models/<Name>.php`, a class named after the post type (`Event`, not `EventPost`) that extends `Studiometa\Foehn\Models\Post`, instead of `app/PostTypes/<Name>Post.php` extending `Timber\Post`
- A generated class imports its parent class (`extends Post`, not `extends \Timber\Post`)
- `make:*` commands print their next steps one line each

### Removed

- **Breaking:** `WpCli::prompt()`, `WpCli::previewGeneratedFile()` and `WpCli::reportFileExists()`. The `Scaffolder` service previews and reports generated files

### Fixed

- `make:block --interactive` names the view script Føhn loads, `assets/js/blocks/<name>.js`, instead of `assets/scripts/blocks/<name>/view.js`, which nothing loads
- `make:menu` and `make:image-size` give Twig hints that are valid Twig: `menus['<location>'].items` and `post.thumbnail.src('<size>')`. A class call such as `PrimaryMenu::get()` is now named as PHP
