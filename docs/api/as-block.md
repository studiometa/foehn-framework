# #[AsBlock]

Register a class as a native Gutenberg block.

## Signature

```php
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsBlock
{
    public function __construct(
        public string $name,
        public string $title,
        public string $category = 'widgets',
        public ?string $icon = null,
        public ?string $description = null,
        public array $keywords = [],
        public array $supports = [],
        public ?string $parent = null,
        public array $ancestor = [],
        public bool $interactivity = false,
        public ?string $interactivityNamespace = null,
        public ?string $template = null,
        public array $allowedBlocks = [],
        public array $innerBlocksTemplate = [],
        public string|bool|null $innerBlocksTemplateLock = null,
    ) {}

    public function getInteractivityNamespace(): string {}

    public static function hasInnerBlocks(
        array $allowedBlocks,
        array $innerBlocksTemplate,
        string|bool|null $innerBlocksTemplateLock,
    ): bool {}
}
```

## Parameters

| Parameter                 | Type                 | Default       | Description                                                       |
| ------------------------- | -------------------- | ------------- | ----------------------------------------------------------------- |
| `name`                    | `string`             | —             | Block name with namespace (required)                              |
| `title`                   | `string`             | —             | Display title (required)                                          |
| `category`                | `string`             | `'widgets'`   | Block category                                                    |
| `icon`                    | `?string`            | `null`        | Dashicon name or SVG                                              |
| `description`             | `?string`            | `null`        | Block description                                                 |
| `keywords`                | `string[]`           | `[]`          | Search keywords                                                   |
| `supports`                | `array`              | `[]`          | Block supports configuration                                      |
| `parent`                  | `?string`            | `null`        | Parent block name                                                 |
| `ancestor`                | `string[]`           | `[]`          | Ancestor block names                                              |
| `interactivity`           | `bool`               | `false`       | Enable WordPress Interactivity API                                |
| `interactivityNamespace`  | `?string`            | Block name    | Custom interactivity namespace                                    |
| `template`                | `?string`            | Auto-resolved | Template path                                                     |
| `allowedBlocks`           | `string[]`           | `[]`          | Block names allowed as inner blocks                               |
| `innerBlocksTemplate`     | `array`              | `[]`          | InnerBlocks template                                              |
| `innerBlocksTemplateLock` | `string\|bool\|null` | `null`        | InnerBlocks lock: `'all'`, `'insert'`, `'contentOnly'` or `false` |

Setting any of the three `allowedBlocks` / `innerBlocksTemplate` / `innerBlocksTemplateLock` parameters makes the block a container: the editor renders `InnerBlocks` instead of a server-rendered preview, and the inner markup reaches the Twig template as `content`.

## Assets

There is no parameter for a block's stylesheet or script. Both are found by naming them after the block, and are loaded when the files exist:

| File                            | Loaded                            | WordPress argument    |
| ------------------------------- | --------------------------------- | --------------------- |
| `assets/css/blocks/callout.css` | front end and editor              | `style_handles`       |
| `assets/js/blocks/callout.js`   | front end, when the block is used | `view_script_handles` |

For a block named `theme/callout`, the file name is the part after the namespace — `callout`. Both paths are theme-relative and resolved with `get_theme_file_path()`, so a child theme can override either file.

Because the assets are attached to the block type rather than enqueued globally, WordPress loads them only on pages that actually render the block, and loads the stylesheet into the editor as well — which is what makes the server-rendered preview look like the front end.

A block with no such files needs no configuration, and a file that does not exist registers nothing: registering an absent asset would emit a 404 on every page using the block.

## Usage

### Basic Block

```php
<?php

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
            'type' => ['type' => 'string', 'default' => 'info'],
            'message' => ['type' => 'string', 'default' => ''],
        ];
    }

    public function compose(array $attributes, string $content, WP_Block $block): array
    {
        return [
            'type' => $attributes['type'],
            'message' => $attributes['message'],
        ];
    }

    public function render(array $attributes, string $content, WP_Block $block): string
    {
        return $this->view->render('blocks/alert', $this->compose($attributes, $content, $block));
    }
}
```

### Interactive Block

```php
<?php

namespace App\Blocks\Counter;

use Studiometa\Foehn\Attributes\AsBlock;
use Studiometa\Foehn\Contracts\InteractiveBlockInterface;
use WP_Block;

#[AsBlock(
    name: 'theme/counter',
    title: 'Counter',
    interactivity: true,
)]
final readonly class CounterBlock implements InteractiveBlockInterface
{
    public static function attributes(): array
    {
        return [
            'initialCount' => ['type' => 'number', 'default' => 0],
        ];
    }

    public static function initialState(): array
    {
        return ['totalClicks' => 0];
    }

    public function initialContext(array $attributes): array
    {
        return ['count' => $attributes['initialCount']];
    }

    public function compose(array $attributes, string $content, WP_Block $block): array
    {
        return ['context' => $this->initialContext($attributes)];
    }

    public function render(array $attributes, string $content, WP_Block $block): string
    {
        // ...
    }
}
```

### With Supports

```php
#[AsBlock(
    name: 'theme/card',
    title: 'Card',
    supports: [
        'align' => ['wide', 'full'],
        'color' => ['background' => true, 'text' => true],
        'spacing' => ['padding' => true],
        'html' => false,
    ],
)]
```

## Required Interfaces

- Basic blocks: `BlockInterface`
- Interactive blocks: `InteractiveBlockInterface`

## Related

- [Guide: Native Blocks](/guide/native-blocks)
- [`BlockInterface`](./block-interface)
- [`InteractiveBlockInterface`](./interactive-block-interface)
