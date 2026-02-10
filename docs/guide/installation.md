# Installation

## Requirements

- PHP 8.4 or higher
- WordPress 6.4 or higher
- Composer

## Install via Composer

```bash
composer require studiometa/foehn
```

## Theme Setup

### 1. Create the app directory

Create an `app/` directory in your theme for your classes:

```
your-theme/
├── app/
│   ├── Blocks/
│   ├── ContextProviders/
│   ├── Controllers/
│   ├── Hooks/
│   ├── Models/
│   └── Taxonomies/
├── templates/
├── functions.php
└── style.css
```

### 2. Configure Composer autoload

Add PSR-4 autoloading for your app namespace in `composer.json`:

```json
{
  "autoload": {
    "psr-4": {
      "App\\": "app/"
    }
  }
}
```

Then run:

```bash
composer dump-autoload
```

### 3. Boot the kernel

In your `functions.php`, boot the Føhn kernel:

```php
<?php
// functions.php

require_once __DIR__ . '/vendor/autoload.php';

use Studiometa\Foehn\Kernel;

// Boot with the path to your app directory
Kernel::boot(__DIR__ . '/app');
```

That's it! Føhn will now auto-discover all your attributed classes.

## Directory Structure

A typical Føhn theme structure:

```
your-theme/
├── app/
│   ├── Blocks/           # Block classes (ACF and native)
│   │   ├── Hero/
│   │   │   ├── HeroBlock.php
│   │   │   └── hero.twig
│   │   └── Counter/
│   │       ├── CounterBlock.php
│   │       └── counter.twig
│   ├── ContextProviders/ # Context providers
│   │   └── GlobalContextProvider.php
│   ├── Controllers/      # Template controllers
│   │   └── SingleController.php
│   ├── Hooks/            # Hook handlers
│   │   └── ThemeHooks.php
│   ├── Models/           # Custom post types
│   │   └── Product.php
│   ├── Rest/             # REST API endpoints
│   │   └── ProductsApi.php
│   └── Taxonomies/       # Custom taxonomies
│       └── ProductCategory.php
├── templates/            # Twig templates
│   ├── layouts/
│   ├── pages/
│   ├── components/
│   ├── blocks/
│   └── patterns/
├── functions.php
├── composer.json
└── style.css
```

## Configuration Options

You can pass configuration options when booting the kernel:

```php
Kernel::boot(__DIR__ . '/app', [
    // Enable discovery cache for production
    'discovery_cache' => 'full',  // 'full', 'partial', 'none'

    // Custom cache path (optional)
    'discovery_cache_path' => WP_CONTENT_DIR . '/cache/foehn/discovery',

    // Enable debug mode (defaults to WP_DEBUG)
    'debug' => true,
]);
```

### Debug Mode

When `debug` is enabled, Føhn will log discovery failures (e.g., classes that cannot be reflected) via `trigger_error()` with `E_USER_WARNING`. This helps identify misconfigured classes during development.

By default, debug mode follows the `WP_DEBUG` constant value.

For production deployments, enable the discovery cache and generate it after deployment:

```bash
wp tempest discovery:generate
```

See [Discovery Cache](/guide/discovery-cache) for more details.

## Using with Timber

Føhn is designed to work seamlessly with Timber. It automatically:

- Registers your post type classes in Timber's classmap
- Integrates context providers with Timber's context
- Uses Twig for block and pattern templates

## Using with ACF

For ACF blocks, ensure ACF Pro is installed. Føhn uses `stoutlogic/acf-builder` for defining fields:

```bash
composer require stoutlogic/acf-builder
```

## Verify Installation

Create a simple hook to verify everything works:

```php
<?php
// app/Hooks/TestHooks.php

namespace App\Hooks;

use Studiometa\Foehn\Attributes\AsAction;

final class TestHooks
{
    #[AsAction('init')]
    public function testInit(): void
    {
        error_log('Føhn is working!');
    }
}
```

Check your `debug.log` after loading any page. If you see the message, you're all set!
