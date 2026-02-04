# InteractiveBlockInterface

Interface for interactive Gutenberg blocks using the WordPress Interactivity API.

## Signature

```php
<?php

namespace Studiometa\WPTempest\Contracts;

interface InteractiveBlockInterface extends BlockInterface
{
    /**
     * Define initial state for the Interactivity API store.
     * This state is shared across all instances of this block type.
     *
     * @return array<string, mixed> Global state data
     */
    public static function initialState(): array;

    /**
     * Define initial context for this block instance.
     * This context is specific to each block instance and
     * will be serialized to data-wp-context attribute.
     *
     * @param array<string, mixed> $attributes Block attributes
     * @return array<string, mixed> Per-instance context data
     */
    public function initialContext(array $attributes): array;
}
```

## Methods

### initialState()

Define global state shared across all block instances. This is registered once with the Interactivity API.

```php
public static function initialState(): array
{
    return [
        'totalClicks' => 0,
        'isLoading' => false,
    ];
}
```

### initialContext()

Define per-instance context. This is serialized to `data-wp-context` on the block wrapper.

```php
public function initialContext(array $attributes): array
{
    return [
        'count' => $attributes['initialCount'],
        'step' => $attributes['step'] ?? 1,
    ];
}
```

## Usage

```php
<?php

namespace App\Blocks\Counter;

use Studiometa\WPTempest\Attributes\AsBlock;
use Studiometa\WPTempest\Contracts\InteractiveBlockInterface;
use Studiometa\WPTempest\Contracts\ViewEngineInterface;
use WP_Block;

#[AsBlock(
    name: 'theme/counter',
    title: 'Counter',
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
            'initialCount' => ['type' => 'number', 'default' => 0],
            'step' => ['type' => 'number', 'default' => 1],
        ];
    }

    public static function initialState(): array
    {
        return [
            'totalClicks' => 0,
        ];
    }

    public function initialContext(array $attributes): array
    {
        return [
            'count' => $attributes['initialCount'],
            'step' => $attributes['step'],
        ];
    }

    public function compose(array $attributes, string $content, WP_Block $block): array
    {
        return [
            'context' => $this->initialContext($attributes),
        ];
    }

    public function render(array $attributes, string $content, WP_Block $block): string
    {
        return $this->view->render('blocks/counter', $this->compose($attributes, $content, $block));
    }
}
```

## Template

```twig
{# views/blocks/counter.twig #}
<div
    class="counter"
    data-wp-interactive="theme/counter"
    {{ wp_context(context) }}
>
    <button data-wp-on--click="actions.decrement">-</button>
    <span data-wp-text="context.count">{{ context.count }}</span>
    <button data-wp-on--click="actions.increment">+</button>
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

## Related

- [Guide: Native Blocks](/guide/native-blocks)
- [`#[AsBlock]`](./as-block)
- [`BlockInterface`](./block-interface)
