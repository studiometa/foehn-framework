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
 * One non-autoloaded option for each URL, named after a hash of the URL, with the date and
 * the URL as value. Not one shared array: two renders that read and write back the same
 * array at the same time keep only the last write, and a URL lost that way is never
 * purged, because its stored page is then served with no render to record it again.
 *
 * The value is the date, zero-padded to {@see self::DATE_DIGITS} digits, a space and the
 * URL, so that the database orders two values of a URL by their date. This lets one
 * statement keep the earliest date (see {@see self::record()}).
 */
final readonly class ScheduleStore
{
    /** The start of the name of each option. */
    public const string PREFIX = 'foehn_page_cache_schedule_';

    /** The width of the date at the start of a value: the digits of `PHP_INT_MAX`. */
    public const int DATE_DIGITS = 19;

    /**
     * Keep the date at which a URL goes stale.
     *
     * A URL keeps its earliest date, also when that date has passed: the purge is then
     * pending, and the stored page stays wrong until {@see ScheduledPurge} runs. The job
     * removes the record before it deletes the page, so a render after that records its
     * next date again.
     *
     * One `INSERT … ON DUPLICATE KEY UPDATE` adds the option, or keeps the smaller of the
     * stored value and the new one. The database runs it atomically on the row, so two
     * renders of the same URL at the same time cannot replace an earlier date with a later
     * one, as a read followed by `update_option()` could. It runs once each time the page
     * cache stores the page, not on each request.
     */
    public function record(string $url, int $at): void
    {
        if (!function_exists('wp_cache_delete')) {
            return;
        }

        $wpdb = WP::db();
        $option = self::option($url);

        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')"
            . ' ON DUPLICATE KEY UPDATE option_value = LEAST(option_value, VALUES(option_value))',
            $option,
            self::value($url, $at),
        ));

        // The write bypasses the options API, so clear what get_option() keeps of the option.
        wp_cache_delete($option, 'options');
        /** @var array<string, bool>|false $notoptions */
        $notoptions = wp_cache_get('notoptions', 'options');

        if (is_array($notoptions) && array_key_exists($option, $notoptions)) {
            unset($notoptions[$option]);
            wp_cache_set('notoptions', $notoptions, 'options');
        }
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
        if (!function_exists('wp_cache_delete')) {
            return [];
        }

        $wpdb = WP::db();
        $values = $wpdb->get_col($wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like(self::PREFIX) . '%',
        ));

        $entries = [];

        foreach ($values as $value) {
            $entry = self::entry($value);

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
     * The value of the option of a URL: the zero-padded date, a space and the URL.
     */
    public static function value(string $url, int $at): string
    {
        return sprintf('%0' . self::DATE_DIGITS . 'd %s', max(0, $at), $url);
    }

    /**
     * A stored record, or null when the value is not one.
     *
     * @return array{url: string, at: int}|null
     */
    private static function entry(mixed $value): ?array
    {
        $match = [];

        if (!is_string($value) || preg_match('/^(\d{' . self::DATE_DIGITS . '}) (.+)$/s', $value, $match) !== 1) {
            return null;
        }

        return ['url' => $match[2], 'at' => (int) $match[1]];
    }
}
