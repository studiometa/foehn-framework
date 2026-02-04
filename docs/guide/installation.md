# Installation

## Requirements

- PHP 8.4 or higher
- WordPress 6.4 or higher
- Composer

## Install via Composer

```bash
composer require studiometa/wp-tempest
```

## Theme Setup

### 1. Create the app directory

Create an `app/` directory in your theme for your classes:

```
your-theme/
├── app/
│   ├── Blocks/
│   ├── Hooks/
│   ├── Models/
│   └── Views/
├── views/
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

In your `functions.php`, boot the WP Tempest kernel:

```php
<?php
// functions.php

require_once __DIR__ . '/vendor/autoload.php';

use Studiometa\WPTempest\Kernel;

// Boot with the path to your app directory
Kernel::boot(__DIR__ . '/app');
```

That's it! WP Tempest will now auto-discover all your attributed classes.

## Directory Structure

A typical WP Tempest theme structure:

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
│   ├── Hooks/            # Hook handlers
│   │   └── ThemeHooks.php
│   ├── Models/           # Post types and taxonomies
│   │   ├── Product.php
│   │   └── ProductCategory.php
│   ├── Rest/             # REST API endpoints
│   │   └── ProductsApi.php
│   └── Views/            # View composers and controllers
│       ├── Composers/
│       └── Controllers/
├── views/                # Twig templates
│   ├── single.twig
│   ├── archive.twig
│   └── partials/
├── patterns/             # Block patterns (Twig)
├── functions.php
├── composer.json
└── style.css
```

## Configuration Options

You can pass configuration options when booting the kernel:

```php
Kernel::boot(__DIR__ . '/app', [
    'timber' => [
        'views' => __DIR__ . '/views',
    ],
    'blocks' => [
        'namespace' => 'theme',
    ],
]);
```

## Using with Timber

WP Tempest is designed to work seamlessly with Timber. It automatically:

- Registers your post type classes in Timber's classmap
- Integrates view composers with Timber's context
- Uses Twig for block and pattern templates

## Using with ACF

For ACF blocks, ensure ACF Pro is installed. WP Tempest uses `stoutlogic/acf-builder` for defining fields:

```bash
composer require stoutlogic/acf-builder
```

## Verify Installation

Create a simple hook to verify everything works:

```php
<?php
// app/Hooks/TestHooks.php

namespace App\Hooks;

use Studiometa\WPTempest\Attributes\AsAction;

final class TestHooks
{
    #[AsAction('init')]
    public function testInit(): void
    {
        error_log('WP Tempest is working!');
    }
}
```

Check your `debug.log` after loading any page. If you see the message, you're all set!
