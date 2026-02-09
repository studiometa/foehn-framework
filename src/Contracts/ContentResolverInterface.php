<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Contracts;

use Timber\Post;
use Timber\Term;

/**
 * Resolves WordPress content (posts, terms) for the Render API.
 */
interface ContentResolverInterface
{
    /**
     * Resolve a post ID to a Timber Post.
     *
     * Should only return published/public posts.
     */
    public function resolvePost(int $postId): ?Post;

    /**
     * Resolve a term ID to a Timber Term.
     */
    public function resolveTerm(int $termId, string $taxonomy): ?Term;
}
