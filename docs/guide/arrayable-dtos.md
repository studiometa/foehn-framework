# Arrayable DTOs

Føhn supports typed DTOs (Data Transfer Objects) as an alternative to plain arrays for block and pattern `compose()` methods. DTOs provide autocompletion, type safety, and clear contracts for your template context.

## Overview

Instead of returning a plain `array` from `compose()`, you can return an `Arrayable` object. Føhn automatically flattens it to a snake_case array before reaching `render()` and Twig templates.

**Before (plain array):**

```php
public function compose(array $block, array $fields): array
{
    return [
        'title' => $fields['title'] ?? '',
        'background_image' => ImageData::fromAttachmentId($fields['background'] ?? null),
        'cta_link' => LinkData::fromAcf($fields['cta'] ?? null),
    ];
}
```

**After (typed DTO):**

```php
public function compose(array $block, array $fields): HeroContext
{
    return new HeroContext(
        title: $fields['title'] ?? '',
        backgroundImage: ImageData::fromAttachmentId($fields['background'] ?? null),
        ctaLink: LinkData::fromAcf($fields['cta'] ?? null),
    );
}
```

Both approaches produce the same template context (`title`, `background_image`, `cta_link`).

## Creating a DTO

Implement `Arrayable` and use the `HasToArray` trait:

```php
<?php

namespace App\Data;

use Studiometa\Foehn\Concerns\HasToArray;
use Studiometa\Foehn\Contracts\Arrayable;
use Studiometa\Foehn\Data\ImageData;
use Studiometa\Foehn\Data\LinkData;

final readonly class HeroContext implements Arrayable
{
    use HasToArray;

    public function __construct(
        public string $title,
        public ?ImageData $backgroundImage = null,
        public ?LinkData $ctaLink = null,
        public string $height = 'medium',
    ) {}
}
```

### How HasToArray Works

The `HasToArray` trait uses reflection to:

1. Read all **public instance properties**
2. Convert **camelCase** property names to **snake_case** keys
3. Recursively flatten nested `Arrayable` objects
4. Skip uninitialized and static properties

```php
$context = new HeroContext(
    title: 'Welcome',
    backgroundImage: new ImageData(id: 1, src: '/img.jpg'),
    ctaLink: new LinkData(url: '/about', title: 'Learn more'),
);

$context->toArray();
// [
//     'title' => 'Welcome',
//     'background_image' => ['id' => 1, 'src' => '/img.jpg', 'alt' => '', 'width' => null, 'height' => null],
//     'cta_link' => ['url' => '/about', 'title' => 'Learn more', 'target' => ''],
//     'height' => 'medium',
// ]
```

### Customizing Key Mapping

Override `propertyToKey()` to change the mapping strategy:

```php
final readonly class MyContext implements Arrayable
{
    use HasToArray;

    // Keep camelCase keys instead of snake_case
    protected function propertyToKey(string $name): string
    {
        return $name;
    }
}
```

## Using in Blocks

All `compose()` methods on `AcfBlockInterface`, `BlockInterface`, and `BlockPatternInterface` accept either `array` or `Arrayable` as return type.

### ACF Block

```php
use Studiometa\Foehn\Attributes\AsAcfBlock;
use Studiometa\Foehn\Contracts\AcfBlockInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use StoutLogic\AcfBuilder\FieldsBuilder;

#[AsAcfBlock(name: 'hero', title: 'Hero Banner')]
final readonly class HeroBlock implements AcfBlockInterface
{
    public function __construct(
        private ViewEngineInterface $view,
    ) {}

    public static function fields(): FieldsBuilder
    {
        return (new FieldsBuilder('hero'))
            ->addText('title')
            ->addImage('background')
            ->addLink('cta');
    }

    public function compose(array $block, array $fields): HeroContext
    {
        return new HeroContext(
            title: $fields['title'] ?? '',
            backgroundImage: ImageData::fromAttachmentId($fields['background'] ?? null),
            ctaLink: LinkData::fromAcf($fields['cta'] ?? null),
        );
    }

    public function render(array $context, bool $isPreview = false): string
    {
        return $this->view->render('blocks/hero', $context);
    }
}
```

### Block Pattern

```php
use Studiometa\Foehn\Attributes\AsBlockPattern;
use Studiometa\Foehn\Contracts\BlockPatternInterface;

#[AsBlockPattern(name: 'theme/featured', title: 'Featured Section')]
final class FeaturedPattern implements BlockPatternInterface
{
    public function context(): FeaturedContext
    {
        return new FeaturedContext(
            posts: \Timber\Timber::get_posts(['posts_per_page' => 3]),
        );
    }
}
```

## Built-in DTOs

Føhn provides DTOs for common ACF field patterns:

### LinkData

Matches ACF link fields (return_format: array).

```php
use Studiometa\Foehn\Data\LinkData;

// From ACF link field
$link = LinkData::fromAcf($fields['cta']);
// → LinkData { url: '...', title: '...', target: '' }

// Manual construction
$link = new LinkData(url: '/about', title: 'About Us', target: '_blank');

// Access properties
$link->url;    // string
$link->title;  // string
$link->target; // string

// Convert to array
$link->toArray();
// ['url' => '/about', 'title' => 'About Us', 'target' => '_blank']
```

Returns `null` if the ACF field is empty or null.

### ImageData

Matches ACF image fields (return_format: id).

```php
use Studiometa\Foehn\Data\ImageData;

// From WordPress attachment ID
$image = ImageData::fromAttachmentId($fields['background'], 'large');
// → ImageData { id: 42, src: 'https://...', alt: '...', width: 1920, height: 1080 }

// Manual construction
$image = new ImageData(id: 42, src: '/img.jpg', alt: 'Photo', width: 800, height: 600);

// Access properties
$image->id;     // int
$image->src;    // string
$image->alt;    // string
$image->width;  // ?int
$image->height; // ?int
```

Returns `null` if the ID is invalid or the attachment doesn't exist.

### SpacingData

Matches fields produced by `SpacingBuilder`.

```php
use Studiometa\Foehn\Data\SpacingData;

// From ACF fields
$spacing = SpacingData::fromAcf($fields, 'spacing');
// → SpacingData { top: 'large', bottom: 'medium' }

// Manual construction
$spacing = new SpacingData(top: 'large', bottom: 'small');

// Access properties
$spacing->top;    // string (default: 'medium')
$spacing->bottom; // string (default: 'medium')
```

## In Twig Templates

DTO properties are available as snake_case keys:

```twig
{% verbatim %}{# blocks/hero.twig #}
<section class="hero hero--{{ height }}">
    {% if background_image %}
        <img
            src="{{ background_image.src }}"
            alt="{{ background_image.alt }}"
            {% if background_image.width %}width="{{ background_image.width }}"{% endif %}
            {% if background_image.height %}height="{{ background_image.height }}"{% endif %}
        >
    {% endif %}

    <h1>{{ title }}</h1>

    {% if cta_link %}
        <a href="{{ cta_link.url }}" target="{{ cta_link.target }}">
            {{ cta_link.title }}
        </a>
    {% endif %}
</section>{% endverbatim %}
```

## Related

- [ACF Blocks](/guide/acf-blocks) — Using DTOs in ACF blocks
- [Block Patterns](/guide/block-patterns) — Using DTOs in block patterns
- [Field Fragments](/guide/field-fragments) — ACF field builder helpers
