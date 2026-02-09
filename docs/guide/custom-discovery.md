# Custom Discovery

Føhn's discovery system can be extended with custom discoveries. This guide shows how to create a discovery that scans for your own PHP attributes and registers them with WordPress.

## Overview

A discovery class:

1. Implements the `WpDiscovery` interface
2. Inspects classes in `discover()` for a specific attribute
3. Registers discovered items with WordPress in `apply()`

## Creating a Custom Discovery

### Step 1: Define Your Attribute

```php
<?php
// app/Attributes/AsWidget.php

namespace App\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsWidget
{
    public function __construct(
        public string $name,
        public string $title,
        public string $description = '',
    ) {}
}
```

### Step 2: Create the Discovery Class

```php
<?php
// app/Discovery/WidgetDiscovery.php

namespace App\Discovery;

use App\Attributes\AsWidget;
use ReflectionClass;
use Studiometa\Foehn\Discovery\Concerns\CacheableDiscovery;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Studiometa\Foehn\Discovery\DiscoveryLocation;
use Studiometa\Foehn\Discovery\WpDiscovery;
use WP_Widget;

final class WidgetDiscovery implements WpDiscovery
{
    use IsWpDiscovery;
    use CacheableDiscovery;

    public function discover(DiscoveryLocation $location, ReflectionClass $class): void
    {
        // Check for the attribute
        $attributes = $class->getAttributes(AsWidget::class);

        if ($attributes === []) {
            return;
        }

        // Validate the class
        if (!$class->isSubclassOf(WP_Widget::class)) {
            return;
        }

        $attribute = $attributes[0]->newInstance();

        // Store the discovered item with its location
        $this->addItem($location, [
            'className' => $class->getName(),
            'name' => $attribute->name,
            'title' => $attribute->title,
            'description' => $attribute->description,
        ]);
    }

    public function apply(): void
    {
        add_action('widgets_init', function (): void {
            foreach ($this->getItems() as $item) {
                register_widget($item['className']);
            }
        });
    }

    protected function itemToCacheable(array $item): array
    {
        return [
            'className' => $item['className'],
            'name' => $item['name'],
            'title' => $item['title'],
            'description' => $item['description'],
        ];
    }
}
```

### Step 3: Register Your Discovery

Currently, custom discoveries must be registered manually. Add a hook in your theme to run the discovery alongside Føhn's built-in ones:

```php
<?php
// app/Hooks/CustomDiscoveryHooks.php

namespace App\Hooks;

use App\Discovery\WidgetDiscovery;
use Studiometa\Foehn\Attributes\AsAction;
use Studiometa\Foehn\Discovery\DiscoveryLocation;
use Studiometa\Foehn\Discovery\DiscoveryRunner;

final class CustomDiscoveryHooks
{
    public function __construct(
        private readonly DiscoveryRunner $runner,
        private readonly WidgetDiscovery $widgetDiscovery,
    ) {}

    #[AsAction('init')]
    public function registerWidgets(): void
    {
        $this->widgetDiscovery->apply();
    }
}
```

## Key Concepts

### The `discover()` Method

Receives two parameters:

- **`DiscoveryLocation $location`** — Where the class was found (app vs vendor, namespace, path)
- **`ReflectionClass $class`** — The class being inspected

Use `$location` when calling `addItem()`:

```php
$this->addItem($location, ['className' => $class->getName()]);
```

### The `apply()` Method

Called at the appropriate WordPress lifecycle phase. Iterate over items using `$this->getItems()`:

```php
public function apply(): void
{
    foreach ($this->getItems() as $item) {
        // Register with WordPress
    }
}
```

### Caching with `CacheableDiscovery`

The `CacheableDiscovery` trait adds cache support. Implement `itemToCacheable()` to define what gets serialized:

```php
protected function itemToCacheable(array $item): array
{
    // Return only serializable data (no objects, closures)
    return [
        'className' => $item['className'],
        'name' => $item['name'],
    ];
}
```

Items stored during `discover()` may contain non-serializable objects (like attribute instances). `itemToCacheable()` extracts only the data needed for `apply()`.

### Discovery Phases

Føhn runs discoveries in three phases:

| Phase     | WordPress Hook          | Use For                               |
| --------- | ----------------------- | ------------------------------------- |
| **Early** | `after_setup_theme`     | Theme setup, hooks, Twig extensions   |
| **Main**  | `init`                  | Post types, taxonomies, blocks        |
| **Late**  | `wp_loaded`             | REST routes, template controllers     |

Custom discoveries should register their `apply()` at the appropriate hook.

### Using `DiscoveryLocation`

The location tells you where a class came from:

```php
public function discover(DiscoveryLocation $location, ReflectionClass $class): void
{
    // Check origin
    if ($location->isVendor) {
        // Class from a Composer package
    } else {
        // Class from the app directory
    }

    $location->namespace;  // e.g. 'App\\'
    $location->path;       // e.g. '/path/to/theme/app'
}
```

### Using `WpDiscoveryItems`

The items collection provides useful methods:

```php
$items = $this->getItems();

// Iterate
foreach ($items as $item) { /* ... */ }

// Count
$count = $items->count();

// Check
$items->isEmpty();
$items->hasLocation($location);

// Get items for a specific location
$appItems = $items->getForLocation($appLocation);
```

## Traits Reference

### `IsWpDiscovery`

| Method       | Description                          |
| ------------ | ------------------------------------ |
| `addItem()`  | Add an item for a location           |
| `getItems()` | Get the `WpDiscoveryItems` collection |
| `setItems()` | Replace the items (cache restore)    |
| `hasItems()` | Check if any items exist             |

### `CacheableDiscovery`

| Method                  | Description                                |
| ----------------------- | ------------------------------------------ |
| `getCacheableData()`    | Export items in serializable format        |
| `restoreFromCache()`    | Import items from cached data              |
| `wasRestoredFromCache()`| Check if items came from cache             |
| `itemToCacheable()`     | Convert one item to cacheable format       |

## Related

- [API: WpDiscovery](../api/wp-discovery)
- [API: DiscoveryRunner](../api/discovery-runner)
- [Guide: Discovery Cache](/guide/discovery-cache)
