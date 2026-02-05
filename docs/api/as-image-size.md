# #[AsImageSize]

Register a custom WordPress image size.

## Signature

```php
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsImageSize
{
    public function __construct(
        public int $width,
        public int $height = 0,
        public bool $crop = false,
        public ?string $name = null,
    ) {}
}
```

## Parameters

| Parameter | Type      | Default | Description                                           |
| --------- | --------- | ------- | ----------------------------------------------------- |
| `width`   | `int`     | —       | Image width in pixels (required)                      |
| `height`  | `int`     | `0`     | Image height in pixels (0 for proportional scaling)   |
| `crop`    | `bool`    | `false` | Whether to crop the image to exact dimensions         |
| `name`    | `?string` | `null`  | Custom size name (derived from class name if omitted) |

## Name Derivation

When `name` is not specified, it's automatically derived from the class name:

1. Common suffixes (`Image`, `Size`, `ImageSize`) are removed
2. PascalCase is converted to snake_case

| Class Name         | Derived Name    |
| ------------------ | --------------- |
| `HeroImage`        | `hero`          |
| `ThumbnailLarge`   | `thumbnail_large` |
| `SocialShareImage` | `social_share`  |
| `CardSize`         | `card`          |

## Usage

### Basic Image Size

```php
<?php

namespace App\ImageSizes;

use Studiometa\Foehn\Attributes\AsImageSize;

#[AsImageSize(width: 1200, height: 630)]
final class SocialShareImage {}
```

This registers an image size named `social_share` with dimensions 1200×630 pixels.

### With Cropping

```php
#[AsImageSize(width: 800, height: 600, crop: true)]
final class ThumbnailLarge {}
```

When `crop` is `true`, images are cropped to exact dimensions rather than scaled proportionally.

### Custom Name

```php
#[AsImageSize(name: 'hero', width: 1920, height: 1080, crop: true)]
final class HeroBannerImage {}
```

Use a custom `name` when you want explicit control over the image size identifier.

### Width Only (Proportional Height)

```php
#[AsImageSize(width: 400)]
final class SmallThumbnail {}
```

When `height` is `0` (the default), the image height scales proportionally to maintain aspect ratio.

## Theme Support

When any image size is discovered, Foehn automatically enables the `post-thumbnails` theme support:

```php
add_theme_support('post-thumbnails');
```

This ensures featured images work correctly in the WordPress admin.

## Using Custom Sizes

### In Templates

```twig
{# Get specific image size #}
<img src="{{ post.thumbnail.src('social_share') }}" alt="{{ post.thumbnail.alt }}">

{# With Timber's resize #}
<img src="{{ post.thumbnail.src|resize(1200, 630) }}" alt="{{ post.thumbnail.alt }}">
```

### In PHP

```php
// Get image URL for custom size
$url = wp_get_attachment_image_url($attachment_id, 'social_share');

// Get full image tag
$img = wp_get_attachment_image($attachment_id, 'hero');
```

## Organization

We recommend organizing image sizes in a dedicated directory:

```
app/
└── ImageSizes/
    ├── HeroImage.php
    ├── ThumbnailLarge.php
    └── SocialShareImage.php
```

## Related

- [WordPress Image Sizes](https://developer.wordpress.org/reference/functions/add_image_size/)
- [`#[AsPostType]`](./as-post-type) — Post types with thumbnail support
