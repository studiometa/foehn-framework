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
        public ?string $editorScript = null,
        public ?string $editorStyle = null,
        public ?string $style = null,
        public ?string $viewScript = null,
    ) {}

    public function getInteractivityNamespace(): string {}
}
```

## Parameters

| Parameter                | Type       | Default       | Description                          |
| ------------------------ | ---------- | ------------- | ------------------------------------ |
| `name`                   | `string`   | —             | Block name with namespace (required) |
| `title`                  | `string`   | —             | Display title (required)             |
| `category`               | `string`   | `'widgets'`   | Block category                       |
| `icon`                   | `?string`  | `null`        | Dashicon name or SVG                 |
| `description`            | `?string`  | `null`        | Block description                    |
| `keywords`               | `string[]` | `[]`          | Search keywords                      |
| `supports`               | `array`    | `[]`          | Block supports configuration         |
| `parent`                 | `?string`  | `null`        | Parent block name                    |
| `ancestor`               | `string[]` | `[]`          | Ancestor block names                 |
| `interactivity`          | `bool`     | `false`       | Enable WordPress Interactivity API   |
| `interactivityNamespace` | `?string`  | Block name    | Custom interactivity namespace       |
| `template`               | `?string`  | Auto-resolved | Template path                        |
| `editorScript`           | `?string`  | `null`        | Editor script path                   |
| `editorStyle`            | `?string`  | `null`        | Editor styles path                   |
| `style`                  | `?string`  | `null`        | Frontend styles path                 |
| `viewScript`             | `?string`  | `null`        | Frontend script path                 |

## Usage

### Basic Block

```php
<?php

namespace App\Blocks\Alert;

use Studiometa\WPTempest\Attributes\AsBlock;
use Studiometa\WPTempest\Contracts\BlockInterface;
use Studiometa\WPTempest\Contracts\ViewEngineInterface;
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

use Studiometa\WPTempest\Attributes\AsBlock;
use Studiometa\WPTempest\Contracts\InteractiveBlockInterface;
use WP_Block;

#[AsBlock(
    name: 'theme/counter',
    title: 'Counter',
    interactivity: true,
    viewScript: 'blocks/counter/view.js',
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
