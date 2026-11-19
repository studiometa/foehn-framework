<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Search\Contracts;

use WP_Post;

/**
 * Turns one published post into the fields the search index stores.
 */
interface SearchDocumentInterface
{
    /**
     * The fields of the document, or null to keep the post out of the index.
     *
     * The index sets `id` and `post_type` itself. The fields that the search reads
     * are the searchable attributes of {@see \Studiometa\Foehn\Search\Config\SearchConfig}:
     * `title`, `terms`, `excerpt` and `content` by default, which
     * {@see \Studiometa\Foehn\Search\Document::fromPost()} gives.
     *
     * @return array<string, string|int|float|bool|list<string>>|null
     */
    public function toDocument(WP_Post $post): ?array;
}
