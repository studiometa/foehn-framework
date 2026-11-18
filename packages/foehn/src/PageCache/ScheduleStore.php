<?php

declare(strict_types=1);

namespace Studiometa\Foehn\PageCache;

use Studiometa\Foehn\Helpers\WP;

/**
 * The pages that go stale at a known date, and that date.
 *
 * A page that renders a scheduled block (see {@see \Studiometa\Foehn\Blocks\BlockSchedule})
 * shows different markup once the block's `visibleFrom` or `visibleUntil` date passes.
 * No content edit happens at that moment, so no purge hook fires. This keeps the URL and
 * the date, and {@see ScheduledPurge} deletes the stored page when the date has passed.
 *
 * One non-autoloaded option for each URL, named after a hash of the URL, with the URL and
 * its date as value. Not one shared array: two renders that read and write back the same
 * array at the same time keep only the last write, and a URL lost that way is never
 * purged, because its stored page is then served with no render to record it again.
 */
final readonly class ScheduleStore
{
    /** The start of the name of each option. */
    public const string PREFIX = 'foehn_page_cache_schedule_';

    /**
     * Keep the date at which a URL goes stale.
     *
     * A URL keeps its earliest date, also when that date has passed: the purge is then
     * pending, and the stored page stays wrong until {@see ScheduledPurge} runs. The job
     * removes the record before it deletes the page, so a render after that records its
     * next date again. Nothing is written when the stored date already covers the new
     * one, so a page rendered again and again costs one option read.
     */
    public function record(string $url, int $at): void
    {
        if (!function_exists('get_option')) {
            return;
        }

        $option = self::option($url);
        $stored = self::entry(get_option($option));

        if ($stored !== null && $stored['at'] <= $at) {
            return;
        }

        update_option($option, ['url' => $url, 'at' => $at], false);
    }

    /**
     * The URLs whose date has passed.
     *
     * @return list<string>
     */
    public function due(?int $now = null): array
    {
        $now ??= time();
        $urls = [];

        foreach ($this->all() as $url => $at) {
            if ($at > $now) {
                continue;
            }

            $urls[] = $url;
        }

        sort($urls);

        return $urls;
    }

    /**
     * Remove the records of these URLs.
     *
     * A due record is never written again (see {@see self::record()}), so the record
     * removed here is the one {@see self::due()} read.
     *
     * @param list<string> $urls
     */
    public function forget(array $urls): void
    {
        if (!function_exists('delete_option')) {
            return;
        }

        foreach ($urls as $url) {
            delete_option(self::option($url));
        }
    }

    /**
     * Every recorded URL and its date, read with one query.
     *
     * @return array<string, int>
     */
    public function all(): array
    {
        if (!function_exists('maybe_unserialize')) {
            return [];
        }

        $wpdb = WP::db();
        $values = $wpdb->get_col($wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like(self::PREFIX) . '%',
        ));

        $entries = [];

        foreach ($values as $value) {
            $entry = self::entry(maybe_unserialize($value));

            if ($entry === null) {
                continue;
            }

            $entries[$entry['url']] = $entry['at'];
        }

        return $entries;
    }

    /**
     * The name of the option that holds the record of a URL.
     */
    public static function option(string $url): string
    {
        return self::PREFIX . md5($url);
    }

    /**
     * A stored record, or null when the value is not one.
     *
     * @return array{url: string, at: int}|null
     */
    private static function entry(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        $url = $value['url'] ?? null;
        $at = $value['at'] ?? null;

        if (!(is_string($url) && is_numeric($at))) {
            return null;
        }

        return ['url' => $url, 'at' => (int) $at];
    }
}
