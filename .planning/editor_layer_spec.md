# Editor Layer Spec: foehn

## 1. Overview

The block-first direction described in `foehn-modern-wordpress-proposal.md` is sound, but its phase ordering is inverted. Its Phase 1 is documentation and messaging — reposition native blocks as the default authoring model. That cannot be honoured yet, because native Foehn blocks are currently **renderable but not authorable**.

This document specifies the missing prerequisite: a generic, attribute-driven editor layer that makes every `#[AsBlock]` class usable in the block editor with no per-block JavaScript and **no build tooling of any kind**.

It should land before the proposal's Phase 1.

> The proposal and its `notes.md` currently live in the `studiometa/foehn` mirror. Since the monorepo is where work happens and the mirrors are synced on release, they should probably move here alongside this document.

## 2. The gap

A Foehn block declares an attribute schema and renders it server-side:

- `#[AsBlock]` carries `name`, `title`, `category`, `icon`, `supports`, `parent`, `ancestor`, `interactivity`, `template`, `editorScript`, `editorStyle`, `style`, `viewScript`
- `BlockInterface::attributes()` returns the WordPress attribute schema — `BlockStub` ships a `title` string
- `compose()` maps attributes into Twig template data
- `packages/foehn/src/Discovery/BlockDiscovery.php` registers the block with a `render_callback` via `register_block_type()`
- `packages/foehn/src/Blocks/BlockJsonGenerator.php` emits `apiVersion: 3` metadata

What does not exist anywhere in the monorepo:

- no `registerBlockType` call
- no reference to `@wordpress/blocks`, `@wordpress/block-editor` or `useBlockProps`
- no `edit` component, generic or otherwise
- no PHP→JS data channel — there is no `wp_localize_script` or `wp_add_inline_script` call in the codebase
- `packages/starter/package.json` has no `@wordpress/*` dependency at all

The consequence: an editor can insert a Foehn block and it renders through its Twig template, but there is **no interface to set its attributes**. The `title` attribute in `BlockStub` has no control.

This is why the proposal's §1 cannot yet be acted on, and why §2 (InnerBlocks) and §3 (block locking) are implementation work rather than documentation work — a search for `innerBlocks`, `templateLock` and `allowedBlocks` across `packages/` returns nothing.

## 3. One item the proposal can drop

The proposal's hierarchy leads with "native block + `block.json` + server-side rendering", and `notes.md` records dynamic rendering as a recommendation to adopt. It is already an enforced invariant, not an aspiration:

- `AsBlock` has no `save` parameter
- `BlockJsonGenerator` never emits `save`
- `BlockDiscovery` registers exclusively through `render_callback`

There is no static-block path to close off. This is worth stating in the docs as a guarantee — it is the property that lets a template be rewritten in year four without invalidating existing content — but it requires no work.

## 4. Design

### 4.1 Principle

The editor UI is derived from data Foehn already discovers. Adding a block means writing a PHP class and a Twig template, exactly as today. No JavaScript is written per block, and nothing is compiled.

### 4.2 No build tooling

Everything the editor layer needs is exposed by WordPress as a global:

| Need                                              | Global                |
| ------------------------------------------------- | --------------------- |
| `registerBlockType`                               | `wp.blocks`           |
| `createElement`, `Fragment`                       | `wp.element`          |
| `InspectorControls`, `InnerBlocks`, `MediaUpload` | `wp.blockEditor`      |
| `PanelBody`, `TextControl`, `ToggleControl`, …    | `wp.components`       |
| `ServerSideRender`                                | `wp.serverSideRender` |

So the script uses `wp.element.createElement` rather than JSX, reads globals rather than importing modules, and targets plain ES2017 — which the WordPress admin supports. React is present at runtime because the block editor _is_ React, but it is never installed, never bundled, and never transpiled.

This also settles the build-tool question raised in the proposal's §4 and in `notes.md`. `@wordpress/build` (currently `0.21.0`, against `@wordpress/scripts` at `34.1.0`) exists to solve **dependency extraction** — scanning imports to produce the list of `wp-*` script handles. A single generic script has a fixed, known import set, so that list is a static array in PHP. There is nothing to extract, and no reason to add a second bundler.

**`@studiometa/foehn-vite-plugin` remains the front-end bundler only. The editor layer does not touch it.**

### 4.3 Deriving controls from the attribute schema

`attributes()` already returns WordPress attribute schemas. The control is derived from `type`, with an optional explicit override:

| Schema                                                        | Control           |
| ------------------------------------------------------------- | ----------------- |
| `'type' => 'string'`                                          | `TextControl`     |
| `'type' => 'boolean'`                                         | `ToggleControl`   |
| `'type' => 'number'`                                          | `NumberControl`   |
| `'type' => 'string', 'control' => 'textarea'`                 | `TextareaControl` |
| `'type' => 'string', 'control' => 'select', 'options' => […]` | `SelectControl`   |
| `'type' => 'number', 'control' => 'image'`                    | `MediaUpload`     |

No second schema, no new attribute class. A block declaring nothing beyond `type` gets a working control.

### 4.4 Preview via ServerSideRender

Because every Foehn block is dynamic, `wp.serverSideRender` renders it through its real `render_callback` and shows the **actual Twig output** in the editor. This is the single biggest reason the generic approach is good rather than merely acceptable: an accurate preview for zero per-block work.

Containers render `InnerBlocks` instead, using the `allowedBlocks` / `template` / `templateLock` parameters added in Phase 1.

### 4.5 Shape

```js
(function (wp) {
  const el = wp.element.createElement;
  const { registerBlockType } = wp.blocks;
  const { InspectorControls, InnerBlocks } = wp.blockEditor;
  const { PanelBody, TextControl, ToggleControl, TextareaControl } = wp.components;

  const CONTROLS = {
    string: TextControl,
    boolean: ToggleControl,
    textarea: TextareaControl,
  };

  function control(key, schema, attributes, setAttributes) {
    const Control = CONTROLS[schema.control || schema.type] || TextControl;

    return el(Control, {
      key,
      label: schema.label || key,
      value: attributes[key],
      checked: !!attributes[key],
      onChange: (value) => setAttributes({ [key]: value }),
    });
  }

  (window.foehnBlocks || []).forEach((def) => {
    registerBlockType(def.name, {
      edit({ attributes, setAttributes }) {
        const fields = Object.entries(def.attributes).map(([key, schema]) =>
          control(key, schema, attributes, setAttributes),
        );

        return el(
          wp.element.Fragment,
          null,
          el(InspectorControls, null, el(PanelBody, { title: "Settings" }, fields)),
          def.innerBlocks
            ? el(InnerBlocks, def.innerBlocks)
            : el(wp.serverSideRender, { block: def.name, attributes }),
        );
      },
    });
  });
})(window.wp);
```

## 5. Delivery

Two artifacts with different lifecycles. Keeping them separate is the most important implementation decision in this document.

### 5.1 The registrar — static, generated into the web root

The code above is identical for every project and changes only when Foehn changes. It lives in the package as a real file so oxlint and vitest can see it:

```
packages/foehn/resources/js/editor.js
```

`packages/installer/src/WebRootGenerator::generate()` already produces the web root on `POST_INSTALL_CMD` and `POST_UPDATE_CMD`, calling `generateIndexPhp()`, `generateWpConfig()`, `symlinkTheme()`, `symlinkMuPlugins()` and `generateMuPluginLoader()`. Adding one more call copies the packaged script into the document root:

```
web/wp-content/foehn/editor.js
```

That path sits inside the web root, outside the symlinked theme, and outside `web/wp/` which Composer owns. The URL is `content_url( 'foehn/editor.js' )`. It carries the same `DO NOT EDIT — regenerated on composer install` header as the other generated files, and since `web/` is gitignored it is never committed.

This is necessary because `vendor/studiometa/foehn/` sits outside the document root and cannot be served to a browser. Generating into the web root solves that the same way the installer already solves everything else, and gives the script a real URL — browser-cacheable, and debuggable in devtools with genuine line numbers. Version it with the Foehn package version as the `$ver` argument for cache busting.

### 5.2 The block definitions — dynamic, from discovery

Names, titles, attribute schemas and container configuration change whenever a developer adds or edits a block. They must not be tied to `composer install`, or adding a block would require reinstalling dependencies.

These are a **discovery artifact**, and the machinery already exists: `packages/foehn/src/Discovery/DiscoveryCache.php`, `DiscoveryRunner`, and the `discovery:generate` / `discovery:warm` / `discovery:clear` commands documented as running at deployment. `BlockDiscovery` should expose its discovered definitions as a serialisable array alongside registration, cached with everything else.

### 5.3 Runtime wiring

A single hook in `packages/foehn/src/Hooks/`:

```php
#[AsAction('enqueue_block_editor_assets')]
```

1. `wp_enqueue_script()` for `web/wp-content/foehn/editor.js` with the static dependency array — `wp-blocks`, `wp-element`, `wp-block-editor`, `wp-components`, `wp-server-side-render`
2. `wp_add_inline_script()` positioned `before` — `window.foehnBlocks = […]` from the discovery cache

### 5.4 The trap

It will be tempting to make the generated registrar smarter — emit only the control types the project's blocks actually use, so the payload is minimal. **Do not.** Control usage depends on block discovery, which is the dynamic half. The moment the generated script depends on project block data, adding a block requires a regeneration step and the two lifecycles have been collapsed back together.

The registrar stays fully generic. Minimising it is a non-goal.

## 6. The design rule this forces

The honest cost of a sidebar-driven generic edit is **prose**. In-canvas `RichText` editing cannot work through a server-rendered preview, so text is edited in the sidebar — acceptable for a label or a CTA, poor for anything paragraph-shaped.

The rule, which belongs in the docs alongside the proposal's §2:

> **Prose lives in `InnerBlocks` using core blocks. Structured data lives in sidebar controls.**

A hero block uses `InnerBlocks` for its heading and body, and sidebar controls for alignment, image and link target. This is the same direction §2 already argues for, so the constraint reinforces the plan rather than fighting it.

## 7. The one genuinely fiddly part

Attaching a client-side `edit` to a block already registered server-side requires `registerBlockType( name, settings )` to **merge with** the server's metadata rather than duplicate or clobber it. Everything else in this document is mechanical; this interaction is not, and it should be prototyped first against a single block before the control map is built.

## 8. Concrete changes

| File                                               | Change                                                                                      |
| -------------------------------------------------- | ------------------------------------------------------------------------------------------- |
| `packages/foehn/resources/js/editor.js`            | new — the generic registrar, no build step                                                  |
| `packages/installer/src/WebRootGenerator.php`      | add `generateEditorScript()` to `generate()`                                                |
| `packages/foehn/src/Discovery/BlockDiscovery.php`  | expose discovered definitions as a serialisable array                                       |
| `packages/foehn/src/Hooks/`                        | new `#[AsAction('enqueue_block_editor_assets')]` — enqueue script, inline definitions       |
| `packages/foehn/src/Attributes/AsBlock.php`        | Phase 1 — add `allowedBlocks`, `template` (InnerBlocks template), `templateLock`            |
| `packages/foehn/src/Blocks/BlockJsonGenerator.php` | Phase 1 — emit the container parameters                                                     |
| `packages/foehn/src/Console/Stubs/BlockStub.php`   | document that no editor JS is required                                                      |
| `packages/foehn/src/Assets/WebpackManifest.php`    | unrelated, but the manifest now comes from Vite — worth renaming before it misleads someone |

## 9. Effect on the existing proposal

| Phase       | Work                                                                                                                                                |
| ----------- | --------------------------------------------------------------------------------------------------------------------------------------------------- |
| **0** (new) | Registration handshake, definitions from discovery, control map, static dependency array                                                            |
| **1**       | `InnerBlocks` + locking as `AsBlock` parameters — the proposal's §2 and §3, reclassified from docs to implementation                                |
| **2**       | Documentation and starter repositioning — the proposal's original Phase 1, now honest                                                               |
| **3**       | Thin `#[AsSettingsPage]` on the core Settings API — §4, confirmed as a real gap: no `register_setting` or `add_options_page` anywhere outside ACF   |
| **4**       | Object storage for uploads as a framework flag — absent from the proposal; no `s3`, `upload_dir` or object-storage reference exists in the monorepo |
| **5**       | Optional ergonomics, ACF docs demotion                                                                                                              |

Two further adjustments to the proposal:

**Scope §4 down.** Its own risk note — that a settings abstraction could become too heavy — is correct. Keep `#[AsSettingsPage]` to registration and routing only, with no field abstraction: a plain Settings API page plus one `@wordpress/components` island, reusing the no-build approach in §4.2 above.

**Cut the backward-compatibility section.** "Keep all current ACF APIs", "avoid deprecating immediately", "gather feedback before introducing deprecations" — this protects an install base that does not exist. Keep `#[AsAcfBlock]` working because it is cheap; drop the staged-deprecation ceremony and the hedging in the docs.

## 10. Open questions

1. **`php: ^8.5`.** Defensible for new projects, but if Foehn is ever to host sites using WP Rocket, Rank Math or Wordfence, that constraint is cheaper to relax now than after the first client project ships on it.
2. **Interactivity API versus `@studiometa/js-toolkit`.** Both are live — `AsBlock` has an `interactivity` flag and there is an `InteractiveBlockStub`, while `packages/starter` ships js-toolkit with a `Counter.js`. There is no `@wordpress/interactivity` dependency, so that path presumably relies on core's bundled runtime. A rule is needed, or the first project will decide it by accident.
3. **`task_plan.md`.** It currently tracks the proposal's four phases as complete through Phase 3. If this spec is accepted, Phase 0 should be inserted there so `.planning/task_plan.md` remains the single source of current status, as CLAUDE.md directs.
