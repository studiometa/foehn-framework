# Kernel

The main bootstrap class for WP Tempest.

## Signature

```php
<?php

namespace Studiometa\WPTempest;

use Tempest\Container\Container;

final class Kernel
{
    /**
     * Boot the kernel.
     *
     * @param string $appPath Path to the app directory to scan
     * @param array<string, mixed> $config Configuration options
     */
    public static function boot(string $appPath, array $config = []): self;

    /**
     * Get the kernel instance.
     *
     * @throws RuntimeException If kernel not booted
     */
    public static function getInstance(): self;

    /**
     * Get the container instance.
     */
    public static function container(): Container;

    /**
     * Get a service from the container.
     *
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    public static function get(string $class): object;

    /**
     * Get the app path.
     */
    public function getAppPath(): string;

    /**
     * Get a configuration value.
     */
    public function getConfig(string $key, mixed $default = null): mixed;

    /**
     * Check if the kernel has been booted.
     */
    public function isBooted(): bool;
}
```

## Methods

### boot()

Initialize and boot the kernel. Call this once in `functions.php`.

```php
use Studiometa\WPTempest\Kernel;

Kernel::boot(__DIR__ . '/app');
```

With configuration:

```php
use Studiometa\WPTempest\Hooks\Cleanup\DisableEmoji;
use Studiometa\WPTempest\Hooks\Security\SecurityHeaders;

Kernel::boot(__DIR__ . '/app', [
    // Enable discovery cache for production
    'discovery_cache' => 'full',  // 'full', 'partial', 'none'

    // Custom cache path (optional)
    'discovery_cache_path' => WP_CONTENT_DIR . '/cache/wp-tempest/discovery',

    // Timber templates directories
    'timber_templates_dir' => ['templates', 'views'],

    // Opt-in built-in hooks
    'hooks' => [
        DisableEmoji::class,
        SecurityHeaders::class,
    ],
]);
```

### Configuration Options

| Option                 | Type             | Default         | Description                               |
| ---------------------- | ---------------- | --------------- | ----------------------------------------- |
| `discovery_cache`      | `string\|bool`   | `'none'`        | Cache strategy: 'full', 'partial', 'none' |
| `discovery_cache_path` | `string\|null`   | `null`          | Custom path for cache files               |
| `timber_templates_dir` | `string[]`       | `['templates']` | Timber templates directory names          |
| `hooks`                | `class-string[]` | `[]`            | Opt-in hook classes to activate           |

See [Discovery Cache](/guide/discovery-cache) and [Built-in Hooks](/guide/hooks#built-in-hooks) for details.

### getInstance()

Get the singleton kernel instance.

```php
$kernel = Kernel::getInstance();
$appPath = $kernel->getAppPath();
```

### container()

Access the Tempest DI container.

```php
$container = Kernel::container();
$container->singleton(MyService::class, fn() => new MyService());
```

### get()

Retrieve a service from the container.

```php
$viewEngine = Kernel::get(ViewEngineInterface::class);
$myService = Kernel::get(MyService::class);
```

### getConfig()

Get configuration values.

```php
$viewsPath = Kernel::getInstance()->getConfig('timber.views');
$default = Kernel::getInstance()->getConfig('missing.key', 'default');
```

## Lifecycle Hooks

The kernel hooks into WordPress lifecycle:

1. **`after_setup_theme`** — Early discoveries (theme setup)
2. **`init`** — Main discoveries (post types, taxonomies, blocks)
3. **`wp_loaded`** — Late discoveries (REST routes, templates)

## Usage

### Basic Setup

```php
<?php
// functions.php

require_once __DIR__ . '/vendor/autoload.php';

use Studiometa\WPTempest\Kernel;

Kernel::boot(__DIR__ . '/app');
```

### Accessing Services

```php
// In any class with DI
public function __construct(
    private readonly ViewEngineInterface $view,
) {}

// Or manually
$view = Kernel::get(ViewEngineInterface::class);
```

### Registering Custom Services

```php
// In a hook or early in bootstrap
use Studiometa\WPTempest\Kernel;

add_action('after_setup_theme', function () {
    $container = Kernel::container();

    $container->singleton(MyService::class, function () {
        return new MyService(
            apiKey: defined('MY_API_KEY') ? MY_API_KEY : '',
        );
    });
}, 0);
```

## Related

- [Guide: Getting Started](/guide/getting-started)
- [Guide: Installation](/guide/installation)
- [Guide: Discovery Cache](/guide/discovery-cache)
- [Helpers](./helpers)
