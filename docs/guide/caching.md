# Caching

Føhn provides an injectable caching service built on WordPress transients. Use it to cache expensive operations like database queries, API calls, or computed data.

## Getting the Cache Service

Føhn registers `CacheInterface` as a singleton in the DI container. Inject it into any discovered class:

```php
use Studiometa\Foehn\Contracts\CacheInterface;

final readonly class ProductService
{
    public function __construct(
        private CacheInterface $cache,
    ) {}

    public function getFeatured(): array
    {
        return $this->cache->remember('featured_products', 3600, fn() => get_posts([
            'post_type' => 'product',
            'meta_key' => 'featured',
            'meta_value' => '1',
        ]));
    }
}
```

You can also retrieve it from the container directly:

```php
use Studiometa\Foehn\Contracts\CacheInterface;
use function Studiometa\Foehn\app;

$cache = app(CacheInterface::class);
```

## Basic Usage

### Store and Retrieve

```php
// Store a value (TTL in seconds)
$cache->set('my_key', $value, 3600); // 1 hour

// Retrieve a value
$value = $cache->get('my_key');
$value = $cache->get('my_key', 'default'); // With default
```

### Remember Pattern

The most common pattern: get from cache or compute and store.

```php
$posts = $cache->remember('recent_posts', 3600, function () {
    return get_posts([
        'post_type' => 'post',
        'numberposts' => 10,
        'orderby' => 'date',
    ]);
});
```

The callback is only executed if the key doesn't exist in cache.

### Other Operations

```php
// Check if key exists
if ($cache->has('my_key')) {
    // ...
}

// Delete a key
$cache->forget('my_key');

// Store forever (no expiration)
$cache->forever('my_key', $value);

// Remember forever
$cache->rememberForever('my_key', fn() => expensive_computation());

// Increment/decrement counters
$cache->increment('page_views');
$cache->decrement('remaining_credits', 5);
```

## Cache Tags

Tags allow grouping cache keys for batch invalidation.

### Storing with Tags

```php
// Store with a single tag
$products = $cache->tags(['products'])
    ->remember('products_list', 3600, fn() => get_products());

// Store with multiple tags
$featured = $cache->tags(['products', 'homepage'])
    ->remember('featured_products', 3600, fn() => get_featured_products());

// Paginated results - same tag, different keys
$page1 = $cache->tags(['products'])
    ->remember('products_page_1', 3600, fn() => get_products(page: 1));

$page2 = $cache->tags(['products'])
    ->remember('products_page_2', 3600, fn() => get_products(page: 2));
```

### Invalidating by Tag

When content changes, flush all related cache entries at once:

```php
// Flush all keys tagged with 'products'
$cache->flushTag('products');
// Clears: products_list, featured_products, products_page_1, products_page_2

// Flush multiple tags
$cache->flushTags(['products', 'categories']);
```

### Tagged Cache Methods

All standard cache methods are available on tagged cache:

```php
$cache->tags(['products'])->set('key', $value, 3600);
$cache->tags(['products'])->forever('key', $value);
$cache->tags(['products'])->remember('key', 3600, fn() => compute());
$cache->tags(['products'])->rememberForever('key', fn() => compute());
$cache->tags(['products'])->forget('key');
```

## Cache Invalidation with Hooks

Combine cache tags with Føhn's hook system for automatic invalidation:

```php
use Studiometa\Foehn\Attributes\AsAction;
use Studiometa\Foehn\Contracts\CacheInterface;

final readonly class ProductCache
{
    public function __construct(
        private CacheInterface $cache,
    ) {}

    /**
     * Get cached products list.
     */
    public function list(): array
    {
        return $this->cache->tags(['products'])
            ->remember('products_list', 3600, fn() => get_posts([
                'post_type' => 'product',
                'numberposts' => -1,
            ]));
    }

    /**
     * Get cached products by category.
     */
    public function byCategory(int $categoryId): array
    {
        return $this->cache->tags(['products', "category_{$categoryId}"])
            ->remember("products_cat_{$categoryId}", 3600, fn() => get_posts([
                'post_type' => 'product',
                'tax_query' => [
                    ['taxonomy' => 'product_cat', 'terms' => $categoryId],
                ],
            ]));
    }

    /**
     * Invalidate all product caches when a product is saved.
     */
    #[AsAction('save_post_product')]
    public function onProductSave(int $postId): void
    {
        $this->cache->flushTag('products');
    }

    /**
     * Invalidate category cache when a term is edited.
     */
    #[AsAction('edited_product_cat')]
    public function onCategoryEdit(int $termId): void
    {
        $this->cache->flushTag("category_{$termId}");
    }
}
```

## Real-World Examples

### Caching Menu Data

```php
use Studiometa\Foehn\Attributes\AsAction;
use Studiometa\Foehn\Contracts\CacheInterface;

final readonly class MenuCache
{
    public function __construct(
        private CacheInterface $cache,
    ) {}

    public function getPrimaryMenu(): array
    {
        return $this->cache->tags(['menus'])
            ->remember('menu_primary', DAY_IN_SECONDS, fn() => wp_get_nav_menu_items('primary') ?: []);
    }

    #[AsAction('wp_update_nav_menu')]
    public function invalidate(): void
    {
        $this->cache->flushTag('menus');
    }
}
```

### Caching API Responses

```php
use Studiometa\Foehn\Contracts\CacheInterface;

final readonly class WeatherService
{
    public function __construct(
        private CacheInterface $cache,
    ) {}

    public function getCurrentWeather(string $city): ?array
    {
        return $this->cache->tags(['weather', "city_{$city}"])
            ->remember("weather_{$city}", HOUR_IN_SECONDS, function () use ($city) {
                $response = wp_remote_get("https://api.weather.com/v1/current?city={$city}");

                if (is_wp_error($response)) {
                    return null;
                }

                return json_decode(wp_remote_retrieve_body($response), true);
            });
    }

    public function invalidateCity(string $city): void
    {
        $this->cache->flushTag("city_{$city}");
    }
}
```

## Cache Key Prefix

All cache keys are automatically prefixed with `foehn_` to avoid collisions with other plugins.

## How It Works

Under the hood, `TransientCache` uses WordPress transients:

- `set()` → `set_transient()`
- `get()` → `get_transient()`
- `forget()` → `delete_transient()`

Tag-to-key mappings are stored in a WordPress option (`foehn_cache_tags`). When you flush a tag, all associated transients are deleted and the mapping is cleaned up automatically.

::: info Object Caching
If you have a persistent object cache (Redis, Memcached), WordPress transients automatically use it. This means Føhn's cache benefits from your object cache without any configuration.
:::

## Best Practices

### Choose Appropriate TTLs

```php
// Frequently changing data - short TTL
$cache->remember('latest_posts', 5 * MINUTE_IN_SECONDS, fn() => ...);

// Relatively stable data - medium TTL
$cache->remember('menu_items', HOUR_IN_SECONDS, fn() => ...);

// Rarely changing data - long TTL with invalidation
$cache->tags(['settings'])
    ->remember('site_settings', DAY_IN_SECONDS, fn() => ...);
```

### Use Tags for Related Data

```php
// Group related caches with tags
$cache->tags(['user', "user_{$userId}"])->remember("user_profile_{$userId}", ...);
$cache->tags(['user', "user_{$userId}"])->remember("user_orders_{$userId}", ...);

// Invalidate all user data at once
$cache->flushTag("user_{$userId}");
```

### Don't Cache Small Operations

```php
// ❌ Don't cache simple operations
$cache->remember('current_time', 60, fn() => time());

// ✅ Cache expensive operations
$cache->remember('complex_query', 3600, fn() => $wpdb->get_results($complex_sql));
```

### Handle Cache Failures Gracefully

```php
// The callback always provides a fallback
$data = $cache->remember('api_data', 3600, function () {
    $response = wp_remote_get('https://api.example.com/data');

    // Return empty array on failure - it will be cached
    // Consider a shorter TTL for error states
    if (is_wp_error($response)) {
        return [];
    }

    return json_decode(wp_remote_retrieve_body($response), true);
});
```

## Related

- [API Reference: CacheInterface](/api/cache-interface)
- [Hooks](/guide/hooks) — For cache invalidation triggers
- [Discovery Cache](/guide/discovery-cache) — Caching Føhn's internal discovery system
