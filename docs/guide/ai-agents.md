# AI Agents

Føhn ships [Agent Skills](https://agentskills.io/): reference files that teach a coding agent the framework's attributes, conventions and commands. Each package carries its own skill next to its code, so the skill is versioned with the release it describes.

| Skill               | Package                         | Source                                                   |
| ------------------- | ------------------------------- | -------------------------------------------------------- |
| `foehn`             | `studiometa/foehn`              | `packages/foehn/skills/foehn/SKILL.md`                   |
| `foehn-acf`         | `studiometa/foehn-acf`          | `packages/acf/skills/foehn-acf/SKILL.md`                 |
| `foehn-vite-plugin` | `@studiometa/foehn-vite-plugin` | `packages/vite-plugin/skills/foehn-vite-plugin/SKILL.md` |

## Install

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
