# Add `#[AsImageSize]` attribute for WordPress image size registration

## Problem

Every theme needs custom image sizes. Currently this is scattered in ThemeManager:

```php
// Current pattern
class ThemeManager implements ManagerInterface {
    public function run() {
        add_theme_support('post-thumbnails');
        add_action('after_setup_theme', [$this, 'register_image_sizes']);
    }

    public function register_image_sizes() {
        add_image_size('card', 400, 300, true);
        add_image_size('card-large', 800, 600, true);
        add_image_size('hero', 1920, 1080, true);
        add_image_size('hero-mobile', 768, 1024, true);
    }
}
```

Problems:

- Scattered in ThemeManager alongside unrelated code
- No clear overview of available sizes
- Hard to scaffold or discover

## Proposed solution

Following Foehn's pattern (like `#[AsMenu]`, `#[AsPostType]`), one class per image size:

```php
// app/ImageSizes/Card.php
namespace App\ImageSizes;

use Studiometa\Foehn\Attributes\AsImageSize;

#[AsImageSize(width: 400, height: 300, crop: true)]
final class Card {}
```

```php
// app/ImageSizes/CardLarge.php
namespace App\ImageSizes;

use Studiometa\Foehn\Attributes\AsImageSize;

#[AsImageSize(width: 800, height: 600, crop: true)]
final class CardLarge {}
```

```php
// app/ImageSizes/Hero.php
namespace App\ImageSizes;

use Studiometa\Foehn\Attributes\AsImageSize;

#[AsImageSize(width: 1920, height: 1080, crop: true)]
final class Hero {}
```

```php
// app/ImageSizes/HeroMobile.php
namespace App\ImageSizes;

use Studiometa\Foehn\Attributes\AsImageSize;

#[AsImageSize(width: 768, height: 1024, crop: true)]
final class HeroMobile {}
```

## Benefits

1. **Discoverability**: `ls app/ImageSizes/` shows all sizes
2. **CLI friendly**: Easy to scaffold with `make:image-size`
3. **AI/Agent friendly**: List files to discover sizes
4. **Consistent**: Same pattern as Menu, PostType, Taxonomy, etc.
5. **Auto-registration**: No manual `add_image_size()` calls

## Attribute definition

```php
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsImageSize
{
    /**
     * @param int $width Width in pixels (0 for proportional)
     * @param int $height Height in pixels (0 for proportional)
     * @param bool|array $crop Crop behavior: true, false, or [x_position, y_position]
     * @param string|null $name Size slug (defaults to snake_case of class name)
     */
    public function __construct(
        public int $width,
        public int $height,
        public bool|array $crop = false,
        public ?string $name = null,
    ) {}
}
```

## Name derivation

The size name is derived from the class name (snake_case) unless explicitly provided:

```php
// Name derived from class: 'card'
#[AsImageSize(width: 400, height: 300, crop: true)]
final class Card {}

// Name derived from class: 'card_large'
#[AsImageSize(width: 800, height: 600, crop: true)]
final class CardLarge {}

// Explicit name override: 'card-large' (with hyphen)
#[AsImageSize(name: 'card-large', width: 800, height: 600, crop: true)]
final class CardLarge {}
```

## Crop positions

WordPress supports crop positions when using array:

```php
#[AsImageSize(width: 1920, height: 1080, crop: true)]                    // Center (default)
#[AsImageSize(width: 1920, height: 1080, crop: ['center', 'top'])]       // Top center
#[AsImageSize(width: 1920, height: 1080, crop: ['left', 'center'])]      // Left center
#[AsImageSize(width: 1920, height: 1080, crop: ['right', 'bottom'])]     // Bottom right
```

Positions: `left`, `center`, `right` (x) and `top`, `center`, `bottom` (y).

## Proportional sizing

Use `0` for width or height to scale proportionally:

```php
// app/ImageSizes/Logo.php
#[AsImageSize(width: 200, height: 0, crop: false)]  // 200px wide, height proportional
final class Logo {}

// app/ImageSizes/Portrait.php
#[AsImageSize(width: 0, height: 400, crop: false)]  // 400px tall, width proportional
final class Portrait {}
```

## Discovery

```php
final class ImageSizeDiscovery implements Discovery
{
    use IsDiscovery;

    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        $attribute = $class->getAttribute(AsImageSize::class);

        if ($attribute) {
            $this->discoveryItems->add($location, [
                'attribute' => $attribute,
                'className' => $class->getName(),
            ]);
        }
    }

    public function apply(): void
    {
        add_action('after_setup_theme', function () {
            add_theme_support('post-thumbnails');

            foreach ($this->discoveryItems as $item) {
                $attribute = $item['attribute'];
                $name = $attribute->name ?? $this->classToSlug($item['className']);

                add_image_size(
                    $name,
                    $attribute->width,
                    $attribute->height,
                    $attribute->crop,
                );
            }
        });
    }

    private function classToSlug(string $className): string
    {
        $shortName = (new \ReflectionClass($className))->getShortName();
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $shortName));
    }
}
```

## Directory structure

```
app/
├── ImageSizes/
│   ├── Card.php
│   ├── CardLarge.php
│   ├── Hero.php
│   ├── HeroMobile.php
│   ├── OgImage.php
│   ├── Avatar.php
│   └── Logo.php
```

## Usage in Twig

Timber handles image sizes natively:

```twig
{# Get image at specific size #}
<img src="{{ post.thumbnail.src('card') }}" alt="{{ post.thumbnail.alt }}">

{# With srcset for responsive #}
<img
    src="{{ post.thumbnail.src('card') }}"
    srcset="{{ post.thumbnail.srcset }}"
    sizes="(max-width: 768px) 100vw, 400px"
    alt="{{ post.thumbnail.alt }}"
>

{# ACF image field #}
{% set image = Image(fields.hero_image) %}
<img src="{{ image.src('hero') }}" alt="{{ image.alt }}">
```

## CLI commands

```bash
# Create a new image size
php foehn make:image-size Card --width=400 --height=300 --crop
php foehn make:image-size Hero --width=1920 --height=1080 --crop
php foehn make:image-size Logo --width=200 --height=0

# Output: app/ImageSizes/Card.php
```

```bash
# List all registered image sizes
php foehn list:image-sizes

# Output:
# Registered Image Sizes
# ──────────────────────
# card          400 × 300    crop: center
# card_large    800 × 600    crop: center
# hero          1920 × 1080  crop: center
# hero_mobile   768 × 1024   crop: center
# og_image      1200 × 630   crop: center
# avatar        96 × 96      crop: center
# logo          200 × auto   crop: no
```

## Common sizes to scaffold

```bash
php foehn make:image-size Card --width=400 --height=300 --crop
php foehn make:image-size CardLarge --width=800 --height=600 --crop
php foehn make:image-size Hero --width=1920 --height=1080 --crop
php foehn make:image-size HeroMobile --width=768 --height=1024 --crop
php foehn make:image-size OgImage --width=1200 --height=630 --crop
php foehn make:image-size Avatar --width=96 --height=96 --crop
php foehn make:image-size Logo --width=200 --height=0
```

## Tasks

- [ ] Create `AsImageSize` attribute
- [ ] Create `ImageSizeDiscovery`
- [ ] Auto-enable `post-thumbnails` theme support
- [ ] Add `make:image-size` CLI command
- [ ] Add `list:image-sizes` CLI command
- [ ] Document common presets
- [ ] Add tests

## Labels

`enhancement`, `priority-medium`
