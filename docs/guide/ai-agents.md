# AI Agents

Føhn ships [Agent Skills](https://agentskills.io/): reference files that teach a coding agent the framework's attributes, conventions and commands. Each package carries its own skill next to its code, so the skill is versioned with the release it describes.

| Skill               | Package                         | Source                                                   |
| ------------------- | ------------------------------- | -------------------------------------------------------- |
| `foehn`             | `studiometa/foehn`              | `packages/foehn/skills/foehn/SKILL.md`                   |
| `foehn-acf`         | `studiometa/foehn-acf`          | `packages/acf/skills/foehn-acf/SKILL.md`                 |
| `foehn-vite-plugin` | `@studiometa/foehn-vite-plugin` | `packages/vite-plugin/skills/foehn-vite-plugin/SKILL.md` |

## Read the installed skills

Every Føhn package ships its skill in the package itself: `vendor/studiometa/foehn/skills/`, `vendor/studiometa/foehn-acf/skills/` and `node_modules/@studiometa/foehn-vite-plugin/skills/`. An agent that runs in the project can read them with WP-CLI, at the versions in `composer.lock` and `package-lock.json`, with nothing to install or pin:

```bash
wp foehn skill                           # list the skills: name, package, version, description
wp foehn skill foehn                     # print SKILL.md
wp foehn skill foehn --list-references   # list its reference files
wp foehn skill foehn --reference=blocks  # print one
```

The command lists every installed Composer package that has a `skills/<name>/SKILL.md`, so a third-party package that ships a skill is listed too. The Vite plugin skill is listed when `node_modules/@studiometa/foehn-vite-plugin/skills` exists; versions of the plugin up to 0.7.0 do not publish it. See [CLI Commands](./cli-commands.md#agent-skills) for the options.

When WordPress cannot boot, `wp foehn skill` cannot run. The agent then reads the same files: `vendor/studiometa/*/skills/*/SKILL.md` and `node_modules/@studiometa/foehn-vite-plugin/skills/*/SKILL.md`.

### Project instructions

The starter ships an `AGENTS.md` at the project root, and a `CLAUDE.md` that imports it for Claude Code. It tells the agent to:

1. read the skills with `wp foehn skill`, or from `vendor/` and `node_modules/` when WordPress cannot boot;
2. run `wp foehn inspect --format=json` to read the summary of what the project already declares, and fix what its `problems` section lists (`wp foehn inspect problems` gives the fix of each one), then `wp foehn inspect post-types templates views menus settings --format=json` to read in full the post types and their meta keys, templates and their controllers, Twig views and their context keys, menu locations and settings;
3. create classes with the `make:*` commands, first with `--dry-run --format=json`, which writes nothing and prints the file it would write;
4. run `wp foehn discovery:clear` after it adds a class or changes an attribute;
5. put sample content and menus in a seeder made with `make:seeder` and run with `wp foehn seed`, never only in the database. See [Seeders](/guide/seeders);
6. run `wp foehn errors` after it loads a page that fails, to read the last error with its file, Twig template, controller and view, and `wp foehn log` for the warnings of WordPress and plugins. See [The error log](/guide/troubleshooting#the-error-log) and [The debug log](/guide/troubleshooting#the-debug-log);
7. run `wp foehn trace <path>` when a page renders the wrong template, view or data, to read what the request ran: the templates tried, the controller, the views with their context providers and keys, and the blocks. See [Request Trace](/guide/request-trace).

Its **Project conventions** section says that sample content and menus go in seeders. The project fills in the rest: how editors edit post meta, and which checks to run. Keep it current, because the agent follows what it says.

A project made before this file existed can copy [`AGENTS.md` from the starter](https://github.com/studiometa/foehn-framework/blob/main/packages/starter/AGENTS.md).

## Install in the agent

Agents that do not run commands in the project, or that load skills only from their own skill directory, can install the skills with a CLI.

The [`skills` CLI](https://github.com/vercel-labs/skills) installs the skills from the GitHub repository for Claude Code, Cursor, Codex, Copilot and more than 40 other agents:

```bash
npx skills add studiometa/foehn-framework
```

The command asks which skills to install and for which agents. Pass the choices as options to skip the prompts:

```bash
npx skills add studiometa/foehn-framework -s foehn -s foehn-acf -a claude-code -y
```

| Option       | Effect                                     |
| ------------ | ------------------------------------------ |
| `-s <name>`  | Install this skill. Repeat for each skill. |
| `-a <agent>` | Install for this agent.                    |
| `-y`         | Skip the prompts.                          |

## Pin to your release

Without a ref, the CLI installs the skills from `main`. `main` can describe attributes or options that your project's release does not have yet. Pin the skills to the version of `studiometa/foehn` in your `composer.lock`:

```bash
composer show studiometa/foehn | grep versions
npx skills add studiometa/foehn-framework#0.6.2
```

Tags have no `v` prefix: use `#0.6.2`, not `#v0.6.2`. A tree URL works too:

```bash
npx skills add https://github.com/studiometa/foehn-framework/tree/0.6.2
```

The CLI records the ref in `skills-lock.json`. `npx skills update` keeps that ref. To move to a new release, after `composer update studiometa/foehn`, run `add` again with the new tag:

```bash
npx skills add studiometa/foehn-framework#0.7.0
```

Commit `skills-lock.json` with the rest of the project, so every developer and agent reads the same skills.

## Without skills

Agents that read documentation directly can use the files the documentation build emits:

- [`llms.txt`](https://studiometa.github.io/foehn-framework/llms.txt) — an index of every page;
- [`llms-full.txt`](https://studiometa.github.io/foehn-framework/llms-full.txt) — every page in one file.

These files follow the current documentation, not a release.
