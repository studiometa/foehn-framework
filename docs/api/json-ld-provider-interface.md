# JsonLdProviderInterface

Interface for the classes that give the schema.org JSON-LD nodes of a post, with [`#[AsJsonLd]`](./as-json-ld).

## Signature

```php
<?php

namespace Studiometa\Foehn\Contracts;

use WP_Post;

interface JsonLdProviderInterface
{
    /**
     * The nodes of a post. An empty list adds nothing.
     *
     * @return list<array<string, mixed>|object>
     */
    public function nodes(WP_Post $post): array;
}
```

## Methods

### nodes(WP_Post $post)

Føhn calls it on the single page of a post of one of the post types of the attribute, with the queried post. Each node is one of:

- an array, such as `['@type' => 'Event', '@id' => '…']`;
- a `JsonSerializable` object that serializes to an array, such as a `spatie/schema-org` type;
- an object with a `toArray()` method, such as an [`Arrayable`](./arrayable) DTO.

Give each node a stable `@id`. Føhn removes the `@context` of each node, because the graph holds it once.

## `JsonLd`

`Studiometa\Foehn\JsonLd\JsonLd` collects the nodes that blocks add while they render:

| Method                                   | Description                                                                     |
| ---------------------------------------- | ------------------------------------------------------------------------------- |
| `JsonLd::add(array\|object $node): void` | Add a node to the graph Føhn prints on `wp_footer`                              |
| `JsonLd::normalize(array\|object $node)` | The node as an array, without its `@context`. Throws `InvalidArgumentException` |

## Related

- [Guide: JSON-LD](/guide/json-ld)
- [`#[AsJsonLd]`](./as-json-ld)
