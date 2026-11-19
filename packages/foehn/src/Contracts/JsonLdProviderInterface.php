<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Contracts;

use WP_Post;

/**
 * Gives the schema.org JSON-LD nodes of a post.
 *
 * Used with #[AsJsonLd]. A node is an array, a JsonSerializable object (such as a
 * `spatie/schema-org` type) or an object with a `toArray()` method. Give each node a
 * stable `@id`, such as the permalink followed by `#event`, so other nodes can refer to it.
 */
interface JsonLdProviderInterface
{
    /**
     * The nodes of a post. An empty list adds nothing.
     *
     * @return list<array<string, mixed>|object>
     */
    public function nodes(WP_Post $post): array;
}
