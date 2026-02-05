# #[AsTwigExtension]

Register a class as a Twig extension for Timber templates.

## Signature

```php
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsTwigExtension
{
    public function __construct(
        public int $priority = 10,
    ) {}
}
```

## Parameters

| Parameter  | Type  | Default | Description                                |
| ---------- | ----- | ------- | ------------------------------------------ |
| `priority` | `int` | `10`    | Loading priority (lower values load first) |

## Requirements

The class must extend `Twig\Extension\AbstractExtension`.

## Usage

### Basic Usage

```php
<?php

namespace App\Twig;

use Studiometa\Foehn\Attributes\AsTwigExtension;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

#[AsTwigExtension]
final class MyExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('hello', fn(string $name) => "Hello, {$name}!"),
        ];
    }
}
```

Then use it in your templates:

```twig
{{ hello('World') }} {# Output: Hello, World! #}
```

### Adding Filters

```php
<?php

namespace App\Twig;

use Studiometa\Foehn\Attributes\AsTwigExtension;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

#[AsTwigExtension]
final class TextExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('excerpt', [$this, 'excerpt']),
            new TwigFilter('reading_time', [$this, 'readingTime']),
        ];
    }

    public function excerpt(string $text, int $length = 150): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length) . '…';
    }

    public function readingTime(string $text): int
    {
        $wordCount = str_word_count(strip_tags($text));

        return max(1, (int) ceil($wordCount / 200));
    }
}
```

Usage:

```twig
<p>{{ post.content | excerpt(200) }}</p>
<span>{{ post.content | reading_time }} min read</span>
```

### With Priority

Control the order extensions are loaded:

```php
#[AsTwigExtension(priority: 5)]
final class CoreExtension extends AbstractExtension
{
    // Loads before extensions with default priority (10)
}

#[AsTwigExtension(priority: 20)]
final class OverrideExtension extends AbstractExtension
{
    // Loads after default priority extensions
}
```

### With Dependency Injection

Extensions are resolved through the container, so you can inject dependencies:

```php
<?php

namespace App\Twig;

use App\Services\PriceFormatter;
use Studiometa\Foehn\Attributes\AsTwigExtension;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

#[AsTwigExtension]
final class PriceExtension extends AbstractExtension
{
    public function __construct(
        private readonly PriceFormatter $formatter,
    ) {}

    public function getFilters(): array
    {
        return [
            new TwigFilter('price', [$this->formatter, 'format']),
        ];
    }
}
```

## Built-in Extensions

Foehn provides built-in extensions that you can use as examples:

- `InteractivityExtension` — Helpers for WordPress Interactivity API
- `VideoEmbedExtension` — Video URL transformation utilities

## Related

- [Guide: Twig Extensions](/guide/twig-extensions)
- [Twig Documentation: Extending Twig](https://twig.symfony.com/doc/3.x/advanced.html)
