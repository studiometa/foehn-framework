### Added

- `wp foehn agents-md` writes the Føhn block in the project's `AGENTS.md`, between `<!-- foehn:begin -->` and `<!-- foehn:end -->`: the first commands an agent runs (inspect, `make:*` with `--dry-run`, seeders, `errors`, `log`, `trace`) and one line for each installed skill and reference file, with the command that prints it. It changes nothing outside the markers, adds the block at the end of a file without them, creates a missing file, and writes nothing when the block is current. Run it after `composer update` or `npm update` and commit the change. Like the other commands that write, it refuses to run in production without `--force` or `--dry-run`. See [AGENTS.md Block](https://studiometa.github.io/foehn-framework/guide/cli-commands#agents-md-block)

### Changed

- The starter's `AGENTS.md` ships with the generated block. The block replaces the "Before you write code" and "When WordPress cannot boot" sections. A project made from an earlier starter runs `wp foehn agents-md`, which adds the block at the end of its `AGENTS.md`, and can then remove the steps the block repeats
