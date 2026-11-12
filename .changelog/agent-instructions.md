### Added

- `wp foehn skill` lists the Agent Skills of the installed packages, with their package and installed version, and prints one `SKILL.md` (`wp foehn skill foehn`) or one reference file (`--reference=<file>`, `--list-references`). It reads every Composer package with a `skills/<name>/SKILL.md`, third-party packages included, and `node_modules/@studiometa/foehn-vite-plugin/skills`. See [Agent Skills](https://studiometa.github.io/foehn-framework/guide/cli-commands#agent-skills)
- The starter ships an `AGENTS.md`, and a `CLAUDE.md` that imports it. It tells a coding agent to read the installed skills, run `wp foehn inspect` and preview `make:*` commands with `--dry-run --format=json` before it writes code. A project made from an earlier starter can copy it from `packages/starter/AGENTS.md`
- The `foehn` skill starts with the steps to follow before writing code: `wp foehn inspect`, then `make:*` with `--dry-run`, then `wp foehn discovery:clear`

### Changed

- `@studiometa/foehn-vite-plugin` publishes its `skills/` directory to npm, so its skill is in `node_modules/` after `npm install`
