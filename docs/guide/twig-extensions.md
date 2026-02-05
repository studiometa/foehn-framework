# Twig Extensions

Foehn uses Timber which provides Twig as its templating engine. You can extend Twig with custom functions, filters, and more using the `#[AsTwigExtension]` attribute.

## Creating an Extension

Create a class that extends `Twig\Extension\AbstractExtension` and mark it with `#[AsTwigExtension]`:

```php
<?php

namespace App\Twig;

use Studiometa\Foehn\Attributes\AsTwigExtension;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Twig\TwigFilter;

#[AsTwigExtension]
final class MyExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('hello', fn(string $name) => "Hello, {$name}!"),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('reverse', fn(string $text) => strrev($text)),
        ];
    }
}
```

Use in templates:

```twig
{{ hello('World') }}        {# Hello, World! #}
{{ 'Foehn' | reverse }}     {# nheOf #}
```

## Adding Functions

Functions are called with `{{ function_name(args) }}`:

```php
public function getFunctions(): array
{
    return [
        new TwigFunction('icon', [$this, 'renderIcon'], ['is_safe' => ['html']]),
        new TwigFunction('site_url', fn() => home_url()),
        new TwigFunction('current_year', fn() => date('Y')),
    ];
}

public function renderIcon(string $name, string $class = ''): string
{
    return sprintf(
        '<svg class="icon icon-%s %s"><use href="#icon-%s"></use></svg>',
        esc_attr($name),
        esc_attr($class),
        esc_attr($name)
    );
}
```

::: tip is_safe option
Use `'is_safe' => ['html']` when your function returns HTML that should not be escaped.
:::

## Adding Filters

Filters transform values with `{{ value | filter_name }}`:

```php
public function getFilters(): array
{
    return [
        new TwigFilter('excerpt', [$this, 'excerpt']),
        new TwigFilter('phone_link', [$this, 'phoneLink'], ['is_safe' => ['html']]),
        new TwigFilter('reading_time', [$this, 'readingTime']),
    ];
}

public function excerpt(string $text, int $length = 150): string
{
    $text = strip_tags($text);

    if (mb_strlen($text) <= $length) {
        return $text;
    }

    return mb_substr($text, 0, $length) . '…';
}

public function phoneLink(string $phone): string
{
    $clean = preg_replace('/[^0-9+]/', '', $phone);

    return sprintf('<a href="tel:%s">%s</a>', $clean, $phone);
}

public function readingTime(string $content): int
{
    $wordCount = str_word_count(strip_tags($content));

    return max(1, (int) ceil($wordCount / 200));
}
```

Usage:

```twig
<p>{{ post.content | excerpt(200) }}</p>
<p>{{ '+33 1 23 45 67 89' | phone_link }}</p>
<span>{{ post.content | reading_time }} min read</span>
```

## Dependency Injection

Extensions are resolved through the DI container:

```php
<?php

namespace App\Twig;

use App\Services\PriceFormatter;
use App\Services\ImageService;
use Studiometa\Foehn\Attributes\AsTwigExtension;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

#[AsTwigExtension]
final class ShopExtension extends AbstractExtension
{
    public function __construct(
        private readonly PriceFormatter $priceFormatter,
        private readonly ImageService $imageService,
    ) {}

    public function getFilters(): array
    {
        return [
            new TwigFilter('price', [$this->priceFormatter, 'format']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('optimized_image', [$this->imageService, 'getOptimized']),
        ];
    }
}
```

## Priority

Control the order extensions are loaded with the `priority` parameter:

```php
#[AsTwigExtension(priority: 5)]
final class CoreExtension extends AbstractExtension
{
    // Loads first (lower priority = earlier)
}

#[AsTwigExtension(priority: 20)]
final class OverrideExtension extends AbstractExtension
{
    // Loads later, can override earlier definitions
}
```

## Project Structure

Organize your extensions by domain:

```
app/
└── Twig/
    ├── CoreExtension.php       # Basic utilities (icons, dates, etc.)
    ├── TextExtension.php       # Text manipulation filters
    ├── MediaExtension.php      # Image/video helpers
    └── ShopExtension.php       # E-commerce specific
```

## Built-in Extensions

Foehn includes useful extensions out of the box:

### InteractivityExtension

Helpers for WordPress Interactivity API:

```twig
<div
    {{ wp_interactive('theme/counter') }}
    {{ wp_context({ count: 0 }) }}
>
    <span {{ wp_text('context.count') }}>0</span>
    <button {{ wp_on('click', 'actions.increment') }}>+</button>
</div>
```

Available functions:

| Function         | Description                               |
| ---------------- | ----------------------------------------- |
| `wp_interactive` | Generate `data-wp-interactive` attribute  |
| `wp_context`     | Generate `data-wp-context` with JSON      |
| `wp_directive`   | Generate any `data-wp-*` directive        |
| `wp_bind`        | Generate `data-wp-bind--{attr}` directive |
| `wp_on`          | Generate `data-wp-on--{event}` directive  |
| `wp_class`       | Generate `data-wp-class--{class}`         |
| `wp_text`        | Generate `data-wp-text` directive         |

### VideoEmbedExtension

Video URL utilities:

```twig
{# Convert watch URL to embed URL #}
{{ 'https://youtube.com/watch?v=abc123' | video_embed }}
{# https://www.youtube.com/embed/abc123 #}

{# Extract video ID #}
{{ 'https://vimeo.com/123456789' | video_id }}
{# 123456789 #}

{# Detect platform #}
{{ 'https://youtube.com/watch?v=abc' | video_platform }}
{# youtube #}

{# Check if URL is supported #}
{% if url | video_is_supported %}
    <iframe src="{{ url | video_embed }}"></iframe>
{% endif %}
```

## Complete Example

```php
<?php

namespace App\Twig;

use Studiometa\Foehn\Attributes\AsTwigExtension;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

#[AsTwigExtension]
final class ThemeExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            // SVG icon helper
            new TwigFunction('icon', [$this, 'icon'], ['is_safe' => ['html']]),

            // Asset URL helper
            new TwigFunction('asset', [$this, 'asset']),

            // Check if current page
            new TwigFunction('is_current', [$this, 'isCurrent']),
        ];
    }

    public function getFilters(): array
    {
        return [
            // Format phone numbers
            new TwigFilter('phone', [$this, 'formatPhone']),

            // Truncate text
            new TwigFilter('truncate', [$this, 'truncate']),

            // Format file size
            new TwigFilter('filesize', [$this, 'formatFilesize']),
        ];
    }

    public function icon(string $name, array $attrs = []): string
    {
        $class = 'icon icon-' . $name;

        if (isset($attrs['class'])) {
            $class .= ' ' . $attrs['class'];
            unset($attrs['class']);
        }

        $attrStr = '';

        foreach ($attrs as $key => $value) {
            $attrStr .= sprintf(' %s="%s"', $key, esc_attr($value));
        }

        return sprintf(
            '<svg class="%s"%s><use href="%s/dist/icons.svg#%s"></use></svg>',
            esc_attr($class),
            $attrStr,
            get_template_directory_uri(),
            esc_attr($name)
        );
    }

    public function asset(string $path): string
    {
        return get_template_directory_uri() . '/dist/' . ltrim($path, '/');
    }

    public function isCurrent(string $url): bool
    {
        $current = trailingslashit($_SERVER['REQUEST_URI'] ?? '');
        $check = trailingslashit(wp_parse_url($url, PHP_URL_PATH) ?? '');

        return $current === $check;
    }

    public function formatPhone(string $phone): string
    {
        // Format French phone: 0612345678 → 06 12 34 56 78
        $clean = preg_replace('/[^0-9]/', '', $phone);

        if (strlen($clean) === 10) {
            return implode(' ', str_split($clean, 2));
        }

        return $phone;
    }

    public function truncate(string $text, int $length = 100, string $suffix = '…'): string
    {
        $text = strip_tags($text);

        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length) . $suffix;
    }

    public function formatFilesize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 1) . ' ' . $units[$i];
    }
}
```

Usage in templates:

```twig
{# Icons #}
{{ icon('arrow-right') }}
{{ icon('menu', { class: 'w-6 h-6', 'aria-hidden': 'true' }) }}

{# Assets #}
<img src="{{ asset('images/logo.svg') }}" alt="Logo">

{# Navigation #}
<a href="/" class="{{ is_current('/') ? 'active' : '' }}">Home</a>

{# Filters #}
<a href="tel:{{ phone | phone }}">{{ phone | phone }}</a>
<p>{{ post.content | truncate(200) }}</p>
<span>{{ attachment.filesize | filesize }}</span>
```

## See Also

- [API Reference: #[AsTwigExtension]](/api/as-twig-extension)
- [Timber Documentation](https://timber.github.io/docs/v2/)
- [Twig Documentation](https://twig.symfony.com/doc/3.x/)
