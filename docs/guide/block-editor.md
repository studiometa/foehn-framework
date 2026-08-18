# Block Editor

Every `#[AsBlock]` class is authorable in the block editor with no JavaScript and no build step: write a PHP class and a Twig template, and Føhn derives the sidebar controls and a real, server-rendered preview from them.

## The model

A Foehn block already declares an attribute schema in `attributes()` and renders itself server-side through `render()` and `compose()`. That schema is the only input the block editor needs.

Føhn ships one generic script — the registrar — that reads the schema of every discovered block and, for each one, registers an `edit` component built from the schema alone: a sidebar field per attribute, and a preview powered by the block's own `render_callback`. There is no per-block script to write, no `edit.js`, and nothing to compile.

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

This class alone is enough to get a working sidebar with two fields, `type` and `message`, and a preview that shows the real Twig output. Nothing else needs to change.

## Controls

The control for an attribute is derived from its `type`, with an optional `control` override for cases the type alone cannot express:

| Schema                                                        | Control    | Component                       |
| ------------------------------------------------------------- | ---------- | ------------------------------- |
| `'type' => 'string'`                                          | `text`     | `TextControl`                   |
| `'type' => 'string', 'options' => [...]` or `'enum' => [...]` | `select`   | `SelectControl`                 |
| `'type' => 'boolean'`                                         | `toggle`   | `ToggleControl`                 |
| `'type' => 'number'` or `'type' => 'integer'`                 | `number`   | `TextControl` (`type="number"`) |
| `'type' => 'string', 'control' => 'textarea'`                 | `textarea` | `TextareaControl`               |
| `'type' => 'integer', 'control' => 'image'`                   | `image`    | `MediaUpload`                   |

A string or number attribute gets a control from `type` alone; nothing else is required. `number` and `integer` share the same component, but the declared type still matters: an `integer` field rounds its value before calling `setAttributes`, because WordPress rejects a float against an `integer` schema and silently falls back to the default.

An attribute of a type with no derived control — `array`, `object`, or no `type` at all — gets no sidebar field. It still exists in the schema and reaches the template; it is simply not editable from the sidebar, which is the right outcome for data a block computes or receives from elsewhere rather than data an author sets.

Four keys beyond the WordPress ones (`type`, `default`, `enum`, ...) describe how the attribute shows up in the sidebar:

| Key       | Description                                                                                                                                                                                                                                                 |
| --------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `control` | Explicit control name: `text`, `textarea`, `toggle`, `number`, `select`, `image`. Overrides the type-derived choice. An unsupported value falls back to the type-derived control and logs a warning in debug mode, rather than silently dropping the field. |
| `label`   | Field label in the sidebar. Defaults to a humanized version of the attribute key — `ctaLabel` becomes "Cta label".                                                                                                                                          |
| `help`    | Help text shown under the field.                                                                                                                                                                                                                            |
| `options` | Choices for a `select` control: a list of raw values, a `value => label` map, or an already normalized list of `{label, value}` pairs.                                                                                                                      |

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

These four keys are stripped before the schema reaches WordPress: `register_block_type()` and `block.json` only ever see the attributes WordPress itself understands.

## Containers

A block accepts inner blocks as soon as one of three `#[AsBlock]` parameters is set — `allowedBlocks`, `innerBlocksTemplate`, or `innerBlocksTemplateLock`. Setting any of them turns the block into a container: the editor renders `InnerBlocks` in the canvas instead of a server-side preview, and the inner markup reaches the Twig template as `content`, exactly as it does today for a block with child blocks.

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
<section class="hero hero--{{ align }}" style="--hero-image: url('{{ image(imageId).src }}')">
    <div class="hero__content">{{ content|raw }}</div>
</section>
```

`imageId` and `align` still get sidebar controls, exactly as on a non-container block. `innerBlocksTemplateLock` accepts the same values as WordPress core's `templateLock`: `'all'` (no insert, move or remove), `'insert'` (no insert, but move and remove are allowed), `'contentOnly'`, or `false` to explicitly leave it unlocked.

`innerBlocksTemplate` and `innerBlocksTemplateLock` are editor-only: they configure the `InnerBlocks` component and never reach `block.json` or `register_block_type()`, unlike `allowedBlocks`, which WordPress also validates server-side.

## Why prose lives in InnerBlocks

**Prose lives in `InnerBlocks` using core blocks. Structured data lives in sidebar controls.**

This is a constraint, not a style preference, and it is worth understanding why. The block editor's in-canvas `RichText` editing — click a heading, type into it — works by editing DOM the editor itself rendered. A Foehn block's canvas is not that: it is `ServerSideRender`, an iframe-like preview that re-runs the block's own PHP `render_callback` and Twig template on every keystroke that changes an attribute. There is no editable DOM to click into, because the DOM shown is not the editor's to own — it is the site's actual markup, rendered by the site's own code.

A sidebar `TextControl` or `TextareaControl` works fine there because it is a plain form field with no requirement to be the thing on screen. It is an acceptable way to edit a label, a CTA, an alt text, or any short string. It is a poor way to edit a paragraph or a heading, because sidebar fields have no live-preview typing feedback, no formatting, and no place for a second heading and three paragraphs to coexist.

`InnerBlocks` solves this the way WordPress core already does: hand paragraph- and heading-shaped content to `core/paragraph` and `core/heading`, which have their own real, click-to-edit `RichText` implementations, and let a Foehn block own only the structure and the data around that content — an image, an alignment, a variant, a link target. A hero block is the clearest example: its heading and body are `InnerBlocks` using core blocks, and its background image and alignment are sidebar controls.

## Blocks are dynamic by construction

A Foehn block has no `save` parameter, and there is no static-block code path anywhere in the framework: `#[AsBlock]` carries no `save`, `BlockJsonGenerator` never emits one, and `BlockDiscovery` registers every block exclusively through `render_callback`. This is a guarantee, not an accident of the current implementation.

It is also what makes the rest of this page possible. `ServerSideRender` can only show a real preview because every block re-renders through PHP on every request, in the editor and on the front end alike. And because there is no serialized markup to keep in sync, a Twig template can be rewritten years from now — new markup, new class names, an entirely different structure — without invalidating a single piece of existing content. The post content stores attributes, not HTML.

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

Nothing declares them — `theme/callout` finds `callout.css` and `callout.js` because of their names. Both are attached to the block type, so WordPress loads them only on pages that render the block, and loads the stylesheet into the editor too. That last part is what makes the sidebar worth using: the server-rendered preview is styled exactly like the front end, because it is the same markup with the same stylesheet.

The script is a script module, so `import` works in it — including `@wordpress/interactivity`, which WordPress resolves through its import map. These files are served as they are, so they are also outside any build pipeline. Keep them plain CSS and plain JS, and a block stays self-contained — it works in a checkout that has never run `npm install`. Anything that needs Tailwind or bundling belongs in the theme's main stylesheet instead.

## See Also

- [Native Blocks](./native-blocks)
- [API Reference: #[AsBlock]](/api/as-block)
- [API Reference: BlockInterface](/api/block-interface)
