<?php

declare(strict_types=1);

namespace Demo\Search;

use Studiometa\Foehn\Search\Attributes\AsSearchDocument;
use Studiometa\Foehn\Search\Contracts\SearchDocumentInterface;
use Studiometa\Foehn\Search\Document;
use WP_Post;

/**
 * A journal post in the search index, with the default fields. The indexer of the
 * package already skips the drafts, the trashed posts and the protected post, so the
 * class does not check for them.
 */
#[AsSearchDocument(postType: 'post')]
final class PostDocument implements SearchDocumentInterface
{
    public function toDocument(WP_Post $post): ?array
    {
        return Document::fromPost($post);
    }
}
