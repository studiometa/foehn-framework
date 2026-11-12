<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Config;

use InvalidArgumentException;
use Studiometa\Foehn\Helpers\Env;
use Studiometa\Foehn\PageCache\StoredHeaders;
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
     * Control parameters the framework owns, whatever a project's config says.
     *
     * A reserved name is keyed by {@see PageCacheConfig::getCacheQueryArgs()} with the
     * framework's own pattern and is refused by {@see PageCacheConfig::getIgnoredQueryArgs()}.
     * Ignoring is the dangerous half: an ignored `foehn_sections` keys a section request
     * where the whole page lives, so the next visitor asking for a fragment is handed the
     * page, and the next visitor asking for the page is handed a fragment.
     *
     * A project cannot supply its own pattern for one either. The grammar is the parser's
     * — see {@see SectionRequest::VALUE_PATTERN} — and a widened one would key values the
     * parser refuses.
     *
     * The PHP readers and the generated server rules all use the normalized getters below,
     * so the reservation has one source.
     *
     * @var list<string>
     */
    public const RESERVED_QUERY_ARGS = [SectionRequest::PARAMETER];

    public function __construct(
        /** Master switch. Off by default: a cache nobody asked for is a bug. */
        public bool $enabled = false,

        /** Cache root. Defaults to WP_CONTENT_DIR . '/cache/foehn/pages'. Not used in bucket storage. */
        public ?string $path = null,

        /**
         * Where pages are stored. Local disk by default.
         *
         * `PageCacheStorage::Bucket` stores them in the `humanmade/s3-uploads` bucket,
         * under `cache/pages/`, and nginx serves hits from there. Use it when the site
         * runs on more than one instance. The drop-in does not read in this mode, and
         * Apache is not supported.
         */
        public PageCacheStorage $storage = PageCacheStorage::Disk,

        /** Seconds a file stays servable. 0 = until something purges it. */
        public int $ttl = 0,

        /** max-age sent to the browser for cached HTML. 0 + must-revalidate keeps purges instant. */
        public int $browserMaxAge = 0,

        /**
         * The whole `Cache-Control` value of a hit, in place of the one built from `browserMaxAge`.
         *
         * Null keeps `public, max-age=<browserMaxAge>, must-revalidate`. Sent by the drop-in
         * and written into the nginx snippet, so the two readers cannot disagree.
         */
        public ?string $cacheControl = null,

        /**
         * Static headers sent on every hit, by the drop-in and by nginx.
         *
         * For the headers a server sets at server level: an nginx `location` with its own
         * `add_header` drops every `add_header` of the `server`, and the cache location has
         * its own. Not sent on a page PHP renders, which already gets them from the server.
         *
         * @var array<string, string>
         */
        public array $responseHeaders = [],

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

        /**
         * Warm the pages a purge removed, through Action Scheduler.
         *
         * After a content change, each purged URL gets a `WarmUrl` job. After a full
         * flush, one job warms every sitemap URL instead.
         */
        public bool $warmAfterPurge = false,

        /**
         * The sitemap or sitemap index to warm from, as a full URL.
         *
         * `null` reads the core WordPress sitemap. Set it when a plugin replaces that
         * sitemap, for example `https://example.com/sitemap_index.xml`.
         */
        public ?string $warmSitemap = null,

        /**
         * URLs never warmed: regular expressions without delimiters, matched anywhere in
         * the full URL. `'/test-form/'` excludes every URL that contains that path.
         *
         * @var list<string>
         */
        public array $warmExclude = [],
    ) {}

    /**
     * Whether a cache key path is under one of the `excludedPaths` prefixes.
     *
     * A prefix matches whole segments: `/contact/` excludes `/contact` and
     * `/contact/thanks/`, not `/contacts/`.
     */
    public function excludesPath(string $path): bool
    {
        foreach ($this->excludedPaths as $prefix) {
            $normalized = '/' . trim($prefix, '/');

            if ($normalized === '/' || str_starts_with(rtrim($path, '/') . '/', $normalized . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a URL matches one of the `warmExclude` patterns.
     *
     * A pattern that does not compile, or that holds the `#` delimiter, matches
     * nothing, like an unusable keyed query arg.
     */
    public function excludesFromWarmup(string $url): bool
    {
        foreach ($this->warmExclude as $pattern) {
            if (str_contains($pattern, '#') || !self::patternCompiles($pattern)) {
                continue;
            }

            if (preg_match('#' . $pattern . '#', $url) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * The headers a project may not set through `responseHeaders`.
     *
     * The ones the cache computes for the file it sends, or never sends from a stored page
     * ({@see StoredHeaders::DENIED}), and `X-Robots-Tag`, which nginx derives for a section
     * response: a configured one would remove the `noindex` of a cached fragment.
     *
     * @var list<string>
     */
    public const OWNED_RESPONSE_HEADERS = [...StoredHeaders::DENIED, 'x-robots-tag'];

    /**
     * A header name nginx can write without quotes.
     */
    private const RESPONSE_HEADER_NAME = '/^[A-Za-z0-9-]+$/D';

    /**
     * A header value nginx can write between double quotes, and PHP can send as it is.
     *
     * Printable ASCII without `"`, which ends the nginx string, `\\`, which escapes in it,
     * and `$`, which nginx reads as a variable. No control character, so no line break
     * can start a second header or a second nginx directive. A `;` stays allowed: it ends
     * a directive only outside quotes, and `X-XSS-Protection: 1; mode=block` needs it.
     */
    private const RESPONSE_HEADER_VALUE = '/^[\x20\x21\x23\x25-\x5B\x5D-\x7E]+$/D';

    /**
     * The `Cache-Control` value of a hit.
     *
     * @throws InvalidArgumentException When `cacheControl` cannot be written safely into
     *     the nginx snippet or sent by the drop-in.
     */
    public function getCacheControl(): string
    {
        if ($this->cacheControl === null) {
            return sprintf('public, max-age=%d, must-revalidate', max(0, $this->browserMaxAge));
        }

        self::assertHeaderValue('cacheControl', $this->cacheControl);

        return $this->cacheControl;
    }

    /**
     * The static headers of a hit, validated.
     *
     * Validated here and not in the constructor, so that a bad value fails
     * `wp foehn cache:config` and stops the drop-in from serving, but never stops
     * WordPress from booting.
     *
     * @return array<string, string>
     * @throws InvalidArgumentException When a name or a value cannot be written safely
     *     into the nginx snippet or sent by the drop-in, or names a header the cache owns.
     */
    public function getResponseHeaders(): array
    {
        $seen = [];

        foreach ($this->responseHeaders as $name => $value) {
            // A config file can give a list of `Name: value` lines, whose keys are integers.
            // @mago-expect analysis:redundant-logical-operation
            if (!is_string($name) || preg_match(self::RESPONSE_HEADER_NAME, $name) !== 1) {
                throw new InvalidArgumentException(sprintf(
                    'responseHeaders: "%s" is not a header name. Use [\'Name\' => \'value\'], with letters, digits and hyphens in the name.',
                    $name,
                ));
            }

            if (in_array(strtolower($name), self::OWNED_RESPONSE_HEADERS, true)) {
                throw new InvalidArgumentException(sprintf(
                    'responseHeaders: the page cache sets "%s" itself.%s',
                    $name,
                    strtolower($name) === 'cache-control' ? ' Use cacheControl.' : '',
                ));
            }

            // nginx sends both spellings of one name, and the drop-in only the last one.
            if (array_key_exists(strtolower($name), $seen)) {
                throw new InvalidArgumentException(sprintf(
                    'responseHeaders: "%s" is listed twice. Header names do not depend on case.',
                    $name,
                ));
            }

            $seen[strtolower($name)] = true;

            self::assertHeaderValue('responseHeaders: ' . $name, $value);
        }

        return $this->responseHeaders;
    }

    /**
     * @throws InvalidArgumentException When the value is not one {@see self::RESPONSE_HEADER_VALUE} accepts.
     */
    private static function assertHeaderValue(string $option, mixed $value): void
    {
        if (!is_string($value) || trim($value) !== $value || preg_match(self::RESPONSE_HEADER_VALUE, $value) !== 1) {
            throw new InvalidArgumentException(sprintf(
                '%s: the value must be printable ASCII without ", \\ or $, and without spaces at the start or the end.',
                $option,
            ));
        }
    }

    /**
     * The name a keyed arg gets when a webserver holds it in a variable: `a-b` becomes `a_b`.
     *
     * A keyed name may hold a hyphen — a taxonomy registered as `product-type` has a
     * query var spelled that way — and an nginx variable name may not: `$foehn_val_a-b`
     * fails `nginx -t`, and `$arg_a-b` is read as `$arg_a` followed by the literal `-b`.
     * The hyphen is the only character a name admits that a variable does not, so one
     * replacement is the whole rule. It is not injective — `a-b` and `a_b` meet here —
     * which is why {@see self::getCacheQueryArgs()} keys neither of a pair that would.
     *
     * Decided here and not in the snippet generators, because it is this configuration
     * that has to refuse the colliding pair: the generators only spell what it kept.
     */
    public static function variableName(string $name): string
    {
        return str_replace('-', '_', $name);
    }

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
     * Two names nginx would hold in one variable — `a-b` and `a_b`, see
     * {@see self::variableName()} — are both dropped, since a snippet keying one of
     * them would read the other's value as its own.
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

        // Always keyed, and never from the config file. A section request returns the
        // HTML of named regions rather than a page, so it is a different response for
        // the same URL and needs a filename of its own —
        // `index__foehn_sections=listing,pagination&.html`. Keying it is what makes
        // filtering and paginating through sections come off a file like everything else.
        //
        // The key space is bounded for free: a name nothing declared makes
        // `SectionRequest` invalid, which answers 400, and only 200s are ever stored. So
        // the files that can exist are the section combinations the templates declare,
        // and no crawler can add to them.
        $normalized[SectionRequest::PARAMETER] = SectionRequest::VALUE_PATTERN;

        // A hyphen is the one character a name may hold and an nginx variable may not, so
        // `a-b` and `a_b` are one `$foehn_val_a_b` in the snippet — and the second `set`
        // would overwrite the first, keying a page under the wrong filter. Neither name is
        // keyed then, which is a bypass for both. The reserved name is never the one to
        // go: a project's `foehn-sections` yields to it.
        $variables = array_count_values(array_map(self::variableName(...), array_keys($normalized)));
        $normalized = array_filter(
            $normalized,
            static fn(string $name): bool => (
                $variables[self::variableName($name)] === 1
                || in_array($name, self::RESERVED_QUERY_ARGS, true)
            ),
            ARRAY_FILTER_USE_KEY,
        );

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
     * The ignored query args, minus any name that is also keyed, minus the reserved ones.
     *
     * A name in both lists is a contradiction in a config file, and the keyed meaning is
     * the specific one. Resolving it here rather than at each call site is what stops the
     * writer dropping an argument the snippet keys, which would serve the wrong page.
     *
     * The reserved names are refused explicitly rather than left to the keyed check, even
     * though every one of them is keyed: this is the rule that must not quietly stop
     * holding, and {@see PageCacheConfig::RESERVED_QUERY_ARGS} says why.
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

        return Env::isDebug();
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
     * Delegated to {@see Env} rather than answered here, so the cache and the
     * operational features cannot read one site as two different environments. The
     * drop-in runs from `wp-settings.php` and reaches this before
     * `wp_get_environment_type()` exists, which is why `Env` resolves the constant and
     * the environment variable as well.
     */
    public static function environment(): string
    {
        return Env::get();
    }
}
