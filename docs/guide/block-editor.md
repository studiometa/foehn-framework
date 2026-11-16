# Block Editor

Every `#[AsBlock]` class is authorable in the block editor with no JavaScript and no build step: write a PHP class and a Twig template, and Føhn derives the sidebar controls from them, and draws the block in the editor from its own Twig template, editable in place.

## The model

A Foehn block already declares an attribute schema in `attributes()` and renders itself server-side through `render()` and `compose()`. That schema is the only input the block editor needs.

Føhn ships one generic script — the registrar — that reads the schema of every discovered block and, for each one, registers an `edit` component built from the schema alone: a sidebar field per attribute, and a canvas drawn from the block's own `render_callback`. There is no per-block script to write, no `edit.js`, and nothing to compile.

```php
#[AsBlock(
    name: 'theme/alert',
    title: 'Alert',
    category: 'widgets',
    icon: 'warning',
)]
final readonly class AlertBlock implements BlockInterface
{
    public static function attributes(): array
    {
        return [
            'type' => ['type' => 'string', 'default' => 'info'],
            'message' => ['type' => 'string', 'default' => ''],
        ];
    }

    // compose() and render() as usual
}
```

This class alone is enough to get a working sidebar with two fields, `type` and `message`, and a canvas that shows the real Twig output. Nothing else needs to change.

## Core's default styles win on load order

WordPress prints the styles it derives from `theme.json` **inline, and after** the theme's enqueued stylesheet. Later and equal specificity means core wins.

With no `theme.json`, those are core's own defaults — including `body { padding: 0 }`, which silently flattens a gutter the theme's stylesheet sets. The symptom is a theme rule struck through in the inspector by an inline sheet the theme never enqueued, which reads as a broken stylesheet rather than a cascade conflict.

`Hooks\Cleanup\DisableGlobalStyles` removes them. The starter enables it, because it ships no `theme.json`; drop it from `foehn.config.php` if you adopt `theme.json` presets for colours, typography, spacing or duotone filters.

## Controls

The control for an attribute is derived from its `type`, with an optional `control` override for cases the type alone cannot express:

| Schema                                                        | Control    | Component                                |
| ------------------------------------------------------------- | ---------- | ---------------------------------------- |
| `'type' => 'string'`                                          | `text`     | `TextControl`                            |
| `'type' => 'string', 'options' => [...]` or `'enum' => [...]` | `select`   | `SelectControl`                          |
| `'type' => 'boolean'`                                         | `toggle`   | `ToggleControl`                          |
| `'type' => 'number'` or `'type' => 'integer'`                 | `number`   | `TextControl` (`type="number"`)          |
| `'type' => 'string', 'control' => 'textarea'`                 | `textarea` | `TextareaControl`                        |
| `'type' => 'string', 'control' => 'richtext'`                 | `richtext` | `RichText`, [on the canvas](#the-canvas) |
| `'type' => 'integer', 'control' => 'image'`                   | `image`    | `MediaUpload`                            |
| `'type' => 'array', 'control' => 'gallery'`                   | `gallery`  | `MediaUpload` (`multiple`)               |
| `'type' => 'integer', 'control' => 'file'`                    | `file`     | `MediaUpload` (any type)                 |
| `'type' => 'array', 'control' => 'posts'`                     | `posts`    | `ComboboxControl` + core-data            |

A string or number attribute gets a control from `type` alone; nothing else is required. `number` and `integer` share the same component, but the declared type still matters: an `integer` field rounds its value before calling `setAttributes`, because WordPress rejects a float against an `integer` schema and silently falls back to the default.

An attribute of a type with no derived control — `array`, `object`, or no `type` at all — gets no sidebar field. It still exists in the schema and reaches the template; it is simply not editable from the sidebar, which is the right outcome for data a block computes or receives from elsewhere rather than data an author sets.

`gallery`, `file` and `posts` are never derived from a type: an `array` says nothing about whether it holds attachment ids or post ids, so each has to be asked for by name.

`gallery` and `posts` store a list, so declare them as arrays — `['type' => 'array', 'items' => ['type' => 'integer'], 'default' => []]`. Declared as a scalar, WordPress validates every selection but the first away, and debug mode warns about it.

`posts` is the one control with no core equivalent. It stores post ids **in the order the author arranged them**, offers move-up and move-down, and searches across the post types `postTypes` names — or every viewable type when it names none.

Seven keys beyond the WordPress ones (`type`, `default`, `enum`, ...) describe how the attribute shows up in the sidebar:

| Key            | Description                                                                                                                                                                                                                                                                                         |
| -------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `control`      | Explicit control name: `text`, `textarea`, `richtext`, `toggle`, `number`, `select`, `image`, `gallery`, `file`, `posts`. Overrides the type-derived choice. An unsupported value falls back to the type-derived control and logs a warning in debug mode, rather than silently dropping the field. |
| `label`        | Field label in the sidebar. Defaults to a humanized version of the attribute key — `ctaLabel` becomes "Cta label".                                                                                                                                                                                  |
| `help`         | Help text shown under the field.                                                                                                                                                                                                                                                                    |
| `placeholder`  | Text an empty [in-place field](#the-canvas) shows. Defaults to the label.                                                                                                                                                                                                                           |
| `options`      | Choices for a `select` control: a list of raw values, a `value => label` map, or an already normalized list of `{label, value}` pairs.                                                                                                                                                              |
| `allowedTypes` | Media types the `file` or `gallery` picker offers, as MIME types or top-level types — `['audio']`, `['image']`. Omit it and a `gallery` offers images while a `file` offers anything.                                                                                                               |
| `postTypes`    | Post types the `posts` control searches — `['termes', 'liens']`. Omit it and every viewable post type is searched.                                                                                                                                                                                  |

```php
public static function attributes(): array
{
    return [
        'variant' => [
            'type' => 'string',
            'default' => 'light',
            'control' => 'select',
            'options' => ['light' => 'Light', 'dark' => 'Dark'],
        ],
        'imageId' => [
            'type' => 'integer',
            'control' => 'image',
        ],
        'body' => [
            'type' => 'string',
            'control' => 'textarea',
            'label' => 'Body text',
            'help' => 'Shown below the title.',
        ],
    ];
}
```

These seven keys are stripped before the schema reaches WordPress: `register_block_type()` and `block.json` only ever see the attributes WordPress itself understands.

## Containers

A block accepts inner blocks as soon as one of three `#[AsBlock]` parameters is set — `allowedBlocks`, `innerBlocksTemplate`, or `innerBlocksTemplateLock`. Setting any of them turns the block into a container: the editor mounts the inner blocks where the template prints `content` (see [The canvas](#the-canvas)), and on the site the inner markup reaches the Twig template as `content`.

```php
#[AsBlock(
    name: 'theme/hero',
    title: 'Hero',
    category: 'theme',
    icon: 'cover-image',
    allowedBlocks: ['core/heading', 'core/paragraph', 'core/buttons'],
    innerBlocksTemplate: [
        ['core/heading', ['level' => 1, 'placeholder' => 'Hero title']],
        ['core/paragraph', ['placeholder' => 'Hero copy']],
    ],
    innerBlocksTemplateLock: 'insert',
)]
final readonly class HeroBlock implements BlockInterface
{
    public static function attributes(): array
    {
        return [
            'imageId' => ['type' => 'integer', 'control' => 'image', 'label' => 'Background image'],
            'align' => [
                'type' => 'string',
                'default' => 'left',
                'control' => 'select',
                'options' => ['left' => 'Left', 'center' => 'Center'],
            ],
        ];
    }

    public function compose(array $attributes, string $content, WP_Block $block): array
    {
        return [
            'imageId' => $attributes['imageId'],
            'align' => $attributes['align'],
            'content' => $content,
        ];
    }

    // render() as usual
}
```

```twig
{# templates/blocks/hero.twig #}
<section class="hero hero--{{ align }}" style="--hero-image: url('{{ get_image(imageId).src }}')">
    <div class="hero__content">{{ content|raw }}</div>
</section>
```

`imageId` and `align` still get sidebar controls, exactly as on a non-container block. `innerBlocksTemplateLock` accepts the same values as WordPress core's `templateLock`: `'all'` (no insert, move or remove), `'insert'` (no insert, but move and remove are allowed), `'contentOnly'`, or `false` to explicitly leave it unlocked.

`innerBlocksTemplate` and `innerBlocksTemplateLock` are editor-only: they configure the `InnerBlocks` component and never reach `block.json` or `register_block_type()`, unlike `allowedBlocks`, which WordPress also validates server-side.

## The canvas

The editor draws a Foehn block from its own Twig template, the same markup as the site, and makes it editable in place. **There is no preview to look at and a sidebar to type into: the author edits the block where it is shown.**

The registrar asks the `block-renderer` REST endpoint for the block's render with the `foehn_canvas` flag, and turns the HTML into editor elements. Two things in a template change in that render, and become live editor components:

- **`content`.** A container receives a marker as `content`. Where the template prints it, the editor mounts the block's inner blocks. When `content` is the only thing in an element, as in `<div class="hero__content">{{ content|raw }}</div>`, the inner blocks are the direct children of that element, exactly as a core container block holds them: a grid or a flex layout in the template applies to them unchanged. Print `content` once and do not change it with a filter: the editor mounts the inner blocks at the first marker only, and adds them at the end of the root element when it finds no marker. It warns in the console in both cases.
- **`block_editable()`.** An element marked with `{{ block_editable('<attribute>') }}` becomes a `RichText` bound to that attribute. The author clicks the heading and types. The attribute has no sidebar field any more.

```twig
{# templates/blocks/gallery.twig #}
<section class="gallery">
  {% if title or is_block_editor() %}
    <h2 {{ block_editable('title') }}>{{ title }}</h2>
  {% endif %}
  …
</section>
```

On the site, `block_editable()` prints nothing and `is_block_editor()` is false: the markup does not change. Use `is_block_editor()` to keep an empty editable element in the markup, so the author has a place to type.

The theme script runs on the canvas when the theme enqueues it on `enqueue_block_assets`, as the starter does. js-toolkit components mount by themselves, and mount again when the editor renders a block again, so a carousel or a video cover works as on the site. A component that hides content the author must edit, such as an accordion panel, must not mount in the editor: render the element without `data-component` and in its open state when `is_block_editor()` is true.

A string attribute edited in place stays plain text: no formatting, and the template prints it escaped, `{{ title }}`. Declare `'control' => 'richtext'` to let the author apply bold, italic and links. The value is then HTML, which the template prints with `{{ caption|e('wp_kses_post')|raw }}`. A `richtext` attribute that no element marks falls back to a textarea in the sidebar, so it is never out of reach.

The rest of the render follows the editor's rules for its own blocks:

- **The root element is the block.** When the template has one root element, it receives the block wrapper props (`useBlockProps`): the block toolbar, the selection outline and the block supports land on the template's own `<section>`, with no wrapper `<div>` around it. A template with several root nodes gets one wrapper.
- **The render is inert.** Links do not navigate, forms do not submit, scripts and `on*` attributes are dropped, and iframes do not take the clicks that select the block.
- **Images have a toolbar button.** Each `image` attribute adds a replace button to the block toolbar, as on core's image block. The sidebar control stays.
- **A change re-renders.** A sidebar change asks for a new render after a short pause. Typing in an editable element does not: `RichText` already shows the text.

Use inner blocks for free-form prose: several paragraphs, headings, lists and buttons, in an order the author chooses. Use `block_editable()` for text the template places itself: a section title, a caption, an item label. Use sidebar controls for the data around them: an image, a variant, a link target.

## Blocks are dynamic by construction

A Foehn block has no `save` parameter, and there is no static-block code path anywhere in the framework: `#[AsBlock]` carries no `save`, `BlockJsonGenerator` never emits one, and `BlockDiscovery` registers every block exclusively through `render_callback`. This is a guarantee, not an accident of the current implementation.

It is also what makes the rest of this page possible. The canvas can show the real markup because every block re-renders through PHP on every request, in the editor and on the front end alike. And because there is no serialized markup to keep in sync, a Twig template can be rewritten years from now — new markup, new class names, an entirely different structure — without invalidating a single piece of existing content. The post content stores attributes, not HTML.

Every Foehn block also gets `supports.html` set to `false` by default, for the same reason: "Edit as HTML" lets an author hand-edit the serialized block markup, which only makes sense for a block with real saved output to edit. A dynamic block has none, so the option can only ever produce an invalid block. An explicit `supports: ['html' => true]` on a block still wins if a project genuinely needs it.

## Where the registrar comes from

The editor script that reads block schemas and builds sidebar controls is a single generic file, identical for every Foehn project. It is generated into the web root — `wp-content/foehn/editor.js` — when you run `composer install` or `composer update`, the same way Foehn already generates `wp-config.php`, `index.php`, and the mu-plugin loader.

This exists because the framework itself lives in `vendor/studiometa/foehn/`, outside the document root, and cannot be served to a browser directly. Copying it into the web root gives it a real, cacheable URL and genuine line numbers in devtools.

If the file is missing — a fresh clone before its first `composer install`, or a deployment that skipped it — no Foehn block registers client-side. The block inserter loses every Foehn block, and existing ones on a page show as "unsupported". Foehn does not fail silently here: it logs a message to the browser console explaining that the registrar is missing and that it is generated by `composer install`, so the symptom points straight at the fix.

## No build step, ever

Adding or editing a block never requires a `composer install`, a JavaScript build, or an npm dependency. The registrar is static and does not change when you add a block; the block definitions it reads — names, titles, attribute schemas, container configuration — come from Foehn's discovery, the same mechanism that already finds your post types and hooks, and refresh on every request in development or whenever the discovery cache is warmed in production.

Write the PHP class, write the Twig template, and the block is authorable. There is nothing to install, nothing to compile, and no `package.json` involved.

## Styling a block

A block's own CSS and JS are named after the block, and are loaded when the files exist:

| File                            | Loaded                                                         |
| ------------------------------- | -------------------------------------------------------------- |
| `assets/css/blocks/callout.css` | front end and editor                                           |
| `assets/js/blocks/callout.js`   | front end, when the block is used, as a `type="module"` script |

Nothing declares them — `theme/callout` finds `callout.css` and `callout.js` because of their names. Both are attached to the block type, so WordPress loads them only on pages that render the block, and loads the stylesheet into the editor too. That last part is what makes the canvas look like the site: it is the same markup with the same stylesheet. A theme stylesheet that holds the block styles, such as a Tailwind build, and the theme script go into the editor through the `enqueue_block_assets` action, which WordPress loads inside the editor iframe.

The script is a script module, so `import` works in it — including `@wordpress/interactivity`, which WordPress resolves through its import map. These files are served as they are, so they are also outside any build pipeline. Keep them plain CSS and plain JS, and a block stays self-contained — it works in a checkout that has never run `npm install`. Anything that needs Tailwind or bundling belongs in the theme's main stylesheet instead.

## See Also

- [Native Blocks](./native-blocks)
- [API Reference: #[AsBlock]](/api/as-block)
- [API Reference: BlockInterface](/api/block-interface)
