# #[AsBlockPattern]

Register a class as a WordPress block pattern.

## Signature

```php
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsBlockPattern
{
    public function __construct(
        public string $name,
        public string $title,
        public array $categories = [],
        public array $keywords = [],
        public array $blockTypes = [],
        public ?string $description = null,
        public ?string $template = null,
        public int $viewportWidth = 1200,
        public bool $inserter = true,
    ) {}

    public function getTemplatePath(): string {}
}
```

## Parameters

| Parameter       | Type       | Default       | Description                 |
| --------------- | ---------- | ------------- | --------------------------- |
| `name`          | `string`   | —             | Pattern name with namespace |
| `title`         | `string`   | —             | Display title (required)    |
| `categories`    | `string[]` | `[]`          | Pattern categories          |
| `keywords`      | `string[]` | `[]`          | Search keywords             |
| `blockTypes`    | `string[]` | `[]`          | Associated block types      |
| `description`   | `?string`  | `null`        | Pattern description         |
| `template`      | `?string`  | Auto-resolved | Template path               |
| `viewportWidth` | `int`      | `1200`        | Preview viewport width      |
| `inserter`      | `bool`     | `true`        | Show in block inserter      |

## Usage

### Basic Pattern

```php
<?php

namespace App\Patterns;

use Studiometa\Foehn\Attributes\AsBlockPattern;

#[AsBlockPattern(
    name: 'theme/hero-with-cta',
    title: 'Hero with CTA',
    categories: ['featured'],
)]
final class HeroWithCta {}
```

Template at `patterns/hero-with-cta.twig`:

```twig
<!-- wp:cover {"dimRatio":50} -->
<div class="wp-block-cover">
    <div class="wp-block-cover__inner-container">
        <!-- wp:heading {"level":1} -->
        <h1>Welcome</h1>
        <!-- /wp:heading -->
    </div>
</div>
<!-- /wp:cover -->
```

### With Dynamic Content

Implement `BlockPatternInterface`:

```php
<?php

namespace App\Patterns;

use Studiometa\Foehn\Attributes\AsBlockPattern;
use Studiometa\Foehn\Contracts\BlockPatternInterface;

#[AsBlockPattern(
    name: 'theme/latest-posts',
    title: 'Latest Posts',
    categories: ['posts'],
)]
final class LatestPosts implements BlockPatternInterface
{
    public function context(): array
    {
        return [
            'posts' => \Timber\Timber::get_posts([
                'posts_per_page' => 3,
            ]),
        ];
    }
}
```

### Full Configuration

```php
#[AsBlockPattern(
    name: 'theme/pricing-table',
    title: 'Pricing Table',
    categories: ['featured', 'pricing'],
    keywords: ['price', 'plans'],
    blockTypes: ['core/group'],
    description: 'A pricing comparison table',
    template: 'patterns/pricing',
    viewportWidth: 1400,
    inserter: true,
)]
```

## Template Resolution

Default: `theme/hero-section` → `patterns/hero-section.twig`

Custom with `template` parameter.

## Related

- [Guide: Block Patterns](/guide/block-patterns)
- [`BlockPatternInterface`](./block-pattern-interface)
