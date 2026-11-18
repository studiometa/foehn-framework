<?php

declare(strict_types=1);

namespace Studiometa\Foehn\PageCache;

/**
 * The pages that go stale at a known date, and that date.
 *
 * A page that renders a scheduled block (see {@see \Studiometa\Foehn\Blocks\BlockSchedule})
 * shows different markup once the block's `visibleFrom` or `visibleUntil` date passes.
 * No content edit happens at that moment, so no purge hook fires. This keeps the URL and
 * the date, and {@see ScheduledPurge} deletes the stored page when the date has passed.
 *
 * One option, URL => Unix timestamp, not autoloaded: only the recorder and the job read it.
 */
final readonly class ScheduleStore
{
    public const string OPTION = 'foehn_page_cache_schedule';

    /**
     * Keep the date at which a URL goes stale.
     *
     * A URL keeps its earliest date, unless that date has passed: then the page was
     * purged or is about to be, and the new date is the one that matters next. Nothing is
     * written when the stored date already covers the new one, so a page rendered again
     * and again costs one option read.
     */
    public function record(string $url, int $at, ?int $now = null): void
    {
        $entries = $this->all();
        $stored = $entries[$url] ?? null;

        if ($stored !== null && $stored > ($now ?? time()) && $stored <= $at) {
            return;
        }

        $entries[$url] = $at;
        $this->save($entries);
    }

    /**
     * The URLs whose date has passed.
     *
     * @return list<string>
     */
    public function due(?int $now = null): array
    {
        $now ??= time();

        $urls = array_keys(array_filter($this->all(), static fn(int $at): bool => $at <= $now));
        sort($urls);

        return $urls;
    }

    /**
     * Remove the URLs whose date has passed, and keep the others.
     *
     * Only the due ones: a URL rendered again since {@see self::due()} has a new, later
     * date, and that one must stay.
     *
     * @param list<string> $urls
     */
    public function forget(array $urls, ?int $now = null): void
    {
        $now ??= time();
        $entries = $this->all();

        foreach ($urls as $url) {
            if (($entries[$url] ?? null) === null || $entries[$url] > $now) {
                continue;
            }

            unset($entries[$url]);
        }

        $this->save($entries);
    }

    /**
     * Every recorded URL and its date.
     *
     * @return array<string, int>
     */
    public function all(): array
    {
        if (!function_exists('get_option')) {
            return [];
        }

        $value = get_option(self::OPTION, []);

        if (!is_array($value)) {
            return [];
        }

        $entries = [];

        foreach ($value as $url => $at) {
            if (!(is_string($url) && is_numeric($at))) {
                continue;
            }

            $entries[$url] = (int) $at;
        }

        return $entries;
    }

    /**
     * @param array<string, int> $entries
     */
    private function save(array $entries): void
    {
        if (!function_exists('update_option')) {
            return;
        }

        if ($entries === []) {
            delete_option(self::OPTION);

            return;
        }

        update_option(self::OPTION, $entries, false);
    }
}
