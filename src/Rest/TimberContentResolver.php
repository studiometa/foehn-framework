<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Rest;

use Studiometa\Foehn\Contracts\ContentResolverInterface;
use Timber\Post;
use Timber\Term;
use Timber\Timber;

/**
 * Default content resolver using Timber.
 */
final readonly class TimberContentResolver implements ContentResolverInterface
{
    public function resolvePost(int $postId): ?Post
    {
        $post = Timber::get_post($postId);

        // Only allow published/public posts
        if (!$post instanceof Post || $post->post_status !== 'publish') {
            return null;
        }

        return $post;
    }

    public function resolveTerm(int $termId, string $taxonomy): ?Term
    {
        $term = Timber::get_term_by('id', $termId, $taxonomy);

        if (!$term instanceof Term) {
            return null;
        }

        return $term;
    }
}
