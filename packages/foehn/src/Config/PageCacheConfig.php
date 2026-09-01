<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Config;

use Studiometa\Foehn\Views\Sections\SectionRequest;

/**
 * The one description of what the page cache stores, and of what it refuses to store.
 *
 * Four readers serve this cache — the `advanced-cache.php` drop-in, a generated nginx
 * snippet, a generated `.htaccess` block, and the writer's own eligibility check — and
 * three of them cannot ask WordPress anything. They all derive from this object
 * instead: the drop-in requires the config file, and `wp foehn cache:config` renders
 * the server snippets from it. Three hand-written copies of "is this request
 * cacheable" that drift apart is the defect this design exists to avoid.
 *
 * Off by default. A cache nobody asked for is a bug, so a project enables it in the
 * environment it wants it in:
 *
 * ```php
 * // app/page-cache.production.config.php
 * use Studiometa\Foehn\Config\PageCacheConfig;
 *
 * return new PageCacheConfig(
 *     enabled: true,
 *     ttl: 8 * HOUR_IN_SECONDS,
 * );
 * ```
 */
final readonly class PageCacheConfig
{
    /**
     * The pattern a keyed query arg gets when the config names it without one.
     *
     * Deliberately narrow: the value becomes part of a filename, so this is the charset
     * a filename may hold and not the charset a URL may.
     */
    public const DEFAULT_QUERY_ARG_PATTERN = '^[A-Za-z0-9_.,\-]{1,64}$';

    /**
     * Control parameters that always bypass full-page caching.
     *
     * Projects cannot ignore or key these names. The PHP readers and generated server
     * rules all use the normalized getters below, so the reservation has one source.
     *
     * @var list<string>
     */
    public const RESERVED_QUERY_ARGS = [SectionRequest::PARAMETER];

    public function __construct(
        /** Master switch. Off by default: a cache nobody asked for is a bug. */
        public bool $enabled = false,

        /** Cache root. Defaults to WP_CONTENT_DIR . '/cache/foehn/pages'. */
        public ?string $path = null,

        /** Seconds a file stays servable. 0 = until something purges it. */
        public int $ttl = 0,

        /** max-age sent to the browser for cached HTML. 0 + must-revalidate keeps purges instant. */
        public int $browserMaxAge = 0,

        /**
         * Environments where caching is allowed at all.
         *
         * @var list<string>
         */
        public array $environments = ['production'],

        /**
         * A request carrying one of these cookie prefixes is never served or written.
         *
         * @var list<string>
         */
        public array $bypassCookies = ['wordpress_logged_in_', 'comment_author_', 'wp-postpass_'],

        /**
         * Stripped before keying, so tracking parameters still hit.
         *
         * @var list<string>
         */
        public array $ignoredQueryArgs = [
            'utm_source',
            'utm_medium',
            'utm_campaign',
            'utm_term',
            'utm_content',
            'utm_id',
            'gclid',
            'fbclid',
            'msclkid',
            'mc_cid',
            'mc_eid',
            '_ga',
            'ref',
        ],

        /**
         * Args that change which page is being asked for, so they belong in the key.
         *
         * A name may be given three ways, because the value ends up in a filename and
         * three is how much detail that needs:
         *
         * ```php
         * cacheQueryArgs: [
         *     'page',                                     // any value the charset allows
         *     'lang' => ['fr', 'en'],                     // only these values
         *     'genre' => '^[a-z0-9-]+(?:,[a-z0-9-]+)*$',  // a pattern, for the rest
         * ],
         * ```
         *
         * A bare name gets {@see PageCacheConfig::DEFAULT_QUERY_ARG_PATTERN}. A list of
         * values becomes the pattern that matches exactly those, which is the form to
         * reach for: it says what a project actually knows — the four sort orders, the
         * three page sizes — without anybody writing a regex, and a value outside it is a
         * bypass rather than a file.
         *
         * A present-but-invalid value is a bypass, never a sanitised guess. Leave this
         * empty unless a query argument really does change the page: every name here is
         * a name the generated snippets have to unroll, and pagination already travels
         * as `/page/2/` rather than as `?page=2`.
         *
         * @var array<string, string|list<scalar>>|list<string>
         */
        public array $cacheQueryArgs = [],

        /**
         * URL path prefixes never cached.
         *
         * @var list<string>
         */
        public array $excludedPaths = [],

        /**
         * Response bodies containing one of these substrings are not cached.
         *
         * @var list<string>
         */
        public array $excludeWhenBodyContains = [],

        /**
         * Cache 404s.
         *
         * Stored under their own filename — `index--404.html` — so both readers can tell
         * them from a page, and served with a 404 status by the drop-in. nginx does not
         * serve them at all: it never looks for that name, so the request reaches PHP,
         * which is the only reader that can set a status on a stored file.
         *
         * They share the `ttl` of everything else. An earlier version of this note
         * promised a shorter bucket of their own; there has never been one.
         *
         * Off by default is also what stops an attacker filling the disk with a
         * directory per made-up URL. Turning it on wants a bound on the entry count.
         */
        public bool $cacheNotFound = false,

        /** Emit X-Foehn-Cache headers. Defaults to WP_DEBUG. */
        public ?bool $debugHeaders = null,
    ) {}

    /**
     * The keyed query args, normalised to `name => pattern` and **sorted by name**.
     *
     * The sort is the whole point, not tidiness. Every reader builds a filename by
     * walking this list in order and asking for each name's value, which is how
     * `?page=2&lang=fr` and `?lang=fr&page=2` reach one file without anybody sorting a
     * query string — nginx cannot. Change the order here and you have renamed every
     * stored file, so this is the one place it is decided.
     *
     * Entries this cache cannot honour are dropped rather than repaired: an unusable
     * name is then simply an argument nobody configured, which is a bypass. A pattern
     * is dropped if it cannot compile, and `#` is refused because it is the delimiter.
     *
     * @return array<string, string>
     */
    public function getCacheQueryArgs(): array
    {
        $normalized = [];

        foreach (self::withPatterns($this->cacheQueryArgs) as $name => $pattern) {
            if (in_array($name, self::RESERVED_QUERY_ARGS, true)) {
                continue;
            }

            if (preg_match('/^[A-Za-z0-9_-]{1,32}$/', $name) !== 1) {
                continue;
            }

            if (str_contains($pattern, '#') || !self::patternCompiles($pattern)) {
                continue;
            }

            $normalized[$name] = $pattern;
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * `cacheQueryArgs` reduced to `name => pattern`, whichever way it was written.
     *
     * A list gives `0 => 'page'`, a map gives `'page' => '^\d+$'`, and a value list gives
     * `'lang' => ['fr', 'en']`. The value list is the interesting one: it compiles to the
     * pattern matching exactly those values, so a project states the four sort orders it
     * supports rather than a regex that means the same thing, and there is still one
     * shape — a pattern — for the readers and the snippet generators to agree on.
     *
     * @param array<string, string|list<scalar>>|list<string> $args
     * @return array<string, string>
     */
    private static function withPatterns(array $args): array
    {
        $normalized = [];

        foreach ($args as $key => $value) {
            if (is_int($key)) {
                // `['page', 'lang']` — a bare name and nothing else. A list entry that is
                // not a name (`[['fr', 'en']]`, a value list nobody gave a name to) is
                // dropped here, and is then an argument nobody configured: a bypass.
                if (is_string($value)) {
                    $normalized[$value] = self::DEFAULT_QUERY_ARG_PATTERN;
                }

                continue;
            }

            $normalized[$key] = is_array($value) ? self::patternForValues($value) : $value;
        }

        return $normalized;
    }

    /**
     * The pattern matching exactly the listed values, and nothing else.
     *
     * Every value is quoted, so a `.` in one is a dot and not "any character" — a config
     * file lists values, and a value that quietly widened into a pattern would key pages
     * this cache was never told to key.
     *
     * An empty list compiles to a pattern nothing matches. That is the honest reading of
     * "these values are allowed" with none named, and it makes the argument a bypass
     * rather than silently letting anything through.
     *
     * @param list<scalar> $values
     */
    private static function patternForValues(array $values): string
    {
        if ($values === []) {
            return '^(?!)$';
        }

        $quoted = array_map(static fn(string|int|float|bool $value): string => preg_quote(
            (string) $value,
            '#',
        ), $values);

        return '^(?:' . implode('|', $quoted) . ')$';
    }

    /**
     * Whether a configured pattern compiles, without emitting a warning if it does not.
     *
     * A bad pattern in a config file is a thing to drop, not a thing to warn about on
     * every request — and `@` would not do: a suppressed diagnostic still reaches a custom
     * error handler, which under `failOnWarning` fails the suite. The handler is restored
     * in a `finally` because an unpaired `restore_error_handler()` fails it too.
     */
    private static function patternCompiles(string $pattern): bool
    {
        set_error_handler(static fn(): bool => true);

        try {
            preg_match('#' . $pattern . '#', '');
        } finally {
            restore_error_handler();
        }

        // `preg_last_error()` rather than the return value: a compilation failure returns
        // false, but static analysis reads `preg_match()` as returning an int and then
        // proves the comparison always true.
        return preg_last_error() === PREG_NO_ERROR;
    }

    /**
     * The ignored query args, minus any name that is also keyed.
     *
     * A name in both lists is a contradiction in a config file, and the keyed meaning is
     * the specific one. Resolving it here rather than at each call site is what stops the
     * writer dropping an argument the snippet keys, which would serve the wrong page.
     *
     * @return list<string>
     */
    public function getIgnoredQueryArgs(): array
    {
        $keyed = $this->getCacheQueryArgs();

        return array_values(array_filter(
            $this->ignoredQueryArgs,
            static fn(string $name): bool => (
                !in_array($name, self::RESERVED_QUERY_ARGS, true) && !array_key_exists($name, $keyed)
            ),
        ));
    }

    /**
     * The cache root, resolved.
     */
    public function getPath(): string
    {
        if ($this->path !== null) {
            return rtrim($this->path, '/');
        }

        if (defined('WP_CONTENT_DIR')) {
            return rtrim((string) constant('WP_CONTENT_DIR'), '/') . '/cache/foehn/pages';
        }

        // Non-WordPress context (tests).
        return sys_get_temp_dir() . '/foehn/pages';
    }

    /**
     * Whether the X-Foehn-Cache headers are emitted.
     *
     * Without them this feature is undebuggable in production and the smoke test has
     * nothing to assert, so debug builds get them for free.
     */
    public function wantsDebugHeaders(): bool
    {
        if ($this->debugHeaders !== null) {
            return $this->debugHeaders;
        }

        return defined('WP_DEBUG') && (bool) constant('WP_DEBUG');
    }

    /**
     * Whether caching is allowed in the environment the site reports.
     */
    public function allowsEnvironment(?string $environment = null): bool
    {
        return in_array($environment ?? self::environment(), $this->environments, true);
    }

    /**
     * The environment, resolved without needing WordPress to be loaded.
     *
     * The drop-in runs from `wp-settings.php`, after `wp-config.php` has defined
     * `WP_ENVIRONMENT_TYPE` and after `wp-includes/load.php` has defined
     * `wp_get_environment_type()`, so both are usually available. The fallbacks are
     * for the cases where neither is — and default to production, as WordPress does.
     */
    public static function environment(): string
    {
        if (function_exists('wp_get_environment_type')) {
            return wp_get_environment_type();
        }

        if (defined('WP_ENVIRONMENT_TYPE')) {
            $type = (string) constant('WP_ENVIRONMENT_TYPE');

            if ($type !== '') {
                return $type;
            }
        }

        $type = getenv('WP_ENVIRONMENT_TYPE');

        return is_string($type) && $type !== '' ? $type : 'production';
    }
}
