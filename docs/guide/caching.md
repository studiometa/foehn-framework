# Caching

Føhn provides a simple caching API built on WordPress transients. Use it to cache expensive operations like database queries, API calls, or computed data.

## Basic Usage

### Store and Retrieve

```php
use Studiometa\Foehn\Helpers\Cache;

// Store a value (TTL in seconds)
Cache::set('my_key', $value, 3600); // 1 hour

// Retrieve a value
$value = Cache::get('my_key');
$value = Cache::get('my_key', 'default'); // With default
```

### Remember Pattern

The most common pattern: get from cache or compute and store.

```php
use Studiometa\Foehn\Helpers\Cache;

$posts = Cache::remember('recent_posts', 3600, function () {
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
if (Cache::has('my_key')) {
    // ...
}

// Delete a key
Cache::forget('my_key');

// Store forever (no expiration)
Cache::forever('my_key', $value);

// Increment/decrement counters
Cache::increment('page_views');
Cache::decrement('remaining_credits', 5);
```

## Cache Tags

Tags allow grouping cache keys for batch invalidation. This is the industry-standard pattern used by Laravel and Symfony.

### Storing with Tags

```php
use Studiometa\Foehn\Helpers\Cache;

// Store with a single tag
$products = Cache::tags(['products'])
    ->remember('products_list', 3600, fn() => get_products());

// Store with multiple tags
$featured = Cache::tags(['products', 'homepage'])
    ->remember('featured_products', 3600, fn() => get_featured_products());

// Paginated results - same tag, different keys
$page1 = Cache::tags(['products'])
    ->remember('products_page_1', 3600, fn() => get_products(page: 1));

$page2 = Cache::tags(['products'])
    ->remember('products_page_2', 3600, fn() => get_products(page: 2));
```

### Invalidating by Tag

When content changes, flush all related cache entries at once:

```php
use Studiometa\Foehn\Helpers\Cache;

// Flush all keys tagged with 'products'
Cache::flushTag('products');
// Clears: products_list, featured_products, products_page_1, products_page_2

// Flush multiple tags
Cache::flushTags(['products', 'categories']);
```

### Tagged Cache Methods

All standard cache methods are available on tagged cache:

```php
Cache::tags(['products'])->put('key', $value, 3600);
Cache::tags(['products'])->forever('key', $value);
Cache::tags(['products'])->remember('key', 3600, fn() => compute());
Cache::tags(['products'])->rememberForever('key', fn() => compute());
Cache::tags(['products'])->forget('key');
```

## Cache Invalidation with Hooks

Combine cache tags with Føhn's hook system for automatic invalidation:

```php
use Studiometa\Foehn\Attributes\AsAction;
use Studiometa\Foehn\Helpers\Cache;

final readonly class ProductCache
{
    /**
     * Get cached products list.
     */
    public function list(): array
    {
        return Cache::tags(['products'])
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
        return Cache::tags(['products', "category_{$categoryId}"])
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
        Cache::flushTag('products');
    }

    /**
     * Invalidate category cache when a term is edited.
     */
    #[AsAction('edited_product_cat')]
    public function onCategoryEdit(int $termId): void
    {
        Cache::flushTag("category_{$termId}");
    }
}
```

::: tip Register the Hook Class
Don't forget to register your cache class in `foehn.php` so hooks are discovered:

```php
return [
    'hooks' => [
        App\Cache\ProductCache::class,
    ],
];
```

:::

## Real-World Examples

### Caching Menu Data

```php
use Studiometa\Foehn\Attributes\AsAction;
use Studiometa\Foehn\Helpers\Cache;

final readonly class MenuCache
{
    public function getPrimaryMenu(): array
    {
        return Cache::tags(['menus'])
            ->remember('menu_primary', DAY_IN_SECONDS, fn() => wp_get_nav_menu_items('primary') ?: []);
    }

    #[AsAction('wp_update_nav_menu')]
    public function invalidate(): void
    {
        Cache::flushTag('menus');
    }
}
```

### Caching API Responses

```php
use Studiometa\Foehn\Helpers\Cache;

final readonly class WeatherService
{
    public function getCurrentWeather(string $city): ?array
    {
        return Cache::tags(['weather', "city_{$city}"])
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
        Cache::flushTag("city_{$city}");
    }
}
```

### Caching Expensive Queries

```php
use Studiometa\Foehn\Attributes\AsAction;
use Studiometa\Foehn\Helpers\Cache;

final readonly class StatsCache
{
    public function getPostStats(): array
    {
        return Cache::tags(['stats'])
            ->remember('post_stats', HOUR_IN_SECONDS, function () {
                global $wpdb;

                return $wpdb->get_results("
                    SELECT post_type, COUNT(*) as count
                    FROM {$wpdb->posts}
                    WHERE post_status = 'publish'
                    GROUP BY post_type
                ", ARRAY_A);
            });
    }

    #[AsAction('transition_post_status')]
    public function onPostStatusChange(): void
    {
        Cache::flushTag('stats');
    }
}
```

## Cache Key Prefix

All cache keys are automatically prefixed with `foehn_` to avoid collisions with other plugins.

```php
Cache::set('my_key', 'value');
// Actually stores as: foehn_my_key

// Change the prefix if needed
Cache::setPrefix('mytheme_');
```

## How It Works

Under the hood, Føhn's cache uses WordPress transients:

- `Cache::set()` → `set_transient()`
- `Cache::get()` → `get_transient()`
- `Cache::forget()` → `delete_transient()`

Tag-to-key mappings are stored in a WordPress option (`foehn_cache_tags`). When you flush a tag, all associated transients are deleted and the mapping is cleaned up automatically.

::: info Object Caching
If you have a persistent object cache (Redis, Memcached), WordPress transients automatically use it. This means Føhn's cache helper benefits from your object cache without any configuration.
:::

## Best Practices

### Choose Appropriate TTLs

```php
// Frequently changing data - short TTL
Cache::remember('latest_posts', 5 * MINUTE_IN_SECONDS, fn() => ...);

// Relatively stable data - medium TTL
Cache::remember('menu_items', HOUR_IN_SECONDS, fn() => ...);

// Rarely changing data - long TTL with invalidation
Cache::tags(['settings'])
    ->remember('site_settings', DAY_IN_SECONDS, fn() => ...);
```

### Use Tags for Related Data

```php
// Group related caches with tags
Cache::tags(['user', "user_{$userId}"])->remember("user_profile_{$userId}", ...);
Cache::tags(['user', "user_{$userId}"])->remember("user_orders_{$userId}", ...);

// Invalidate all user data at once
Cache::flushTag("user_{$userId}");
```

### Don't Cache Small Operations

```php
// ❌ Don't cache simple operations
Cache::remember('current_time', 60, fn() => time());

// ✅ Cache expensive operations
Cache::remember('complex_query', 3600, fn() => $wpdb->get_results($complex_sql));
```

### Handle Cache Failures Gracefully

```php
// The callback always provides a fallback
$data = Cache::remember('api_data', 3600, function () {
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

- [API Reference: Cache Helper](/api/helpers#cache)
- [Hooks](/guide/hooks) - For cache invalidation triggers
- [Discovery Cache](/guide/discovery-cache) - Caching Føhn's internal discovery system
