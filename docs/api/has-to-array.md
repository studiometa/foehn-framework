# HasToArray

Reflection-based `toArray()` trait for DTOs implementing `Arrayable`.

## Signature

```php
<?php

namespace Studiometa\Foehn\Concerns;

trait HasToArray
{
    public function toArray(): array;
    protected function propertyToKey(string $name): string;
}
```

## Behavior

1. Reads all **public instance properties** via reflection
2. Converts **camelCase** names to **snake_case** keys (e.g., `backgroundImage` → `background_image`)
3. Recursively flattens nested `Arrayable` objects
4. Recursively flattens `Arrayable` items in arrays
5. Skips uninitialized and static properties

## Customization

Override `propertyToKey()` to change the key mapping:

```php
final readonly class MyContext implements Arrayable
{
    use HasToArray;

    public function __construct(
        public string $firstName,
    ) {}

    // Keep camelCase keys
    protected function propertyToKey(string $name): string
    {
        return $name;
    }
}

(new MyContext('John'))->toArray();
// ['firstName' => 'John'] instead of ['first_name' => 'John']
```

## Related

- [Guide: Arrayable DTOs](/guide/arrayable-dtos)
- [Arrayable](./arrayable)
