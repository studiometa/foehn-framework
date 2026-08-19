<?php

declare(strict_types=1);

namespace Studiometa\Foehn\PageCache;

use Studiometa\Foehn\Helpers\WP;
use WP_Post;
use WP_Term;

/**
 * Which URLs one change makes stale.
 *
 * Separate from {@see Purger}, which decides *when* to act and batches the acting.
 * This class answers the harder question, and it is the question that goes wrong: a
 * page whose content depends on a post in a way this class does not model goes stale
 * and stays stale until the TTL sweep catches it.
 *
 * Every URL it returns is a WordPress-built URL — `get_permalink()`,
 * `get_term_link()` — and is resolved into a filename by {@see CacheKey} and nothing
 * else. That matters more than it looks: WordPress stores a non-ASCII slug with
 * lowercase percent escapes while a browser sends uppercase ones, so the two spellings
 * only meet if one implementation does the decoding.
 *
 * A map of URL => "and its `page/**` subtree", because an archive's pagination is stale
 * whenever the archive is, and a single post's is not.
 */
final readonly class PurgeTargets
{
    /**
     * Beyond this many terms on one post, walking the archives costs more than
     * rebuilding the whole cache does.
     */
    private const MAX_TERMS = 50;

    /**
     * Post types that are not pages and never had a URL to purge.
     *
     * @var list<string>
     */
    private const IGNORED_POST_TYPES = [
        'revision',
        'nav_menu_item',
        'custom_css',
        'customize_changeset',
        'oembed_cache',
        'user_request',
        'wp_global_styles',
        'wp_navigation',
        'wp_template',
        'wp_template_part',
        'wp_block',
    ];

    /**
     * Everything one post going stale makes stale.
     *
     * Returns null to mean "flush the whole cache instead" — the answer for a post
     * carrying a long tail of terms, where enumerating the archives is the expensive
     * half. An empty array means "this post never had a URL".
     *
     * @return array<string, bool>|null
     */
    public function forPost(WP_Post $post): ?array
    {
        if (in_array($post->post_type, self::IGNORED_POST_TYPES, true) || $post->post_status === 'auto-draft') {
            return [];
        }

        $urls = [];

        // The post's own page is not paginated under `page/`: `<!--nextpage-->` splits
        // it at /slug/2/, which is a different page rather than the same one repeated.
        $this->add($urls, self::permalink($post), paginated: false);

        foreach ($this->frontPages() as $url) {
            $this->add($urls, $url);
        }

        $archive = get_post_type_archive_link($post->post_type);

        if (is_string($archive)) {
            $this->add($urls, $archive);
        }

        $this->add($urls, get_author_posts_url((int) $post->post_author));
        $this->add($urls, $this->monthArchive($post));

        foreach ($this->relatives($post) as $url => $paginated) {
            $this->add($urls, $url, $paginated);
        }

        $terms = $this->termArchives($post);

        if ($terms === null) {
            return null;
        }

        foreach ($terms as $url) {
            $this->add($urls, $url);
        }

        return $urls;
    }

    /**
     * A term archive, and the front pages a term rename shows on.
     *
     * @return array<string, bool>
     */
    public function forTerm(int|string|WP_Term $term, string $taxonomy = ''): array
    {
        $urls = [];
        $link = get_term_link($term instanceof WP_Term ? $term : (int) $term, $taxonomy);

        $this->add($urls, is_string($link) ? $link : null);

        foreach ($this->frontPages() as $url) {
            $this->add($urls, $url);
        }

        return $urls;
    }

    /**
     * The pages that list posts regardless of which post changed.
     *
     * @return list<string>
     */
    public function frontPages(): array
    {
        $urls = [home_url('/')];
        $postsPage = get_option('page_for_posts');

        if (get_option('show_on_front') === 'page' && is_numeric($postsPage) && (int) $postsPage > 0) {
            $permalink = self::permalinkOf((int) $postsPage);

            if ($permalink !== null) {
                $urls[] = $permalink;
            }
        }

        return $urls;
    }

    /**
     * The pages that render a link to this post because of where it sits.
     *
     * A parent page listing its children goes stale when a child is renamed, and a
     * single template that renders previous/next links goes stale on both sides of an
     * insertion. Neither shows up in a permalink or an archive, and both are pages a
     * reader would otherwise see the old version of.
     *
     * @return array<string, bool>
     */
    private function relatives(WP_Post $post): array
    {
        $urls = [];

        foreach (get_post_ancestors($post) as $ancestorId) {
            $this->add($urls, self::permalinkOf((int) $ancestorId), paginated: false);
        }

        foreach ([true, false] as $previous) {
            $adjacent = $this->adjacentPost($post, $previous);

            if ($adjacent === null) {
                continue;
            }

            $this->add($urls, self::permalink($adjacent), paginated: false);
        }

        return $urls;
    }

    /**
     * The post before or after this one.
     *
     * `get_adjacent_post()` reads the global post rather than taking one, so the post
     * under purge is lent to the global for the length of the call — a purge triggered
     * from WP-CLI or from an admin save has no meaningful global of its own.
     */
    private function adjacentPost(WP_Post $post, bool $previous): ?WP_Post
    {
        if (!function_exists('get_adjacent_post')) {
            return null;
        }

        $adjacent = WP::withPost($post, static fn(): mixed => get_adjacent_post(false, '', $previous));

        return $adjacent instanceof WP_Post ? $adjacent : null;
    }

    private function monthArchive(WP_Post $post): ?string
    {
        $date = $post->post_date;

        if (strlen($date) < 7) {
            return null;
        }

        $link = get_month_link((int) substr($date, 0, 4), (int) substr($date, 5, 2));

        return is_string($link) ? $link : null;
    }

    /**
     * Every term archive the post appears in, or null past the point where walking
     * them costs more than rebuilding the whole cache.
     *
     * @return list<string>|null
     */
    private function termArchives(WP_Post $post): ?array
    {
        $links = [];

        foreach (get_object_taxonomies($post->post_type) as $taxonomy) {
            $terms = wp_get_post_terms($post->ID, $taxonomy);

            if (!is_array($terms)) {
                continue;
            }

            foreach ($terms as $term) {
                if (!$term instanceof WP_Term) {
                    continue;
                }

                $link = get_term_link($term, $taxonomy);

                if (is_string($link)) {
                    $links[] = $link;
                }
            }

            if (count($links) > self::MAX_TERMS) {
                return null;
            }
        }

        return $links;
    }

    /**
     * Put a URL in a target map, if there is one.
     *
     * @param array<string, bool> $urls
     */
    private function add(array &$urls, ?string $url, bool $paginated = true): void
    {
        if ($url === null || $url === '') {
            return;
        }

        $urls[$url] = ($urls[$url] ?? false) || $paginated;
    }

    private static function permalink(WP_Post $post): ?string
    {
        $permalink = get_permalink($post);

        return is_string($permalink) ? $permalink : null;
    }

    private static function permalinkOf(int $postId): ?string
    {
        $permalink = get_permalink($postId);

        return is_string($permalink) ? $permalink : null;
    }
}
