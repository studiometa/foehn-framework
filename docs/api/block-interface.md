# BlockInterface

Interface for native Gutenberg blocks.

## Signature

```php
<?php

namespace Studiometa\WPTempest\Contracts;

use WP_Block;

interface BlockInterface
{
    /**
     * Define block attributes schema.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function attributes(): array;

    /**
     * Compose data for the view.
     *
     * @param array<string, mixed> $attributes Block attributes
     * @param string $content Inner block content
     * @param WP_Block $block Block instance
     * @return array<string, mixed> Context for the template
     */
    public function compose(array $attributes, string $content, WP_Block $block): array;

    /**
     * Render the block.
     *
     * @param array<string, mixed> $attributes Block attributes
     * @param string $content Inner block content
     * @param WP_Block $block Block instance
     * @return string Rendered HTML
     */
    public function render(array $attributes, string $content, WP_Block $block): string;
}
```

## Methods

### attributes()

Define the block's attributes schema. This is a static method used during block registration.

```php
public static function attributes(): array
{
    return [
        'title' => [
            'type' => 'string',
            'default' => '',
        ],
        'count' => [
            'type' => 'number',
            'default' => 0,
        ],
        'isActive' => [
            'type' => 'boolean',
            'default' => false,
        ],
    ];
}
```

### compose()

Transform attributes into template context. Called before rendering.

```php
public function compose(array $attributes, string $content, WP_Block $block): array
{
    return [
        'title' => $attributes['title'],
        'items' => $this->getItems($attributes['count']),
        'content' => $content,
    ];
}
```

### render()

Render the block HTML. Typically uses the view engine.

```php
public function render(array $attributes, string $content, WP_Block $block): string
{
    $context = $this->compose($attributes, $content, $block);
    return $this->view->render('blocks/my-block', $context);
}
```

## Usage

```php
<?php

namespace App\Blocks\Alert;

use Studiometa\WPTempest\Attributes\AsBlock;
use Studiometa\WPTempest\Contracts\BlockInterface;
use Studiometa\WPTempest\Contracts\ViewEngineInterface;
use WP_Block;

#[AsBlock(name: 'theme/alert', title: 'Alert')]
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
            'content' => $content,
        ];
    }

    public function render(array $attributes, string $content, WP_Block $block): string
    {
        return $this->view->render('blocks/alert', $this->compose($attributes, $content, $block));
    }
}
```

## Related

- [Guide: Native Blocks](/guide/native-blocks)
- [`#[AsBlock]`](./as-block)
- [`InteractiveBlockInterface`](./interactive-block-interface)
