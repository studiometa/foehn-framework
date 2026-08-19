<?php

declare(strict_types=1);

namespace Studiometa\Foehn\PageCache;

use Studiometa\Foehn\Config\PageCacheConfig;

/**
 * The query string turned into the one filename suffix every reader can compute.
 *
 * A query string is three kinds of argument at once. One is noise a campaign link
 * carries and the page does not read (`utm_source`); one changes which page is being
 * asked for (`page`, `lang`); the rest is anything this cache has not been told about.
 * Noise is dropped, the second kind is keyed, and an unknown argument is a bypass —
 * because a cache that guesses which of the three an argument is will eventually serve
 * page one of an archive to somebody who asked for page two.
 *
 * **Order cannot matter, and no reader can sort.** `?page=2&lang=fr` and
 * `?lang=fr&page=2` are one page, so they have to be one file, and nginx has no sorting
 * primitive. The way out is to never read the query string left to right: every reader
 * walks the configured argument names in one fixed order — the order
 * {@see PageCacheConfig::getCacheQueryArgs()} sorts them into — and asks for each name's
 * value in turn. nginx can do that, because `$arg_page` is independent of where `page`
 * appeared. So the canonical order is a property of the configuration rather than of the
 * request, and the generated snippet is that same list unrolled into `if` statements.
 *
 * Two rules exist purely so the readers cannot disagree:
 *
 * **An empty value counts as absent.** `?page=` keys as no query at all.
 *
 * **A repeated keyed argument is a bypass.** nginx's `$arg_page` is the first `page=`
 * in the query string and PHP's `$_GET['page']` is the last. There is no answer to
 * `?page=1&page=2` that both readers would give, so neither gives one.
 */
final readonly class QueryKey
{
    /**
     * The characters a keyed value may use, whatever pattern a project adds on top.
     *
     * A project's own pattern can only narrow this, never widen it: the value becomes
     * part of a filename, and a config file is not the place from which to extend what
     * this cache is willing to write to disk.
     *
     * Exposed as its parts because the nginx generator has to state the same floor, and
     * two spellings of it would be two rules.
     */
    public const VALUE_CHARACTER_CLASS = 'A-Za-z0-9_.\-';

    /** Longest keyed value, so one long arg cannot push a filename past the 255 a filesystem takes. */
    public const VALUE_MAX_LENGTH = 64;

    private const VALUE_CHARSET = '#^[' . self::VALUE_CHARACTER_CLASS . ']{1,' . self::VALUE_MAX_LENGTH . '}$#';

    /**
     * The canonical suffix for a query string, or null when the request must bypass.
     *
     * An empty string is not a bypass — it is the answer for a request whose query
     * string does not change which page it is, and it keys to the plain `index.html`.
     *
     * @param string $query The raw query string, without its leading `?`.
     */
    public static function canonical(string $query, PageCacheConfig $config): ?string
    {
        $query = explode('#', $query, 2)[0];

        if ($query === '') {
            return '';
        }

        $keyed = $config->getCacheQueryArgs();
        $ignored = $config->getIgnoredQueryArgs();
        $pairs = array_filter(explode('&', $query), static fn(string $pair): bool => $pair !== '');

        if (self::hasRepeatedName($pairs, $keyed)) {
            return null;
        }

        $found = [];

        foreach ($pairs as $pair) {
            // Never decoded. nginx does not decode `$args` or `$arg_name` either, and two
            // readers disagreeing about what `%75tm_source` means is worse than both
            // treating it as an argument they were not told about.
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');

            if (array_key_exists($name, $keyed)) {
                if ($value === '') {
                    continue;
                }

                if (!self::valueIsAllowed($value, $keyed[$name])) {
                    return null;
                }

                $found[$name] = $value;

                continue;
            }

            if (in_array($name, $ignored, true)) {
                continue;
            }

            return null;
        }

        if ($found === []) {
            return '';
        }

        // The configuration's order, not the request's. `getCacheQueryArgs()` is sorted,
        // so walking it is what makes the two spellings of one URL agree.
        $variant = '';

        foreach (array_keys($keyed) as $name) {
            if (!array_key_exists($name, $found)) {
                continue;
            }

            $variant .= $name . '=' . $found[$name] . '&';
        }

        return $variant;
    }

    /**
     * Whether a keyed argument appears more than once, at any value including none.
     *
     * `?page=&page=2` counts: nginx would read the empty first one, find no variant and
     * serve the unpaginated page, while PHP would key the second one and store page two
     * under a different name.
     *
     * @param list<string> $pairs
     * @param array<string, string> $keyed
     */
    private static function hasRepeatedName(array $pairs, array $keyed): bool
    {
        $seen = [];

        foreach ($pairs as $pair) {
            $name = explode('=', $pair, 2)[0];

            if (!array_key_exists($name, $keyed)) {
                continue;
            }

            if (array_key_exists($name, $seen)) {
                return true;
            }

            $seen[$name] = true;
        }

        return false;
    }

    /**
     * Whether a value passes both the charset this cache imposes and the project's own.
     */
    private static function valueIsAllowed(string $value, string $pattern): bool
    {
        if (preg_match(self::VALUE_CHARSET, $value) !== 1) {
            return false;
        }

        return preg_match('#' . $pattern . '#', $value) === 1;
    }
}
