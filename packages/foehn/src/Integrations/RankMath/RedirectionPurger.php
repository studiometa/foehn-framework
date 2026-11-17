<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Integrations\RankMath;

use Studiometa\Foehn\Helpers\WP;
use Studiometa\Foehn\PageCache\Invalidator;

/**
 * Purge the stored page of each source of a Rank Math redirection that is turned on.
 *
 * Rank Math redirects in PHP. A page that the cache stored before the redirection
 * existed is served by nginx or the drop-in, and PHP does not run: the redirection has
 * no effect until the page is purged.
 *
 * **Why the database and not Rank Math's hooks.** Rank Math fires an action when the
 * redirection form saves (`rank_math/redirection/saved`), but not when the list
 * activates or restores a redirection, when the post editor saves one, or when a slug
 * change creates one. Every one of these writes the row with `updated` set to the current
 * time. So this class watches for a write to the redirections table, and on `shutdown`
 * reads the active rows that were updated since the request started.
 *
 * **Only active redirections.** The cache never stores a redirect response, so a
 * redirection that is deleted or turned off has no stored page to purge: the next
 * request renders the page and the cache stores it.
 *
 * **Which pages.** An exact source is one path: its stored page is deleted, with its
 * query-string variants. Any other source (contains, starts with, ends with, regex, or an
 * exact source that ignores case) can match many paths, and the cache cannot list the
 * paths it matches, so the whole cache is emptied.
 */
final class RedirectionPurger
{
    /** The table Rank Math keeps its redirections in, without the prefix. */
    public const TABLE = 'rank_math_redirections';

    /** When the request started, as Rank Math writes `updated`. */
    private string $since = '';

    private bool $written = false;

    public function __construct(
        private readonly Invalidator $invalidator,
    ) {}

    public function register(): void
    {
        $this->since = current_time('mysql');

        add_filter('query', $this->watch(...));
        add_action('shutdown', $this->purge(...));
    }

    /**
     * Note a write to the redirections table, and hand the query back unchanged.
     */
    public function watch(string $query): string
    {
        if (!$this->written && preg_match($this->writePattern(), $query) === 1) {
            $this->written = true;
        }

        return $query;
    }

    /**
     * Purge the sources of the active redirections this request wrote.
     */
    public function purge(): void
    {
        if (!$this->written) {
            return;
        }

        $this->written = false;

        $wpdb = WP::db();

        // `>=` and not `>`: `updated` has a precision of one second, and a write in the
        // second the request started must be read. A row that another request wrote in
        // that second is read too, and purged again, which costs one render.
        /** @var list<string> $rows */
        $rows = $wpdb->get_col($wpdb->prepare(
            "SELECT sources FROM {$wpdb->prefix}" . self::TABLE . " WHERE status = 'active' AND updated >= %s",
            $this->since,
        ));

        $paths = [];

        foreach ($rows as $row) {
            $sources = maybe_unserialize($row);

            foreach (is_array($sources) ? $sources : [] as $source) {
                $source = is_array($source) ? $source : [];
                $pattern = (string) ($source['pattern'] ?? '');

                // A fragment, which no request carries: it matches nothing.
                if (str_starts_with($pattern, '#')) {
                    continue;
                }

                if (!self::isExact($source)) {
                    $this->invalidator->flush();

                    return;
                }

                // Rank Math stores an exact source without its leading slash.
                $paths[] = '/' . ltrim($pattern, '/');
            }
        }

        foreach (array_unique($paths) as $path) {
            $this->invalidator->forgetUrl(home_url($path));
        }
    }

    /**
     * Whether a source matches one path only: an exact source that does not ignore case.
     *
     * @param array<array-key, mixed> $source
     */
    private static function isExact(array $source): bool
    {
        return ($source['comparison'] ?? '') === 'exact' && ($source['ignore'] ?? '') !== 'case';
    }

    /**
     * An INSERT, UPDATE or REPLACE on the redirections table that sets `updated`.
     *
     * Not the `rank_math_redirections_cache` table beside it, and not the hit counter
     * that Rank Math updates on each redirect it serves, which does not set `updated`.
     */
    private function writePattern(): string
    {
        return (
            '/^\s*(?:INSERT|UPDATE|REPLACE)\b.*?\b'
            . preg_quote(WP::db()->prefix . self::TABLE, '/')
            . '\b.*\bupdated\b/is'
        );
    }
}
