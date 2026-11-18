# #[AsJsonLd]

Register a class that gives the schema.org JSON-LD nodes of the single pages of one or more post types.

## Signature

```php
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsJsonLd
{
    public function __construct(
        public string|array $postType,
    ) {}

    /** @return list<string> */
    public function getPostTypes(): array {}
}
```

## Parameters

| Parameter  | Type                   | Default | Description                                                |
| ---------- | ---------------------- | ------- | ---------------------------------------------------------- |
| `postType` | `string\|list<string>` | —       | The post types whose single pages get the nodes (required) |

## Usage

```php
<?php

namespace App\JsonLd;

use Spatie\SchemaOrg\Schema;
use Studiometa\Foehn\Attributes\AsJsonLd;
use Studiometa\Foehn\Contracts\JsonLdProviderInterface;
use WP_Post;

#[AsJsonLd(postType: ['event', 'festival'])]
final class EventJsonLd implements JsonLdProviderInterface
{
    public function nodes(WP_Post $post): array
    {
        $url = get_permalink($post);

        return [
            Schema::event()
                ->identifier($url . '#event')
                ->name(get_the_title($post))
                ->url($url),
        ];
    }
}
```

With the Schema module of Rank Math on, the nodes go in the graph of Rank Math. Else Føhn prints them in one `<script type="application/ld+json">` in `<head>`.

A class that has the attribute but does not implement `JsonLdProviderInterface` is skipped and reported: see `wp foehn discovery:status`.

## Related

- [Guide: JSON-LD](/guide/json-ld)
- [`JsonLdProviderInterface`](./json-ld-provider-interface)
