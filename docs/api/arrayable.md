# Arrayable

Interface for objects that can be converted to an associative array. Used by DTOs returned from `compose()` methods.

## Signature

```php
<?php

namespace Studiometa\Foehn\Contracts;

interface Arrayable
{
    /**
     * Convert the object to an associative array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
```

## Usage

Implement this interface on DTOs used as block or pattern context:

```php
use Studiometa\Foehn\Concerns\HasToArray;
use Studiometa\Foehn\Contracts\Arrayable;

final readonly class CardContext implements Arrayable
{
    use HasToArray;

    public function __construct(
        public string $title,
        public string $excerpt,
        public ?ImageData $image = null,
    ) {}
}
```

The `HasToArray` trait provides a reflection-based `toArray()` that converts public properties to snake_case keys and recursively flattens nested `Arrayable` objects.

## Related

- [Guide: Arrayable DTOs](/guide/arrayable-dtos)
- [HasToArray](./has-to-array)
