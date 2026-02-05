# GitHub Issue Drafts

This directory contains draft issues for Foehn development, based on an analysis of real-world WordPress projects built with studiometa/wp-toolkit.

## Issues by Priority

### 🔴 High Priority

| #   | Title                                             | Description                                         |
| --- | ------------------------------------------------- | --------------------------------------------------- |
| 001 | [ACF Field Groups](./001-acf-field-groups.md)     | Add `#[AsAcfFieldGroup]` for non-block field groups |
| 002 | [ACF Options Pages](./002-acf-options-pages.md)   | Add `#[AsAcfOptionsPage]` for theme settings        |
| 008 | [Theme Conventions](./008-theme-conventions.md)   | Define standard directory structure and naming      |
| 009 | [CLI Commands](./009-cli-commands-enhancement.md) | Implement scaffolding commands                      |

### 🟡 Medium Priority

| #   | Title                                                          | Description                          |
| --- | -------------------------------------------------------------- | ------------------------------------ |
| 003 | [ACF Field Fragments](./003-acf-field-fragments.md)            | Document reusable field pattern      |
| 004 | [Menu Registration](./004-menu-registration.md)                | Add `#[AsMenu]` attribute            |
| 005 | [Image Sizes](./005-image-sizes.md)                            | Add `#[AsImageSize]` attribute       |
| 007 | [ContextProvider Analysis](./007-context-provider-analysis.md) | Document pros/cons vs timber/context |

### 🟢 Low Priority

| #   | Title                                                     | Description                             |
| --- | --------------------------------------------------------- | --------------------------------------- |
| 006 | [Cleanup/Security Audit](./006-cleanup-security-audit.md) | Verify existing hooks, add missing ones |

## Analysis Summary

### What Foehn does well ✅

- Eliminates massive boilerplate for CPTs and taxonomies
- Auto-discovery removes manual registration
- Timber classmap is automatic
- DI for services
- Clean `functions.php` (single line!)

### What's missing ❌

- ACF Field Groups for pages/post types (not blocks)
- ACF Options Pages
- No conventions for project structure
- Limited CLI scaffolding

### Decisions made

- **Keep** Native Blocks and Block Patterns (new project features)
- **Keep** FSE support as-is (optional, nice to have)
- **ContextProvider** (renamed from ViewComposer) is better architecture, needs migration docs

## Converting to GitHub Issues

```bash
# Using GitHub CLI
gh issue create --title "Add #[AsAcfFieldGroup] attribute" --body-file .github/ISSUE_DRAFTS/001-acf-field-groups.md --label "enhancement,acf,priority-high"
```

Or copy-paste content manually when creating issues on GitHub.
