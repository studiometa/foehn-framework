# Built-in Data DTOs

Føhn provides typed DTOs for common ACF field patterns. All implement `Arrayable` and use `HasToArray`.

## LinkData

DTO for link/button fields matching ACF link fields (return_format: array).

```php
<?php

namespace Studiometa\Foehn\Data;

final readonly class LinkData implements Arrayable
{
    use HasToArray;

    public function __construct(
        public string $url,
        public string $title,
        public string $target = '',
    ) {}

    public static function fromAcf(?array $link): ?self;
}
```

### Factory

```php
use Studiometa\Foehn\Data\LinkData;

// From ACF link field — returns null if empty
$link = LinkData::fromAcf($fields['cta']);

// Manual
$link = new LinkData(url: '/about', title: 'About', target: '_blank');
```

### Array Output

```php
$link->toArray();
// ['url' => '/about', 'title' => 'About', 'target' => '_blank']
```

## ImageData

DTO for image/attachment fields matching ACF image fields (return_format: id).

```php
<?php

namespace Studiometa\Foehn\Data;

final readonly class ImageData implements Arrayable
{
    use HasToArray;

    public function __construct(
        public int $id,
        public string $src,
        public string $alt = '',
        public ?int $width = null,
        public ?int $height = null,
    ) {}

    public static function fromAttachmentId(?int $id, string $size = 'large'): ?self;
}
```

### Factory

```php
use Studiometa\Foehn\Data\ImageData;

// From WordPress attachment ID — returns null if invalid
$image = ImageData::fromAttachmentId($fields['background']);
$image = ImageData::fromAttachmentId($fields['background'], 'full');

// Manual
$image = new ImageData(id: 42, src: '/img.jpg', alt: 'Photo', width: 800, height: 600);
```

### Array Output

```php
$image->toArray();
// ['id' => 42, 'src' => '/img.jpg', 'alt' => 'Photo', 'width' => 800, 'height' => 600]
```

## SpacingData

DTO for spacing fields matching `SpacingBuilder` output.

```php
<?php

namespace Studiometa\Foehn\Data;

final readonly class SpacingData implements Arrayable
{
    use HasToArray;

    public function __construct(
        public string $top = 'medium',
        public string $bottom = 'medium',
    ) {}

    public static function fromAcf(?array $fields, string $prefix = 'spacing'): self;
}
```

### Factory

```php
use Studiometa\Foehn\Data\SpacingData;

// From ACF fields with prefix
$spacing = SpacingData::fromAcf($fields, 'spacing');
// Reads $fields['spacing_top'] and $fields['spacing_bottom']

// Manual
$spacing = new SpacingData(top: 'large', bottom: 'small');
```

### Array Output

```php
$spacing->toArray();
// ['top' => 'large', 'bottom' => 'small']
```

## Creating Custom DTOs

Follow the same pattern for your own DTOs:

```php
<?php

namespace App\Data;

use Studiometa\Foehn\Concerns\HasToArray;
use Studiometa\Foehn\Contracts\Arrayable;

final readonly class SocialLink implements Arrayable
{
    use HasToArray;

    public function __construct(
        public string $platform,
        public string $url,
        public string $label,
    ) {}

    public static function fromAcf(?array $fields): ?self
    {
        if ($fields === null || empty($fields['url'])) {
            return null;
        }

        return new self(
            platform: $fields['platform'] ?? '',
            url: $fields['url'],
            label: $fields['label'] ?? $fields['platform'] ?? '',
        );
    }
}
```

## Related

- [Guide: Arrayable DTOs](/guide/arrayable-dtos)
- [Guide: Field Fragments](/guide/field-fragments)
- [Arrayable](./arrayable)
- [HasToArray](./has-to-array)
