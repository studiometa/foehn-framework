# Iconify Installer Design

## Overview

A Composer plugin that extracts icon references from templates at build time,
fetches them from the Iconify API, and saves them locally as SVG files.

**Goal:** Access to 150,000+ icons without the 690MB `iconify/json` dependency.

## Package Structure

```
studiometa/iconify-installer/
├── src/
│   ├── Plugin.php              # Composer plugin entry point
│   ├── Config.php              # Configuration from composer.json
│   ├── Scanner/
│   │   ├── IconScanner.php     # Main scanner orchestrator
│   │   ├── TwigScanner.php     # Scans .twig files
│   │   └── PhpScanner.php      # Scans .php files
│   ├── Fetcher/
│   │   ├── IconifyClient.php   # HTTP client for Iconify API
│   │   └── BatchFetcher.php    # Batches API requests
│   ├── Storage/
│   │   ├── IconStorage.php     # Saves SVG files
│   │   └── Manifest.php        # Manages manifest.json
│   └── Command/
│       └── ScanCommand.php     # Manual CLI command
├── composer.json
└── README.md
```

## Configuration Schema

```json
{
    "extra": {
        "iconify": {
            "enabled": true,
            "output": "assets/icons",
            "manifest": "assets/icons/manifest.json",
            "scan": [
                "templates/**/*.twig",
                "app/**/*.php"
            ],
            "functions": [
                "icon",
                "meta_icon", 
                "iconify"
            ],
            "include": [],
            "exclude": []
        }
    }
}
```

## Core Classes

### Plugin.php

```php
<?php

declare(strict_types=1);

namespace Studiometa\IconifyInstaller;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

final class Plugin implements PluginInterface, EventSubscriberInterface, Capable
{
    private Composer $composer;
    private IOInterface $io;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    public function deactivate(Composer $composer, IOInterface $io): void {}
    public function uninstall(Composer $composer, IOInterface $io): void {}

    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => ['onPostInstall', -10],
            ScriptEvents::POST_UPDATE_CMD => ['onPostUpdate', -10],
        ];
    }

    public function getCapabilities(): array
    {
        return [
            'Composer\Plugin\Capability\CommandProvider' => CommandProvider::class,
        ];
    }

    public function onPostInstall(Event $event): void
    {
        $this->syncIcons();
    }

    public function onPostUpdate(Event $event): void
    {
        $this->syncIcons();
    }

    private function syncIcons(): void
    {
        $config = Config::fromComposer($this->composer);

        if (!$config->enabled) {
            return;
        }

        $this->io->write('<info>Iconify:</info> Scanning templates for icons...');

        $scanner = new Scanner\IconScanner($config);
        $found = $scanner->scan();

        $this->io->write(sprintf('<info>Iconify:</info> Found %d icon references', count($found)));

        $manifest = new Storage\Manifest($config->manifestPath);
        $existing = $manifest->getIcons();

        $toFetch = array_diff($found, $existing);
        $toRemove = array_diff($existing, $found);

        if (empty($toFetch) && empty($toRemove)) {
            $this->io->write('<info>Iconify:</info> Icons up to date');
            return;
        }

        if (!empty($toFetch)) {
            $this->io->write(sprintf('<info>Iconify:</info> Fetching %d new icons...', count($toFetch)));
            
            $fetcher = new Fetcher\BatchFetcher();
            $storage = new Storage\IconStorage($config->outputPath);

            foreach ($fetcher->fetch($toFetch) as $icon => $svg) {
                $storage->save($icon, $svg);
                $manifest->add($icon);
            }
        }

        if (!empty($toRemove) && $config->pruneUnused) {
            $this->io->write(sprintf('<info>Iconify:</info> Removing %d unused icons...', count($toRemove)));
            
            $storage = new Storage\IconStorage($config->outputPath);
            foreach ($toRemove as $icon) {
                $storage->remove($icon);
                $manifest->remove($icon);
            }
        }

        $manifest->save();

        $this->io->write('<info>Iconify:</info> Done!');
    }
}
```

### Scanner/IconScanner.php

```php
<?php

declare(strict_types=1);

namespace Studiometa\IconifyInstaller\Scanner;

use Studiometa\IconifyInstaller\Config;
use Symfony\Component\Finder\Finder;

final class IconScanner
{
    /** @var list<string> */
    private array $patterns;

    public function __construct(
        private readonly Config $config,
    ) {
        // Build regex patterns for each configured function
        $this->patterns = array_map(
            fn(string $fn) => sprintf(
                '/%s\s*\(\s*[\'"]([a-z0-9-]+:[a-z0-9-]+)[\'"]/i',
                preg_quote($fn, '/')
            ),
            $config->functions
        );
    }

    /**
     * Scan all configured paths and return unique icon references.
     *
     * @return list<string> Icon references (e.g., ['mdi:home', 'heroicons:arrow-right'])
     */
    public function scan(): array
    {
        $icons = [];

        $finder = new Finder();
        $finder->files()
            ->in($this->config->scanPaths)
            ->name(['*.twig', '*.php', '*.html'])
            ->notPath(['vendor', 'node_modules', 'cache']);

        foreach ($finder as $file) {
            $content = $file->getContents();
            $icons = [...$icons, ...$this->extractIcons($content)];
        }

        // Add manually included icons
        $icons = [...$icons, ...$this->config->include];

        // Remove excluded icons
        $icons = array_filter($icons, fn(string $icon) => !$this->isExcluded($icon));

        // Unique and sorted
        $icons = array_unique($icons);
        sort($icons);

        return array_values($icons);
    }

    /**
     * Extract icon references from content.
     *
     * @return list<string>
     */
    private function extractIcons(string $content): array
    {
        $icons = [];

        foreach ($this->patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                $icons = [...$icons, ...$matches[1]];
            }
        }

        return $icons;
    }

    private function isExcluded(string $icon): bool
    {
        foreach ($this->config->exclude as $pattern) {
            if (str_contains($pattern, '*')) {
                $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/';
                if (preg_match($regex, $icon)) {
                    return true;
                }
            } elseif ($pattern === $icon) {
                return true;
            }
        }

        return false;
    }
}
```

### Fetcher/BatchFetcher.php

```php
<?php

declare(strict_types=1);

namespace Studiometa\IconifyInstaller\Fetcher;

final class BatchFetcher
{
    private const API_ENDPOINT = 'https://api.iconify.design';
    private const BATCH_SIZE = 50; // Max icons per request

    /**
     * Fetch icons from Iconify API.
     *
     * @param list<string> $icons Icon references (e.g., ['mdi:home', 'mdi:menu'])
     * @return \Generator<string, string> Yields icon => svg pairs
     */
    public function fetch(array $icons): \Generator
    {
        // Group icons by prefix
        $grouped = [];
        foreach ($icons as $icon) {
            [$prefix, $name] = explode(':', $icon);
            $grouped[$prefix][] = $name;
        }

        // Fetch each prefix in batches
        foreach ($grouped as $prefix => $names) {
            foreach (array_chunk($names, self::BATCH_SIZE) as $batch) {
                yield from $this->fetchBatch($prefix, $batch);
            }
        }
    }

    /**
     * @return \Generator<string, string>
     */
    private function fetchBatch(string $prefix, array $names): \Generator
    {
        $url = sprintf(
            '%s/%s.json?icons=%s',
            self::API_ENDPOINT,
            $prefix,
            implode(',', $names)
        );

        $response = file_get_contents($url);
        if ($response === false) {
            return;
        }

        $data = json_decode($response, true);
        if (!isset($data['icons'])) {
            return;
        }

        $width = $data['width'] ?? 24;
        $height = $data['height'] ?? 24;

        foreach ($data['icons'] as $name => $iconData) {
            $iconWidth = $iconData['width'] ?? $width;
            $iconHeight = $iconData['height'] ?? $height;

            $svg = sprintf(
                '<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 %d %d">%s</svg>',
                $iconWidth,
                $iconHeight,
                $iconData['body']
            );

            yield "{$prefix}:{$name}" => $svg;
        }
    }
}
```

### Storage/IconStorage.php

```php
<?php

declare(strict_types=1);

namespace Studiometa\IconifyInstaller\Storage;

final class IconStorage
{
    public function __construct(
        private readonly string $basePath,
    ) {}

    /**
     * Save an icon SVG to disk.
     *
     * @param string $icon Icon reference (e.g., 'mdi:home')
     * @param string $svg SVG content
     */
    public function save(string $icon, string $svg): void
    {
        $path = $this->getPath($icon);
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, $svg);
    }

    /**
     * Remove an icon SVG from disk.
     */
    public function remove(string $icon): void
    {
        $path = $this->getPath($icon);
        if (file_exists($path)) {
            unlink($path);
        }

        // Clean up empty directories
        $dir = dirname($path);
        if (is_dir($dir) && count(scandir($dir)) === 2) {
            rmdir($dir);
        }
    }

    /**
     * Check if an icon exists locally.
     */
    public function exists(string $icon): bool
    {
        return file_exists($this->getPath($icon));
    }

    /**
     * Get the file path for an icon.
     */
    public function getPath(string $icon): string
    {
        [$prefix, $name] = explode(':', $icon);
        return sprintf('%s/%s/%s.svg', $this->basePath, $prefix, $name);
    }
}
```

### Storage/Manifest.php

```php
<?php

declare(strict_types=1);

namespace Studiometa\IconifyInstaller\Storage;

final class Manifest
{
    /** @var array{icons: list<string>, updated: string} */
    private array $data;

    public function __construct(
        private readonly string $path,
    ) {
        $this->load();
    }

    private function load(): void
    {
        if (file_exists($this->path)) {
            $this->data = json_decode(file_get_contents($this->path), true);
        } else {
            $this->data = ['icons' => [], 'updated' => ''];
        }
    }

    /**
     * @return list<string>
     */
    public function getIcons(): array
    {
        return $this->data['icons'];
    }

    public function add(string $icon): void
    {
        if (!in_array($icon, $this->data['icons'], true)) {
            $this->data['icons'][] = $icon;
            sort($this->data['icons']);
        }
    }

    public function remove(string $icon): void
    {
        $this->data['icons'] = array_values(
            array_filter($this->data['icons'], fn(string $i) => $i !== $icon)
        );
    }

    public function save(): void
    {
        $this->data['updated'] = date('c');

        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $this->path,
            json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }
}
```

## CLI Command

```bash
# Manual sync
composer iconify:sync

# Scan and show what would be fetched (dry-run)
composer iconify:scan

# Add specific icons
composer iconify:add mdi:home heroicons:arrow-right

# Remove unused icons
composer iconify:prune
```

## Twig Extension (for Foehn)

```php
<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Views\Twig;

use Studiometa\Foehn\Attributes\AsTwigExtension;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Icon extension that reads from locally installed icons.
 * 
 * Icons are fetched at build time by studiometa/iconify-installer.
 */
#[AsTwigExtension]
final class IconExtension extends AbstractExtension
{
    public function __construct(
        private readonly string $iconPath,
        private readonly bool $allowMissing = false,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('icon', $this->renderIcon(...), ['is_safe' => ['html']]),
        ];
    }

    /**
     * Render an icon.
     *
     * @param string $name Icon name (e.g., 'mdi:home' or 'arrow-right' for local)
     * @param array<string, mixed> $attrs HTML attributes to add to SVG
     */
    public function renderIcon(string $name, array $attrs = []): string
    {
        $path = $this->resolvePath($name);

        if (!file_exists($path)) {
            if ($this->allowMissing) {
                return '';
            }
            throw new \RuntimeException(sprintf(
                'Icon "%s" not found. Run "composer iconify:sync" to fetch missing icons.',
                $name
            ));
        }

        $svg = file_get_contents($path);

        if (!empty($attrs)) {
            $svg = $this->injectAttributes($svg, $attrs);
        }

        return $svg;
    }

    private function resolvePath(string $name): string
    {
        if (str_contains($name, ':')) {
            // Iconify format: mdi:home -> icons/mdi/home.svg
            [$prefix, $icon] = explode(':', $name);
            return sprintf('%s/%s/%s.svg', $this->iconPath, $prefix, $icon);
        }

        // Local format: arrow-right -> icons/arrow-right.svg
        return sprintf('%s/%s.svg', $this->iconPath, $name);
    }

    private function injectAttributes(string $svg, array $attrs): string
    {
        $attrString = '';
        foreach ($attrs as $key => $value) {
            $key = str_replace('_', '-', $key);
            $attrString .= sprintf(' %s="%s"', $key, htmlspecialchars((string) $value));
        }

        return preg_replace('/<svg/', '<svg' . $attrString, $svg, 1);
    }
}
```

## Usage in Templates

```twig
{# Basic usage #}
{{ icon('mdi:home') }}

{# With attributes #}
{{ icon('heroicons:arrow-right', { class: 'w-6 h-6', aria_hidden: 'true' }) }}

{# Local icons (no prefix) #}
{{ icon('logo') }}
```

## Workflow

### Development

1. Add icon to template: `{{ icon('mdi:new-icon') }}`
2. Run `composer iconify:sync` (or let it run on `composer install`)
3. Icon is fetched and saved locally
4. Commit the icon to version control

### CI/CD

```yaml
# .gitlab-ci.yml
build:
  script:
    - composer install
    # Icons are automatically synced
    - composer iconify:sync --dry-run  # Verify no missing icons
```

### Production

- All icons are already in the repository
- No network requests needed
- No runtime fetching

## Comparison

| Approach | Vendor Size | Network | Build Step |
|----------|-------------|---------|------------|
| `iconify/json` | 690MB | No | No |
| Runtime API | 0 | Yes (cached) | No |
| **iconify-installer** | ~5KB + icons | Build only | Yes |

## Benefits

1. **Small footprint**: Only icons you use
2. **No runtime network**: Everything local
3. **Version controlled**: Icons in git
4. **Automatic sync**: On composer install/update
5. **CI verification**: Catch missing icons in CI
6. **Compatible with studiometa/ui**: Just swap the function

## Integration with studiometa/ui

The `studiometa/ui` package uses `meta_icon()` function. To use iconify-installer:

1. Configure the function name:
```json
{
    "extra": {
        "iconify": {
            "functions": ["icon", "meta_icon"]
        }
    }
}
```

2. Override the `meta_icon` function in Foehn to read from local files instead of using `iconify/json`.

## Future Enhancements

1. **Watch mode**: Auto-sync on template change during development
2. **Icon preview**: Generate HTML preview of all used icons
3. **Optimization**: SVGO integration for smaller files
4. **Fallback**: CDN fallback for missing icons in development
5. **Analytics**: Report on icon usage across projects
