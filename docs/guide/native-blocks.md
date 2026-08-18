# Native Blocks

Føhn provides `#[AsBlock]` for creating native Gutenberg blocks with optional WordPress Interactivity API support.

For how a block's `attributes()` schema becomes sidebar controls and a real editor preview, with no JavaScript to write, see the [Block Editor](./block-editor) guide.

## Basic Native Block

```php
<?php
// app/Blocks/Alert/AlertBlock.php

namespace App\Blocks\Alert;

use Studiometa\Foehn\Attributes\AsBlock;
use Studiometa\Foehn\Contracts\BlockInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use WP_Block;

#[AsBlock(
    name: 'theme/alert',
    title: 'Alert',
    category: 'widgets',
    icon: 'warning',
)]
final readonly class AlertBlock implements BlockInterface
{
    public function __construct(
        private ViewEngineInterface $view,
    ) {}

    public static function attributes(): array
    {
        return [
            'type' => [
                'type' => 'string',
                'default' => 'info',
            ],
            'message' => [
                'type' => 'string',
                'default' => '',
            ],
        ];
    }

    public function compose(array $attributes, string $content, WP_Block $block): array
    {
        return [
            'type' => $attributes['type'],
            'message' => $attributes['message'],
            'content' => $content,
        ];
    }

    public function render(array $attributes, string $content, WP_Block $block): string
    {
        $context = $this->compose($attributes, $content, $block);
        return $this->view->render('blocks/alert', $context);
    }
}
```

## Template

```twig
{# templates/blocks/alert.twig #}
<div class="alert alert--{{ type }}">
    {% if message %}
        <p class="alert__message">{{ message }}</p>
    {% endif %}

    {% if content %}
        <div class="alert__content">{{ content|raw }}</div>
    {% endif %}
</div>
```

## Interactive Blocks

For blocks with client-side interactivity, use the WordPress Interactivity API:

```php
<?php
// app/Blocks/Counter/CounterBlock.php

namespace App\Blocks\Counter;

use Studiometa\Foehn\Attributes\AsBlock;
use Studiometa\Foehn\Contracts\InteractiveBlockInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use WP_Block;

#[AsBlock(
    name: 'theme/counter',
    title: 'Counter',
    category: 'widgets',
    icon: 'calculator',
    interactivity: true,
    viewScript: 'blocks/counter/view.js',
)]
final readonly class CounterBlock implements InteractiveBlockInterface
{
    public function __construct(
        private ViewEngineInterface $view,
    ) {}

    public static function attributes(): array
    {
        return [
            'initialCount' => [
                'type' => 'number',
                'default' => 0,
            ],
            'step' => [
                'type' => 'number',
                'default' => 1,
            ],
        ];
    }

    public static function initialState(): array
    {
        // Global state shared across all counter instances
        return [
            'totalClicks' => 0,
        ];
    }

    public function initialContext(array $attributes): array
    {
        // Per-instance context
        return [
            'count' => $attributes['initialCount'],
            'step' => $attributes['step'],
        ];
    }

    public function compose(array $attributes, string $content, WP_Block $block): array
    {
        return [
            'initialCount' => $attributes['initialCount'],
            'step' => $attributes['step'],
            'context' => $this->initialContext($attributes),
        ];
    }

    public function render(array $attributes, string $content, WP_Block $block): string
    {
        $context = $this->compose($attributes, $content, $block);
        return $this->view->render('blocks/counter', $context);
    }
}
```

## Interactive Template

```twig
{# templates/blocks/counter.twig #}
<div
    class="counter"
    data-wp-interactive="theme/counter"
    {{ wp_context(context) }}
>
    <button
        class="counter__button counter__button--decrement"
        data-wp-on--click="actions.decrement"
    >
        -
    </button>

    <span
        class="counter__value"
        data-wp-text="context.count"
    >
        {{ context.count }}
    </span>

    <button
        class="counter__button counter__button--increment"
        data-wp-on--click="actions.increment"
    >
        +
    </button>
</div>
```

## View Script

```javascript
// assets/blocks/counter/view.js
import { store, getContext } from "@wordpress/interactivity";

store("theme/counter", {
  actions: {
    increment() {
      const context = getContext();
      context.count += context.step;
    },
    decrement() {
      const context = getContext();
      context.count -= context.step;
    },
  },
});
```

## Twig Interactivity Helpers

Føhn provides Twig helpers for the Interactivity API:

### wp_context

Outputs the `data-wp-context` attribute with JSON-encoded data:

```twig
<div {{ wp_context({ count: 0, isOpen: false }) }}>
{# Outputs: data-wp-context='{"count":0,"isOpen":false}' #}
```

### wp_directive

Outputs any `data-wp-*` directive:

```twig
<button {{ wp_directive('on--click', 'actions.toggle') }}>
{# Outputs: data-wp-on--click="actions.toggle" #}

<div {{ wp_directive('class--active', 'context.isActive') }}>
{# Outputs: data-wp-class--active="context.isActive" #}
```

## Block Supports

Configure block features:

```php
#[AsBlock(
    name: 'theme/card',
    title: 'Card',
    supports: [
        'align' => ['wide', 'full'],
        'color' => [
            'background' => true,
            'text' => true,
        ],
        'spacing' => [
            'padding' => true,
            'margin' => true,
        ],
        'typography' => [
            'fontSize' => true,
        ],
    ],
)]
```

## Block Categories

Register custom categories:

```php
<?php

namespace App\Blocks;

use Studiometa\Foehn\Attributes\AsBlockCategory;

#[AsBlockCategory(slug: 'theme', title: 'Theme Blocks', icon: 'star-filled')]
final class ThemeBlocks {}
```

## Full Configuration Example

```php
#[AsBlock(
    name: 'theme/accordion',
    title: 'Accordion',
    category: 'theme',
    icon: 'list-view',
    description: 'Expandable accordion sections',
    keywords: ['faq', 'collapse', 'toggle'],
    supports: [
        'align' => true,
        'html' => false,
    ],
    parent: null,
    ancestor: [],
    interactivity: true,
    interactivityNamespace: 'theme/accordion',
    template: 'blocks/accordion',
    allowedBlocks: ['core/heading', 'core/paragraph'],
    innerBlocksTemplate: [['core/heading', ['level' => 3]]],
    innerBlocksTemplateLock: 'insert',
    editorStyle: 'blocks/accordion/editor.css',
    style: 'blocks/accordion/style.css',
    viewScript: 'blocks/accordion/view.js',
)]
```

## File Structure

```
app/Blocks/
├── Alert/
│   └── AlertBlock.php
├── Counter/
│   └── CounterBlock.php
└── Accordion/
    └── AccordionBlock.php

templates/blocks/
├── alert.twig
├── counter.twig
└── accordion.twig

assets/blocks/
├── counter/
│   ├── view.js
│   ├── editor.js
│   └── style.css
└── accordion/
    ├── view.js
    └── style.css
```

## Attribute Parameters

| Parameter                 | Type                 | Default       | Description                     |
| ------------------------- | -------------------- | ------------- | ------------------------------- |
| `name`                    | `string`             | _required_    | Block name with namespace       |
| `title`                   | `string`             | _required_    | Display title                   |
| `category`                | `string`             | `'widgets'`   | Block category                  |
| `icon`                    | `?string`            | `null`        | Dashicon or SVG                 |
| `description`             | `?string`            | `null`        | Block description               |
| `keywords`                | `string[]`           | `[]`          | Search keywords                 |
| `supports`                | `array`              | `[]`          | Block supports                  |
| `parent`                  | `?string`            | `null`        | Parent block                    |
| `ancestor`                | `string[]`           | `[]`          | Ancestor blocks                 |
| `interactivity`           | `bool`               | `false`       | Enable Interactivity API        |
| `interactivityNamespace`  | `?string`            | Block name    | Interactivity namespace         |
| `template`                | `?string`            | Auto-resolved | Template path                   |
| `editorScript`            | `?string`            | `null`        | Editor script (rarely needed)   |
| `editorStyle`             | `?string`            | `null`        | Editor styles                   |
| `style`                   | `?string`            | `null`        | Frontend styles                 |
| `viewScript`              | `?string`            | `null`        | Frontend script (interactivity) |
| `allowedBlocks`           | `string[]`           | `[]`          | Allowed inner block names       |
| `innerBlocksTemplate`     | `array`              | `[]`          | InnerBlocks template            |
| `innerBlocksTemplateLock` | `string\|bool\|null` | `null`        | InnerBlocks lock                |

No editor JavaScript is needed. Foehn derives the sidebar controls from the block's `attributes()` schema and previews the block with its own server-side rendering, so a block is a PHP class plus a Twig template and nothing else. See the [Block Editor](./block-editor) guide for the control table and how `allowedBlocks` / `innerBlocksTemplate` / `innerBlocksTemplateLock` turn a block into a container.

## See Also

- [Block Editor](./block-editor)
- [ACF Blocks](./acf-blocks)
- [Block Patterns](./block-patterns)
- [API Reference: #[AsBlock]](/api/as-block)
- [API Reference: BlockInterface](/api/block-interface)
- [API Reference: InteractiveBlockInterface](/api/interactive-block-interface)
