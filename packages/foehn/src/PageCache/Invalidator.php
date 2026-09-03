<?php

declare(strict_types=1);

namespace Studiometa\Foehn\PageCache;

use Studiometa\Foehn\Config\PageCacheConfig;

/**
 * The one way anything at runtime deletes a stored page.
 *
 * There are four callers with the same question and no reason to answer it four times:
 * WordPress invalidation hooks through {@see Purger}, `wp foehn cache:clear`, the Føhn
 * dashboard, and the admin bar. Before this class existed, two of them built a
 * {@see CacheKey} from a URL with their own `parse_url()` and their own error handling —
 * which is how a WP-CLI clear and an automatic purge come to disagree about which files
 * one URL owns, and the one that disagrees quietly is the one that leaves a stale page
 * up.
 *
 * The split with its two collaborators is deliberate and worth stating:
 *
 * - **This class owns URL validation and key creation.** A caller hands over a URL, not
 *   a path and not a key.
 * - **{@see Store} owns the filesystem.** Atomic writes, containment, tree deletion,
 *   directory pruning.
 * - **{@see Purger} owns *when*.** Which WordPress events make which URLs stale, and
 *   batching them so a bulk edit deletes once rather than forty times.
 *
 * Every operation returns the same thing: the number of **stored response bodies**
 * deleted. A headers sidecar goes with its body and is not counted, because an operator
 * reading "12 files cleared" for six pages cannot act on that number.
 *
 * **It works while caching is disabled.** Nothing here reads `PageCacheConfig::$enabled`
 * for that reason: a release that had the cache on leaves files behind, and the operator
 * turning it off is precisely the one who needs them gone. The config is still injected,
 * because the cache root comes from it.
 *
 * This is the static page cache and nothing else. Discovery cache, transformed images,
 * application transients and PHP's OPcache have their own lifecycles, and a content
 * edit is not a reason to clear any of them.
 */
final readonly class Invalidator
{
    /**
     * When the cache was last emptied in full, as a Unix timestamp.
     *
     * One option, overwritten, and not autoloaded. The Føhn dashboard has to be able to
     * answer "when was this last cleared" — an operator watching a deploy needs to know
     * whether the empty cache in front of them is the one they just emptied — and a
     * single timestamp is the smallest thing that answers it. Deliberately *not* an event
     * log: per-URL and per-section purges are not recorded at all, because a table
     * growing by a row per content edit is a maintenance burden nobody asked for and the
     * question it would answer is one no operator has.
     */
    public const LAST_FLUSH_OPTION = 'foehn_page_cache_last_flush';

    public function __construct(
        private PageCacheConfig $config,
        private Store $store,
    ) {}

    /**
     * Empty the page cache.
     *
     * @return int Stored response bodies removed.
     */
    public function flush(): int
    {
        $removed = $this->store->flush();

        $this->recordFlush();

        return $removed;
    }

    /**
     * Delete every section-cache entry, leaving whole pages alone.
     *
     * @return int Stored response bodies removed.
     */
    public function flushSections(): int
    {
        return $this->store->flushSections();
    }

    /**
     * Delete one URL: its page, its 404, its keyed variants and its section fragments.
     *
     * Null rather than a count when the URL cannot become a cache key — no host, a path
     * this cache would refuse to write, an attempt to climb out of the root. It is a
     * different answer from `0`, and both callers need the difference: WP-CLI says "that
     * URL cannot be a cache key" for the first and "nothing was cached" for the second,
     * and an admin handler that could not tell them apart would report success for a
     * request it rejected.
     *
     * @param bool $paginated Whether the URL's `page/**` subtree goes with it. An
     *                        archive's pagination is stale whenever the archive is.
     * @return int|null Stored response bodies removed, or null when the URL has no key.
     */
    public function forgetUrl(string $url, bool $paginated = false): ?int
    {
        $key = $this->keyFor($url);

        if ($key === null) {
            return null;
        }

        return $paginated ? $this->store->forgetPaginated($key) : $this->store->forget($key);
    }

    /**
     * The cache root every operation here stays inside.
     */
    public function root(): string
    {
        return $this->config->getPath();
    }

    /**
     * Note the moment, when there is a WordPress to note it in.
     *
     * Guarded on the function rather than on a constant, because this class is reached
     * from contexts that have no database: `wp foehn cache:clear` has one, the page-cache
     * drop-in loads from `wp-settings.php` before options are readable, and the unit
     * suite has neither. A flush that could not be recorded is still a flush, so the
     * missing timestamp is never an error — the dashboard says "unknown", which is what
     * it would have to say anyway.
     *
     * Recorded even when the cache was already empty. "Nothing was there" and "nobody has
     * cleared it" are different facts, and an operator who has just pressed the button is
     * owed the first.
     */
    private function recordFlush(): void
    {
        if (!function_exists('update_option')) {
            return;
        }

        update_option(self::LAST_FLUSH_OPTION, time(), false);
    }

    /**
     * The key a URL maps to, or null when it has none.
     *
     * Resolved through {@see CacheKey} and never through string handling of its own.
     * That is not tidiness: WordPress stores a non-ASCII slug with lowercase percent
     * escapes while a browser sends uppercase ones, so `get_permalink()` hands this a
     * different spelling of the URL than the recorder was asked for. Only one decode, in
     * one place, collapses the two onto one file — and a second implementation is how
     * category and author archives came to serve stale pages after every edit in
     * wp-super-cache.
     */
    private function keyFor(string $url): ?CacheKey
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (!is_string($host)) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);

        return CacheKey::create($host, is_string($path) ? $path : '/');
    }
}
