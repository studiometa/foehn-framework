# CLI Commands

Foehn provides `#[AsCliCommand]` for creating WP-CLI commands and includes built-in scaffolding commands.

## Built-in Commands

Foehn includes scaffolding and discovery management commands:

### Scaffolding Commands

```bash
# Generate a Timber model (with optional post type)
wp tempest make:model Product
wp tempest make:model Product --post-type

# Generate a post type
wp tempest make:post-type Product

# Generate a taxonomy
wp tempest make:taxonomy ProductCategory --post-types=product

# Generate an ACF block
wp tempest make:acf-block Hero

# Generate a native block
wp tempest make:block Counter --interactive

# Generate a template controller
wp tempest make:controller single-product

# Generate a hooks class
wp tempest make:hooks Seo

# Generate a context provider
wp tempest make:context-provider Header

# Generate a context provider (alias for view composer)
wp tempest make:context GlobalContext --global
wp tempest make:context ProductContext --templates=single-product,archive-product

# Generate a block pattern
wp tempest make:pattern HeroWithCta

# Generate a shortcode
wp tempest make:shortcode Button

# Generate an ACF field group
wp tempest make:field-group ProductFields --post-type=product
wp tempest make:field-group PageFields --page-template=front-page
wp tempest make:field-group CategoryFields --taxonomy=category

# Generate an ACF options page
wp tempest make:options-page ThemeSettings
wp tempest make:options-page FooterSettings --parent=theme-settings

# Generate a navigation menu
wp tempest make:menu HeaderMenu --location=header
wp tempest make:menu FooterMenu --description="Footer Navigation"

# Generate an image size
wp tempest make:image-size CardImage --width=400 --height=300 --crop
wp tempest make:image-size HeroImage --width=1920 --height=0
```

### Global Options

All scaffolding commands support these options:

```bash
--force      # Overwrite existing files
--dry-run    # Preview what would be created without creating
```

Example with dry-run:

```bash
wp tempest make:model Product --post-type --dry-run
```

### Discovery Cache Commands

```bash
# Warm discovery cache (run discoveries + cache)
wp tempest discovery:warm

# Generate discovery cache for production
wp tempest discovery:generate

# Clear the discovery cache
wp tempest discovery:clear

# Check cache status
wp tempest discovery:status
```

See [Discovery Cache](/guide/discovery-cache) for more details on caching.

## Custom Commands

Create custom WP-CLI commands with `#[AsCliCommand]`:

```php
<?php
// app/Console/ImportProductsCommand.php

namespace App\Console;

use Studiometa\Foehn\Attributes\AsCliCommand;
use WP_CLI;

#[AsCliCommand(
    name: 'import:products',
    description: 'Import products from CSV file',
)]
final class ImportProductsCommand
{
    /**
     * Import products from a CSV file.
     *
     * ## OPTIONS
     *
     * <file>
     * : Path to the CSV file
     *
     * [--dry-run]
     * : Preview without importing
     *
     * ## EXAMPLES
     *
     *     wp tempest import:products products.csv
     *     wp tempest import:products products.csv --dry-run
     *
     * @param array $args Positional arguments
     * @param array $assocArgs Named arguments
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        $file = $args[0];
        $dryRun = isset($assocArgs['dry-run']);

        if (!file_exists($file)) {
            WP_CLI::error("File not found: {$file}");
        }

        $handle = fopen($file, 'r');
        $headers = fgetcsv($handle);
        $count = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($headers, $row);

            if ($dryRun) {
                WP_CLI::log("Would import: {$data['name']}");
            } else {
                $this->importProduct($data);
                WP_CLI::log("Imported: {$data['name']}");
            }

            $count++;
        }

        fclose($handle);

        WP_CLI::success("Processed {$count} products");
    }

    private function importProduct(array $data): int
    {
        $id = wp_insert_post([
            'post_type' => 'product',
            'post_title' => $data['name'],
            'post_content' => $data['description'] ?? '',
            'post_status' => 'publish',
        ]);

        if (isset($data['price'])) {
            update_post_meta($id, 'price', $data['price']);
        }

        return $id;
    }
}
```

**Usage:**

```bash
wp tempest import:products /path/to/products.csv
wp tempest import:products /path/to/products.csv --dry-run
```

## Command with Progress Bar

```php
<?php

namespace App\Console;

use Studiometa\Foehn\Attributes\AsCliCommand;
use WP_CLI;

#[AsCliCommand(
    name: 'images:optimize',
    description: 'Optimize all media library images',
)]
final class OptimizeImagesCommand
{
    public function __invoke(array $args, array $assocArgs): void
    {
        $attachments = get_posts([
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);

        $total = count($attachments);

        if ($total === 0) {
            WP_CLI::warning('No images found');
            return;
        }

        $progress = \WP_CLI\Utils\make_progress_bar('Optimizing images', $total);

        foreach ($attachments as $id) {
            $this->optimizeImage($id);
            $progress->tick();
        }

        $progress->finish();
        WP_CLI::success("Optimized {$total} images");
    }

    private function optimizeImage(int $id): void
    {
        // Optimization logic
        wp_update_attachment_metadata($id, wp_generate_attachment_metadata(
            $id,
            get_attached_file($id)
        ));
    }
}
```

## Command with Subcommands

For complex commands, use separate methods:

```php
<?php

namespace App\Console;

use Studiometa\Foehn\Attributes\AsCliCommand;
use WP_CLI;

#[AsCliCommand(
    name: 'cache',
    description: 'Manage application cache',
)]
final class CacheCommand
{
    /**
     * Clear all caches.
     *
     * ## EXAMPLES
     *
     *     wp tempest cache clear
     */
    public function clear(): void
    {
        wp_cache_flush();
        WP_CLI::success('Cache cleared');
    }

    /**
     * Show cache statistics.
     *
     * ## EXAMPLES
     *
     *     wp tempest cache stats
     */
    public function stats(): void
    {
        global $wp_object_cache;

        WP_CLI::log('Cache Statistics:');
        WP_CLI::log('  Hits: ' . ($wp_object_cache->cache_hits ?? 'N/A'));
        WP_CLI::log('  Misses: ' . ($wp_object_cache->cache_misses ?? 'N/A'));
    }

    /**
     * Warm up the cache.
     *
     * ## OPTIONS
     *
     * [--post-types=<types>]
     * : Comma-separated post types to warm
     *
     * ## EXAMPLES
     *
     *     wp tempest cache warm
     *     wp tempest cache warm --post-types=post,page,product
     */
    public function warm(array $args, array $assocArgs): void
    {
        $postTypes = isset($assocArgs['post-types'])
            ? explode(',', $assocArgs['post-types'])
            : ['post', 'page'];

        foreach ($postTypes as $type) {
            $posts = get_posts([
                'post_type' => $type,
                'posts_per_page' => -1,
                'fields' => 'ids',
            ]);

            foreach ($posts as $id) {
                get_post($id);
                get_post_meta($id);
            }

            WP_CLI::log("Warmed {$type}: " . count($posts) . ' posts');
        }

        WP_CLI::success('Cache warmed');
    }
}
```

**Usage:**

```bash
wp tempest cache clear
wp tempest cache stats
wp tempest cache warm --post-types=product
```

## Command with Tables

```php
#[AsCliCommand(
    name: 'products:list',
    description: 'List all products',
)]
final class ListProductsCommand
{
    public function __invoke(array $args, array $assocArgs): void
    {
        $products = get_posts([
            'post_type' => 'product',
            'posts_per_page' => -1,
        ]);

        if (empty($products)) {
            WP_CLI::warning('No products found');
            return;
        }

        $items = array_map(fn($p) => [
            'ID' => $p->ID,
            'Title' => $p->post_title,
            'Status' => $p->post_status,
            'Price' => get_post_meta($p->ID, 'price', true) ?: 'N/A',
        ], $products);

        WP_CLI\Utils\format_items(
            $assocArgs['format'] ?? 'table',
            $items,
            ['ID', 'Title', 'Status', 'Price']
        );
    }
}
```

## Dependency Injection

Commands support constructor injection:

```php
<?php

namespace App\Console;

use App\Services\ExportService;
use Studiometa\Foehn\Attributes\AsCliCommand;
use WP_CLI;

#[AsCliCommand(
    name: 'export:orders',
    description: 'Export orders to CSV',
)]
final class ExportOrdersCommand
{
    public function __construct(
        private readonly ExportService $export,
    ) {}

    public function __invoke(array $args, array $assocArgs): void
    {
        $file = $this->export->ordersToCSV();
        WP_CLI::success("Exported to: {$file}");
    }
}
```

## Long Description

Provide detailed help with `longDescription`:

```php
#[AsCliCommand(
    name: 'sync:inventory',
    description: 'Sync inventory from external API',
    longDescription: <<<'DOC'
## DESCRIPTION

Synchronizes product inventory levels from the external inventory
management system.

## OPTIONS

[--force]
: Force sync even if recently updated

[--products=<ids>]
: Comma-separated product IDs to sync

## EXAMPLES

    # Sync all products
    wp tempest sync:inventory

    # Force sync specific products
    wp tempest sync:inventory --products=123,456 --force

## NOTES

This command requires API credentials in wp-config.php:
- INVENTORY_API_KEY
- INVENTORY_API_SECRET
DOC,
)]
final class SyncInventoryCommand {}
```

## Attribute Parameters

| Parameter         | Type      | Default    | Description              |
| ----------------- | --------- | ---------- | ------------------------ |
| `name`            | `string`  | _required_ | Command name             |
| `description`     | `string`  | _required_ | Short description        |
| `longDescription` | `?string` | `null`     | Detailed help (docblock) |

## See Also

- [API Reference: #[AsCliCommand]](/api/as-cli-command)
- [WP-CLI Commands Cookbook](https://make.wordpress.org/cli/handbook/guides/commands-cookbook/)
