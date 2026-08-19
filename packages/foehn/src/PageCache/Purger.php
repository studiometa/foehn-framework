<?php

declare(strict_types=1);

namespace Studiometa\Foehn\PageCache;

use Studiometa\Foehn\Config\PageCacheConfig;
use WP_Post;
use WP_Term;

/**
 * When to invalidate, and doing it once.
 *
 * A cache that serves stale pages is worse than no cache, which is why the purge rules
 * exist before anything reads a file rather than after. Which URLs a change makes stale
 * is {@see PurgeTargets}' question; this class decides when to ask it, and batches the
 * answer.
 *
 * Targets are collected in a set during the request and acted on once, on `shutdown`. A
 * bulk edit, a WP-CLI import or an ACF options save touching forty posts would otherwise
 * run the same recursive delete forty times, and the fortieth would be walking
 * directories the first had already removed.
 *
 * Two escape hatches are actions rather than methods, so a plugin, a CDN integration or
 * a project's own code can reach them without a class reference:
 *
 * ```php
 * do_action('foehn/page_cache/purge_post', $postId);
 * do_action('foehn/page_cache/flush');
 * ```
 */
final class Purger
{
    /**
     * Options whose value the whole site renders from.
     *
     * @var list<string>
     */
    private const FLUSHING_OPTIONS = [
        'home',
        'siteurl',
        'blogname',
        'blogdescription',
        'page_on_front',
        'page_for_posts',
        'show_on_front',
        'posts_per_page',
        'permalink_structure',
    ];

    /**
     * URL => whether its `page/**` subtree goes with it.
     *
     * @var array<string, bool>
     */
    private array $targets = [];

    private bool $flushQueued = false;

    private PurgeTargets $resolver;

    public function __construct(
        private readonly PageCacheConfig $config,
        private readonly Store $store,
        ?PurgeTargets $resolver = null,
    ) {
        $this->resolver = $resolver ?? new PurgeTargets();
    }

    /**
     * Wire every hook that can make a stored page wrong.
     */
    public function register(): void
    {
        // Targeted: something about one post changed.
        add_action('save_post', $this->purgePost(...), 10, 1);
        add_action('deleted_post', $this->purgePost(...), 10, 1);
        // `deleted_post` fires after the row is gone, and `get_permalink()` then has
        // nothing to build a URL from. This is the last moment the URL still exists.
        add_action('before_delete_post', $this->purgePost(...), 10, 1);
        add_action('wp_trash_post', $this->purgePost(...), 10, 1);
        add_action('untrash_post', $this->purgePost(...), 10, 1);
        add_action('attachment_updated', $this->purgePost(...), 10, 1);
        add_action('comment_post', $this->purgeComment(...), 10, 1);
        add_action('transition_comment_status', $this->purgeCommentTransition(...), 10, 3);
        add_action('edited_term', $this->purgeTerm(...), 10, 3);
        // Four arguments, because `delete_term` fires after the row is gone and only
        // its fourth — the deleted WP_Term — can still build the archive URL.
        add_action('delete_term', $this->purgeTerm(...), 10, 4);

        // Full flush: something about every page changed.
        add_action('switch_theme', $this->queueFlush(...));
        add_action('customize_save_after', $this->queueFlush(...));
        add_action('wp_update_nav_menu', $this->queueFlush(...));
        add_action('activated_plugin', $this->queueFlush(...));
        add_action('deactivated_plugin', $this->queueFlush(...));
        add_action('update_option_permalink_structure', $this->queueFlush(...));
        add_action('updated_option', $this->onUpdatedOption(...), 10, 1);
        add_action('acf/save_post', $this->onAcfSave(...), 20, 1);

        // For everyone else.
        add_action('foehn/page_cache/purge_post', $this->purgePost(...), 10, 1);
        add_action('foehn/page_cache/flush', $this->queueFlush(...));

        // Late, so a plugin that purges on `shutdown` too has already spoken.
        add_action('shutdown', $this->commit(...), 999);
    }

    /**
     * Queue a post's page and every archive it appears in.
     *
     * The resolved list passes through the `foehn/page_cache/purge_urls` filter before
     * anything is queued, so a project can add the page whose staleness only it knows
     * about — and drop one it knows is safe.
     */
    public function purgePost(int|string|WP_Post $post): void
    {
        $resolved = self::resolvePost($post);

        if ($resolved === null) {
            return;
        }

        $urls = $this->resolver->forPost($resolved);

        if ($urls === null) {
            // A post with a long tail of terms: rebuilding beats enumerating.
            $this->queueFlush();

            return;
        }

        $this->queue($this->filter($urls, $resolved), $urls);
    }

    /**
     * Queue the post a new comment was left on.
     */
    public function purgeComment(int|string $commentId): void
    {
        $comment = self::resolveComment((int) $commentId);

        if ($comment === null) {
            return;
        }

        $this->purgeCommentPost($comment);
    }

    /**
     * Queue the post a comment moved into or out of moderation on.
     */
    public function purgeCommentTransition(string $new, string $old, object $comment): void
    {
        if ($new === $old) {
            return;
        }

        $this->purgeCommentPost($comment);
    }

    /**
     * Queue a term's archive, and the front pages a term rename shows on.
     */
    public function purgeTerm(
        int|string|WP_Term $term,
        int|string $termTaxonomyId = 0,
        string $taxonomy = '',
        mixed $deletedTerm = null,
    ): void {
        $subject = $deletedTerm instanceof WP_Term ? $deletedTerm : $term;
        $urls = $this->resolver->forTerm($subject, $taxonomy);

        $this->queue(array_keys($urls), $urls);
    }

    /**
     * Queue one URL.
     */
    public function purgeUrl(string $url, bool $paginated = false): void
    {
        $this->target($url, $paginated);
    }

    /**
     * Replace every queued target with a full flush.
     */
    public function queueFlush(): void
    {
        $this->flushQueued = true;
        $this->targets = [];
    }

    /**
     * Whether a flush is queued.
     */
    public function isFlushQueued(): bool
    {
        return $this->flushQueued;
    }

    /**
     * The URLs queued so far.
     *
     * @return list<string>
     */
    public function targets(): array
    {
        $urls = array_keys($this->targets);
        sort($urls);

        return $urls;
    }

    /**
     * Act on everything queued, once.
     */
    public function commit(): void
    {
        if ($this->flushQueued) {
            $this->flushQueued = false;
            $this->store->flush();

            return;
        }

        foreach ($this->targets as $url => $paginated) {
            $key = self::keyFor($url);

            if ($key === null) {
                continue;
            }

            $paginated ? $this->store->forgetPaginated($key) : $this->store->forget($key);
        }

        $this->targets = [];
    }

    /**
     * Offer a resolved URL list to the project before anything is queued.
     *
     * @param array<string, bool> $urls
     * @return list<string>
     */
    private function filter(array $urls, ?WP_Post $post = null): array
    {
        /** @var list<string> */
        return apply_filters('foehn/page_cache/purge_urls', array_keys($urls), $post);
    }

    /**
     * Queue a filtered URL list, keeping the pagination decision the resolver made.
     *
     * @param list<string> $urls
     * @param array<string, bool> $paginated
     */
    private function queue(array $urls, array $paginated): void
    {
        foreach ($urls as $url) {
            // A URL the filter added is treated as a listing page: over-purging costs
            // one render, under-purging serves the wrong page until the TTL expires.
            $this->target($url, $paginated[$url] ?? true);
        }
    }

    /**
     * Full-flush the cache when an option the whole site renders from changed.
     */
    private function onUpdatedOption(string $option): void
    {
        if (!in_array($option, self::FLUSHING_OPTIONS, true)) {
            return;
        }

        $this->queueFlush();
    }

    /**
     * ACF options pages are site-wide by definition: a header link, a footer address.
     */
    private function onAcfSave(int|string $postId): void
    {
        if (!is_string($postId) || !str_starts_with($postId, 'options')) {
            return;
        }

        $this->queueFlush();
    }

    /**
     * Queue the post a comment belongs to, whichever hook handed the comment over.
     */
    private function purgeCommentPost(object $comment): void
    {
        $postId = $comment->comment_post_ID ?? null;

        if ($postId === null) {
            return;
        }

        $this->purgePost((int) $postId);
    }

    /**
     * Queue a URL, unless an import has already made a flush the cheaper answer.
     */
    private function target(?string $url, bool $paginated = true): void
    {
        if (!$this->config->enabled) {
            return;
        }

        // An import purges once per post, thousands of times. One flush is cheaper than
        // the recursive deletes, and correct for the same reason.
        if (defined('WP_IMPORTING') && (bool) constant('WP_IMPORTING')) {
            $this->queueFlush();

            return;
        }

        if ($this->flushQueued || $url === null || $url === '') {
            return;
        }

        $this->targets[$url] = ($this->targets[$url] ?? false) || $paginated;
    }

    /**
     * The key a purge target maps to, or null when the URL has none.
     */
    private static function keyFor(string $url): ?CacheKey
    {
        $host = parse_url($url, PHP_URL_HOST);
        $path = parse_url($url, PHP_URL_PATH);

        if (!is_string($host)) {
            return null;
        }

        return CacheKey::create($host, is_string($path) ? $path : '/');
    }

    /**
     * A comment, or null when the id resolves to nothing.
     */
    private static function resolveComment(int $commentId): ?object
    {
        // `get_comment()`'s stubbed return type is an intersection of two conditional
        // types, which the analyser reduces to `never`. Taken as mixed and narrowed
        // here, which is what the runtime does anyway.
        /** @var mixed $comment */
        $comment = get_comment($commentId, 'OBJECT');

        return is_object($comment) ? $comment : null;
    }

    /**
     * A post, from whichever of the three shapes a WordPress hook passes.
     */
    private static function resolvePost(int|string|WP_Post $post): ?WP_Post
    {
        if ($post instanceof WP_Post) {
            return $post;
        }

        $resolved = get_post((int) $post);

        return $resolved instanceof WP_Post ? $resolved : null;
    }
}
