# Discovery Cache

Føhn uses PHP reflection to discover attributes at runtime. While this provides a great developer experience, it can add overhead in production. The discovery cache stores discovery results to avoid runtime reflection.

## Configuration

Enable discovery caching by passing configuration when booting the kernel:

```php
<?php
// functions.php

use Studiometa\Foehn\Kernel;

Kernel::boot(__DIR__ . '/app', [
    'discovery_cache' => 'full',  // or 'partial', 'none', true, false
]);
```

### Cache Strategies

| Strategy    | Description                                          |
| ----------- | ---------------------------------------------------- |
| `'full'`    | Cache all discoveries (vendor + app) - best for prod |
| `'partial'` | Cache only vendor discoveries - good for staging     |
| `'none'`    | Disable caching - use in development                 |
| `true`      | Alias for `'full'`                                   |
| `false`     | Alias for `'none'`                                   |

### Custom Cache Path

By default, cache files are stored in `wp-content/cache/foehn/discovery/`. You can customize this:

```php
Kernel::boot(__DIR__ . '/app', [
    'discovery_cache' => 'full',
    'discovery_cache_path' => WP_CONTENT_DIR . '/cache/my-theme/discovery',
]);
```

## CLI Commands

### Generate Cache

Scan every discovery location and write the result to the cache. This is the deployment step: without it, a site with caching enabled reflects over the framework and the theme on every request.

```bash
wp foehn discovery:generate
```

Options:

- `--strategy=<strategy>` - Override configured strategy (full, partial)
- `--clear` - Clear existing cache before generating

```bash
# Generate with a specific strategy
wp foehn discovery:generate --strategy=full

# Clear and regenerate
wp foehn discovery:generate --clear
```

The command reports what it found:

```
Generating discovery cache using 'full' strategy...
Success: Discovery cache generated successfully (12 discoveries cached).

Cached discoveries:
  - HookDiscovery: 18 items
  - CliCommandDiscovery: 18 items
  - TwigExtensionDiscovery: 3 items
  - PostTypeDiscovery: 2 items
  ...
```

Nothing is applied while generating: the command builds and stores, so running it inside a booted request cannot register a hook twice.

### Clear Cache

Clear the discovery cache:

```bash
wp foehn discovery:clear
```

Run this command when:

- Adding or removing attributed classes
- Changing attribute parameters
- Deploying new code

### Check Status

View the current cache status:

```bash
wp foehn discovery:status
```

Output example:

```
Discovery Cache Status
======================

Strategy: full
Enabled: Yes
Cache path: /var/www/html/wp-content/cache/foehn/discovery
  ✓ Studiometa\Foehn\
  ✓ App\
Locations cached: 2/2

Discovery cache is active and valid.
```

## Deployment Workflow

### Basic Deployment

```bash
# 1. Deploy your code
git pull origin main

# 2. Install dependencies
composer install --no-dev --optimize-autoloader

# 3. Generate the discovery cache
wp foehn discovery:generate
```

### With CI/CD

Add to your deployment script:

```yaml
# GitHub Actions example
deploy:
  runs-on: ubuntu-latest
  steps:
    - name: Deploy code
      run: rsync -avz ./ user@server:/var/www/html/

    - name: Generate discovery cache
      run: |
        ssh user@server "cd /var/www/html && wp foehn discovery:generate"
```

### With Laravel Forge

In your deploy script:

```bash
cd /home/forge/example.com

git pull origin main
composer install --no-dev --optimize-autoloader

# Generate the Føhn discovery cache
php wp-cli.phar foehn discovery:generate

# Clear other caches
php wp-cli.phar cache flush
```

## Environment-Based Configuration

Use environment variables for different environments:

```php
<?php
// functions.php

use Studiometa\Foehn\Kernel;

$cacheStrategy = match (wp_get_environment_type()) {
    'production' => 'full',
    'staging' => 'partial',
    default => 'none',
};

Kernel::boot(__DIR__ . '/app', [
    'discovery_cache' => $cacheStrategy,
]);
```

Or use a constant in `wp-config.php`:

```php
// wp-config.php
define('FOEHN_DISCOVERY_CACHE', 'full');
```

```php
// functions.php
Kernel::boot(__DIR__ . '/app', [
    'discovery_cache' => defined('FOEHN_DISCOVERY_CACHE')
        ? FOEHN_DISCOVERY_CACHE
        : 'none',
]);
```

## How It Works

1. **Without cache**: On each request, Føhn scans all PHP files in your app directory, reflecting on classes to find attributes.

2. **With cache**: Discovery results are stored as a PHP array file. On subsequent requests, this file is loaded directly (benefiting from PHP's opcode cache).

3. **Cache invalidation**: The cache stores the configured strategy. If you change strategies, the cache is automatically invalidated.

### What's Cached

The cache stores serialized discovery data for:

- Hook registrations (actions/filters)
- Post types and taxonomies
- Blocks (ACF and native)
- Block patterns
- Context providers
- Template controllers
- REST routes
- Shortcodes
- CLI commands

### Cache Format

Cache files are stored as executable PHP for opcode caching:

```php
<?php

declare(strict_types=1);

// Auto-generated discovery cache - do not edit
// Generated: 2024-01-15 10:30:00

return [
    'Studiometa\\Foehn\\Discovery\\HookDiscovery' => [
        [
            'type' => 'action',
            'hook' => 'init',
            'className' => 'App\\Hooks\\ThemeHooks',
            'methodName' => 'onInit',
            'priority' => 10,
            'acceptedArgs' => 1,
        ],
        // ...
    ],
    // ...
];
```

## Troubleshooting

### Cache Not Working

1. Check if caching is enabled:

   ```bash
   wp foehn discovery:status
   ```

2. Ensure the cache directory is writable:

   ```bash
   chmod -R 755 wp-content/cache/foehn
   ```

3. Regenerate the cache:
   ```bash
   wp foehn discovery:generate --clear
   ```

### Changes Not Reflected

If your code changes aren't taking effect:

1. Clear the discovery cache:

   ```bash
   wp foehn discovery:clear
   ```

2. Clear PHP opcode cache:
   ```bash
   wp eval "opcache_reset();"
   ```

### Development Mode

Always disable caching in development to see changes immediately:

```php
Kernel::boot(__DIR__ . '/app', [
    'discovery_cache' => WP_DEBUG ? 'none' : 'full',
]);
```

## Performance Impact

| Scenario                | First Request | Subsequent Requests |
| ----------------------- | ------------- | ------------------- |
| No cache (development)  | ~50-100ms     | ~50-100ms           |
| Full cache (production) | ~50-100ms     | ~5-10ms             |

_Times are approximate and depend on the number of discovered classes._

## See Also

- [Installation Guide](/guide/installation)
- [CLI Commands](/guide/cli-commands)
- [API Reference: Kernel](/api/kernel)
