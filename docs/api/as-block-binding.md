# #[AsBlockBinding]

Registers a block bindings source: a value computed at render time and bound to a block attribute.

::: tip
A value that is merely **stored** needs none of this. A key declared with [`#[AsPostMeta]`](./as-post-meta) is bindable through core's own `core/post-meta` with no source of your own. See the [guide](/guide/block-bindings).
:::

## Signature

```php
<?php

namespace Studiometa\Foehn\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsBlockBinding
{
    public function __construct(
        public string $name,
        public string $label,
        public array $usesContext = [],
    ) {}
}
```

## Parameters

| Parameter     | Type           | Default    | Description                                                        |
| ------------- | -------------- | ---------- | ------------------------------------------------------------------ |
| `name`        | `string`       | _required_ | `namespace/name`. A name without the slash is refused at discovery |
| `label`       | `string`       | _required_ | Shown in the editor's binding UI                                   |
| `usesContext` | `list<string>` | `[]`       | Block context keys the value needs, e.g. `postId`                  |

## BlockBindingInterface

```php
<?php

namespace Studiometa\Foehn\Contracts;

use WP_Block;

interface BlockBindingInterface
{
    public function value(array $args, WP_Block $block, string $attribute): ?string;
}
```

Required. A class carrying the attribute without it is refused during discovery.

| Parameter    | Contents                                                                       |
| ------------ | ------------------------------------------------------------------------------ |
| `$args`      | What the binding declared in the block's markup, e.g. `['key' => 'price']`     |
| `$block`     | The block being rendered; its `context` holds the keys `usesContext` asked for |
| `$attribute` | Which attribute is being bound — a source on two attributes is called twice    |

Returning `null` leaves the attribute as the block author wrote it.

## Usage

```php
#[AsBlockBinding(name: 'theme/reading-time', label: 'Reading time', usesContext: ['postId'])]
final readonly class ReadingTime implements BlockBindingInterface
{
    public function value(array $args, WP_Block $block, string $attribute): ?string
    {
        // …
    }
}
```

## Notes

- **Registered on `init`**, which is where `register_block_bindings_source()` belongs.
- **The class is resolved when a bound block renders**, not when the source is registered, so a source nothing binds to costs nothing.
- **Which attributes accept a binding is version-dependent.** See the [guide](/guide/block-bindings#which-attributes-accept-a-binding) for the WordPress 7.0 list and the filter that extends it.
- **A WordPress older than 6.5 gets no sources** rather than a fatal error.

## Related

- [Guide: Block Bindings](/guide/block-bindings)
- [#[AsPostMeta]](./as-post-meta)
- [#[AsBlock]](./as-block)
