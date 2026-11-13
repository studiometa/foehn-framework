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

Agents read `AGENTS.md` at the start of every task, but they load a skill only when they decide that it applies, and they often do not. So the few rules that matter most, and an index of where to read the rest, go in `AGENTS.md`.

`wp foehn agents-md` writes them in a block between `<!-- foehn:begin -->` and `<!-- foehn:end -->`, and changes nothing outside the markers. The block tells the agent to:

1. trust the installed skills over what it remembers, and read the skill and the reference of an area before it writes code in it;
2. run `wp foehn inspect --format=json` to read the summary of what the project already declares, then the sections it needs in full, such as `wp foehn inspect post-types templates views --format=json`;
3. create classes with the `make:*` commands, first with `--dry-run --format=json`, which writes nothing and prints the file it would write;
4. run `wp foehn discovery:clear` after it adds a class or changes an attribute, then check that `problems` is empty;
5. put sample content and menus in a seeder made with `make:seeder` and run with `wp foehn seed`, never only in the database. See [Seeders](/guide/seeders);
6. read `wp foehn errors`, `wp foehn errors --browser` and `wp foehn log` when a page fails. See [The error log](/guide/troubleshooting#the-error-log), [Browser errors](/guide/troubleshooting#browser-errors) and [The debug log](/guide/troubleshooting#the-debug-log);
7. run `wp foehn trace <path>` when a page renders the wrong template, view or data. See [Request Trace](/guide/request-trace).

Then it lists every installed skill and each of its reference files on one line, with the command that prints it, such as `wp foehn skill foehn --reference=blocks`, and the first sentence of the file. The block stays under 60 lines with the Føhn packages. See [AGENTS.md Block](./cli-commands.md#agents-md-block) for what the command does with each state of the file.

The starter ships an `AGENTS.md` with the block already written, and a `CLAUDE.md` that imports it for Claude Code. Outside the block, its **Project conventions** section says that sample content and menus go in seeders. The project fills in the rest: how editors edit post meta, and which checks to run. Keep it current, because the agent follows what it says.

#### Refresh the block

Run the command again after `composer update`, `npm update` or the install of another package that ships a skill, such as `studiometa/foehn-acf`, and commit the change:

```bash
wp foehn agents-md --dry-run   # print the block and say what would change
wp foehn agents-md
```

When the block is current, the command writes nothing. Composer does not run it for you: the command needs WordPress, which Composer does not boot, and a deploy that runs `composer install` would change a committed file on the server.

A project made before the block existed runs the same command. It adds the block at the end of the project's `AGENTS.md`, or creates the file.

An agent can also run a command on the wrong site, for example in a shell on a production server. So `wp foehn seed`, the `make:*` commands, `salts:generate` and `agents-md` refuse to run when `wp_get_environment_type()` is `production`, unless `--force` or `--dry-run` is given. See [Production Environment](/guide/cli-commands#production-environment).

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
npx skills add studiometa/foehn-framework#0.8.1
```

Replace `0.8.1` with your version. Tags have no `v` prefix: use `#0.8.1`, not `#v0.8.1`. A tree URL works too:

```bash
npx skills add https://github.com/studiometa/foehn-framework/tree/0.8.1
```

The CLI records the ref in `skills-lock.json`. `npx skills update` keeps that ref. To move to a new release, after `composer update studiometa/foehn`, run `add` again with the new tag:

```bash
npx skills add studiometa/foehn-framework#<new version>
```

Commit `skills-lock.json` with the rest of the project, so every developer and agent reads the same skills.

## Without skills

Agents that read documentation directly can use the files the documentation build emits:

- [`llms.txt`](https://studiometa.github.io/foehn-framework/llms.txt) — an index of every page;
- [`llms-full.txt`](https://studiometa.github.io/foehn-framework/llms-full.txt) — every page in one file.

These files follow the current documentation, not a release.
