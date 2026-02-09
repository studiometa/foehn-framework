# WpDiscovery

Interface for all Føhn discovery classes. Each discovery is responsible for inspecting classes, collecting relevant items, and registering them with WordPress.

## Signature

```php
<?php

namespace Studiometa\Foehn\Discovery;

use ReflectionClass;

interface WpDiscovery
{
    /**
     * Inspect a class and collect relevant items.
     *
     * @param DiscoveryLocation $location The location where the class was found
     * @param ReflectionClass<object> $class
     */
    public function discover(DiscoveryLocation $location, ReflectionClass $class): void;

    /**
     * Get the discovery items collection.
     */
    public function getItems(): WpDiscoveryItems;

    /**
     * Set the discovery items collection (for cache restoration).
     */
    public function setItems(WpDiscoveryItems $items): void;

    /**
     * Apply discovered items (register with WordPress).
     */
    public function apply(): void;

    /**
     * Check if any items have been discovered.
     */
    public function hasItems(): bool;
}
```

## Implementing a Custom Discovery

### 1. Create the Discovery Class

```php
<?php

namespace App\Discovery;

use ReflectionClass;
use Studiometa\Foehn\Discovery\Concerns\CacheableDiscovery;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Studiometa\Foehn\Discovery\DiscoveryLocation;
use Studiometa\Foehn\Discovery\WpDiscovery;

final class WidgetDiscovery implements WpDiscovery
{
    use IsWpDiscovery;
    use CacheableDiscovery;

    public function discover(DiscoveryLocation $location, ReflectionClass $class): void
    {
        $attributes = $class->getAttributes(AsWidget::class);

        if ($attributes === []) {
            return;
        }

        $attribute = $attributes[0]->newInstance();

        $this->addItem($location, [
            'className' => $class->getName(),
            'name' => $attribute->name,
            'title' => $attribute->title,
        ]);
    }

    public function apply(): void
    {
        foreach ($this->getItems() as $item) {
            register_widget($item['className']);
        }
    }

    protected function itemToCacheable(array $item): array
    {
        return [
            'className' => $item['className'],
            'name' => $item['name'],
            'title' => $item['title'],
        ];
    }
}
```

### 2. Key Concepts

- **`discover()`** receives a `DiscoveryLocation` and a `ReflectionClass` — inspect the class for relevant attributes and store items via `addItem($location, $data)`
- **`apply()`** iterates over items and registers them with WordPress
- **`itemToCacheable()`** (from `CacheableDiscovery`) converts items to a serializable format

## Traits

### IsWpDiscovery

Provides storage via `WpDiscoveryItems`:

```php
trait IsWpDiscovery
{
    protected function addItem(DiscoveryLocation $location, array $item): void;
    public function getItems(): WpDiscoveryItems;
    public function setItems(WpDiscoveryItems $items): void;
    public function hasItems(): bool;
}
```

### CacheableDiscovery

Adds caching support:

```php
trait CacheableDiscovery
{
    public function getCacheableData(): array;
    public function restoreFromCache(array $data): void;
    public function wasRestoredFromCache(): bool;
    abstract protected function itemToCacheable(array $item): ?array;
}
```

## Related Classes

### DiscoveryLocation

Value object identifying where a class was found:

```php
$location = DiscoveryLocation::app('App\\', '/path/to/app');
$location = DiscoveryLocation::vendor('Vendor\\Package\\', '/path/to/vendor');

$location->namespace;  // 'App\\'
$location->path;       // '/path/to/app'
$location->isVendor;   // false
```

### WpDiscoveryItems

Location-aware collection for discovered items:

```php
$items = new WpDiscoveryItems();
$items->add($location, ['key' => 'value']);

$items->all();                    // Flat array of all items
$items->count();                  // Total number of items
$items->getForLocation($location); // Items for a specific location
$items->hasLocation($location);   // Check if location has items
$items->isEmpty();                // Check if empty
$items->toArray();                // Serialize for caching
WpDiscoveryItems::fromArray($data); // Restore from cache
```

## Related

- [DiscoveryRunner](./discovery-runner)
- [Guide: Custom Discovery](/guide/custom-discovery)
