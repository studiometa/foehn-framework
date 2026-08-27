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
 * **A repeated bare name is a bypass.** nginx's `$arg_page` is the first `page=`
 * in the query string and PHP's `$_GET['page']` is the last. There is no answer to
 * `?page=1&page=2` that both readers would give, so neither gives one.
 *
 * **A multi-value filter has two spellings, and they key to one file.** `?genre=rock,jazz`
 * is the comma form, which nginx reads with `$arg_genre` like any other value.
 * `?genre[]=rock&genre[]=jazz` is the form a checkbox group posts, and nginx cannot read
 * it at all — a variable name may not hold brackets, and there is no `$arg_genre[]`.
 *
 * That asymmetry is settled by nginx **declining** rather than guessing: a bracketed name
 * fails {@see \Studiometa\Foehn\PageCache\ServerConfig\SnippetPolicy::knownQueryPattern()},
 * so the request is passed to PHP, where this class joins the members in request order and
 * produces the key the comma form would have produced. The page is still served from cache
 * — by the drop-in rather than by nginx, a couple of milliseconds slower — and the file is
 * the same file. What never happens is nginx computing a key PHP disagrees with, which is
 * the only outcome that would serve one visitor another's page.
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
     *
     * The comma is in here so a filter with more than one value can be cached at all:
     * `?genre=rock,jazz` is the format the query filters emit, and without the comma it
     * was a value this cache refused — which made the framework's own documented filter
     * URLs the one shape its own page cache would not store.
     */
    public const VALUE_CHARACTER_CLASS = 'A-Za-z0-9_.,\-';

    /**
     * The characters one *member* of a multi-value argument may use.
     *
     * The comma is the separator, so a member may not contain one. This is not
     * tidiness: `?genre[]=rock,jazz` and `?genre=rock,jazz` would otherwise join to the
     * same key while asking for different things — the first for a single term whose
     * slug contains a comma, the second for two terms — and one visitor would get the
     * other's page.
     */
    public const MEMBER_CHARACTER_CLASS = 'A-Za-z0-9_.\-';

    /** Longest keyed value, so one long arg cannot push a filename past the 255 a filesystem takes. */
    public const VALUE_MAX_LENGTH = 64;

    private const VALUE_CHARSET = '#^[' . self::VALUE_CHARACTER_CLASS . ']{1,' . self::VALUE_MAX_LENGTH . '}$#';

    private const MEMBER_CHARSET = '#^[' . self::MEMBER_CHARACTER_CLASS . ']{1,' . self::VALUE_MAX_LENGTH . '}$#';

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

        /** @var array<string, list<string>> $plain */
        $plain = [];
        /** @var array<string, list<string>> $members */
        $members = [];

        foreach ($pairs as $pair) {
            // Never decoded. nginx does not decode `$args` or `$arg_name` either, and two
            // readers disagreeing about what `%75tm_source` means is worse than both
            // treating it as an argument they were not told about.
            [$rawName, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $name = self::bracketedName($rawName);
            $bracketed = $name !== null;
            $name ??= $rawName;

            if (array_key_exists($name, $keyed) && !$bracketed) {
                // Kept even when empty, because it is an *occurrence* that matters here
                // and not a value. `?page=&page=2` is the case: nginx reads the first
                // `page=`, finds it empty and serves the unpaginated file, while PHP
                // would key the second. Counting both is what refuses it.
                $plain[$name][] = $value;

                continue;
            }

            if (array_key_exists($name, $keyed)) {
                // An empty member is simply absent. Nothing can disagree about that: the
                // bracketed form is never keyed by nginx in the first place.
                if ($value !== '') {
                    $members[$name][] = $value;
                }

                continue;
            }

            // Matched against the name as written: `utm_source[]` is not `utm_source`,
            // and an argument this cache cannot name is one it does not serve.
            if (in_array($rawName, $ignored, true)) {
                continue;
            }

            return null;
        }

        $found = self::joinOccurrences($plain, $members, $keyed);

        if ($found === null) {
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
     * The value of a bare argument written once, or null when it was written twice.
     *
     * An empty string is not a refusal here: it is the answer for `?page=`, which counts
     * as absent.
     *
     * @param list<string> $values
     */
    private static function onlyOccurrence(array $values): ?string
    {
        return count($values) > 1 ? null : $values[0];
    }

    /**
     * The members of a bracketed argument as one comma-joined value, or null.
     *
     * @param list<string> $values
     */
    private static function joinedMembers(array $values): ?string
    {
        foreach ($values as $member) {
            if (preg_match(self::MEMBER_CHARSET, $member) !== 1) {
                return null;
            }
        }

        return implode(',', $values);
    }

    /**
     * The base name of a `name[]` argument, or null when the name is not bracketed.
     *
     * Both spellings a browser produces: a form serialises `genre[]` literally, and an
     * encoder that percent-escapes the brackets produces `genre%5B%5D`. The query string
     * is never decoded here, so both have to be recognised as written.
     */
    private static function bracketedName(string $rawName): ?string
    {
        $suffix = 0;

        if (str_ends_with($rawName, '[]')) {
            $suffix = 2;
        }

        if (str_ends_with(strtolower($rawName), '%5b%5d')) {
            $suffix = 6;
        }

        if ($suffix === 0) {
            return null;
        }

        $name = substr($rawName, 0, -$suffix);

        return $name === '' ? null : $name;
    }

    /**
     * One value per keyed name, or null when a spelling has no answer both readers share.
     *
     * The bracketed form is joined with commas **in request order and never sorted**, so
     * `?genre[]=rock&genre[]=jazz` keys exactly where `?genre=rock,jazz` does. Sorting
     * would be the obvious thing and is the wrong thing: nginx cannot sort, so a sorted
     * key is one only PHP could compute, and the two readers would part company on the
     * first URL that arrived unsorted.
     *
     * Three spellings get no key at all:
     *
     * **A repeated bare name.** nginx's `$arg_page` is the first `page=` and PHP's
     * `$_GET['page']` the last, so `?page=1&page=2` has no shared answer. Joining them
     * would invent one, and `page=1,2` is a key `?page=1,2` already means.
     *
     * **The two spellings mixed.** `?genre=rock&genre[]=jazz` is a request nobody meant.
     *
     * **A member holding the separator.** See {@see QueryKey::MEMBER_CHARACTER_CLASS}.
     *
     * @param array<string, list<string>> $plain
     * @param array<string, list<string>> $members
     * @param array<string, string> $keyed
     * @return array<string, string>|null
     */
    private static function joinOccurrences(array $plain, array $members, array $keyed): ?array
    {
        $found = [];

        foreach ($keyed as $name => $pattern) {
            $plainValues = $plain[$name] ?? [];
            $memberValues = $members[$name] ?? [];

            if ($plainValues !== [] && $memberValues !== []) {
                return null;
            }

            if ($plainValues === [] && $memberValues === []) {
                continue;
            }

            $value = $plainValues === [] ? self::joinedMembers($memberValues) : self::onlyOccurrence($plainValues);

            if ($value === null) {
                return null;
            }

            // One occurrence, and it was empty: absent, and the plain `index.html`.
            if ($value === '') {
                continue;
            }

            if (!self::valueIsAllowed($value, $pattern)) {
                return null;
            }

            $found[$name] = $value;
        }

        return $found;
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
