<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Search\Query;

use WP_Post;

/**
 * The results of one post type on the search page.
 */
final readonly class SearchGroup
{
    /**
     * @param string $postType The post type
     * @param string $label The plural name of the post type
     * @param int $count The number of results of this post type, after the filters
     * @param list<WP_Post> $posts The most relevant results
     * @param string $url The search page limited to this post type: the "see all" link
     */
    public function __construct(
        public string $postType,
        public string $label,
        public int $count,
        public array $posts,
        public string $url,
    ) {}
}
