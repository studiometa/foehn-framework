<?php

declare(strict_types=1);

namespace Demo\Search;

use Studiometa\Foehn\Search\Attributes\AsSearchDocument;
use Studiometa\Foehn\Search\Contracts\SearchDocumentInterface;
use Studiometa\Foehn\Search\Document;
use WP_Post;

/**
 * A page in the search index, with the default fields.
 *
 * The Home and Journal pages are listings with no text of their own, so `null` keeps
 * them out of the index.
 */
#[AsSearchDocument(postType: 'page')]
final class PageDocument implements SearchDocumentInterface
{
    public function toDocument(WP_Post $post): ?array
    {
        $listings = [(int) get_option('page_on_front'), (int) get_option('page_for_posts')];

        return in_array($post->ID, $listings, true) ? null : Document::fromPost($post);
    }
}
