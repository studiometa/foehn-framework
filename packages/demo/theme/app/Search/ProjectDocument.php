<?php

declare(strict_types=1);

namespace Demo\Search;

use Studiometa\Foehn\Search\Attributes\AsSearchDocument;
use Studiometa\Foehn\Search\Contracts\SearchDocumentInterface;
use Studiometa\Foehn\Search\Document;
use WP_Post;

/**
 * A series in the search index: the default fields, and who commissioned it and where
 * it was shot, so a search for "Grille" or "Basel" finds the series.
 *
 * `search.config.php` names the two fields in `searchableAttributes`.
 */
#[AsSearchDocument(postType: 'project')]
final class ProjectDocument implements SearchDocumentInterface
{
    public function toDocument(WP_Post $post): ?array
    {
        return [
            ...Document::fromPost($post),
            'client' => (string) get_post_meta($post->ID, 'client', true),
            'location' => (string) get_post_meta($post->ID, 'location', true),
        ];
    }
}
