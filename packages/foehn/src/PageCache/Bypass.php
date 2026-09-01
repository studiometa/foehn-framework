<?php

declare(strict_types=1);

namespace Studiometa\Foehn\PageCache;

use Studiometa\Foehn\Config\PageCacheConfig;
use Studiometa\Foehn\Views\Sections\SectionRequest;

/**
 * The one answer to "may this request be cached?", for every reader that can ask it.
 *
 * The failure mode of a page cache is not slowness, it is serving the wrong HTML to
 * the wrong visitor — someone else's logged-in header, a dead nonce, a stale cart. So
 * there is one implementation of the rules and one test suite over it, rather than one
 * copy in the writer and another in the drop-in that drift apart over a year.
 *
 * Three entry points, ordered by what the caller is allowed to know:
 *
 * - {@see forRequest()} reads the superglobals and the filesystem only. Safe from
 *   `advanced-cache.php`, before WordPress exists.
 * - {@see forContext()} adds the questions only a booted WordPress can answer. The
 *   writer calls it on `template_redirect`, before opening a buffer it would throw
 *   away — a feed or a REST response is never worth wrapping in `ob_start()`.
 * - {@see forResponse()} adds the status, the headers and the body, and calls both of
 *   the others. It is the last word before a file is written.
 */
final readonly class Bypass
{
    /**
     * Shortest page worth storing.
     *
     * Anything below this is a stub, a redirect page or the tail of a render that
     * died — none of which should be frozen for the next eight hours.
     */
    public const MIN_BODY_LENGTH = 255;

    /**
     * What a finished response looks like, by what was asked for.
     *
     * The rule exists because a render that dies mid-template still flushes its buffer,
     * and a half-written page is exactly the one that must not be frozen for the next
     * eight hours. What "finished" means depends on what was asked for, and this is the
     * only place in the cache where that is true.
     *
     * **A page** ends with `</html>` and is at least {@see Bypass::MIN_BODY_LENGTH} bytes.
     *
     * **A section response** ends with the `</div>` that
     * {@see \Studiometa\Foehn\Views\Sections\SectionRenderer} wraps every section in,
     * at any length — a fragment is legitimately small, and a byte count that made sense
     * for a page would refuse to store a pagination bar for a reason that has nothing to
     * do with it.
     *
     * @var array<string, array{int, string}> Shortest body, and what a finished one ends with.
     */
    private const BODY_SHAPE = [
        'page' => [self::MIN_BODY_LENGTH, '</html>'],
        'section' => [0, '</div>'],
    ];

    /**
     * Constants whose mere definition means "not a page".
     *
     * Read rather than the matching `wp_doing_*()` functions because the drop-in runs
     * before those exist, and WP-CLI, the REST controller and `wp_ajax` all define
     * theirs long before a template is chosen.
     *
     * @var array<string, BypassReason>
     */
    private const REQUEST_CONSTANTS = [
        'WP_CLI' => BypassReason::Cli,
        'REST_REQUEST' => BypassReason::Rest,
        'DOING_AJAX' => BypassReason::Ajax,
        'DOING_CRON' => BypassReason::Cron,
        'XMLRPC_REQUEST' => BypassReason::XmlRpc,
        'DONOTCACHEPAGE' => BypassReason::DoNotCache,
    ];

    /**
     * The template conditionals only a booted WordPress can answer.
     *
     * @var array<string, BypassReason>
     */
    private const CONTEXT_CONDITIONALS = [
        'is_admin' => BypassReason::Admin,
        'wp_doing_ajax' => BypassReason::Ajax,
        'wp_doing_cron' => BypassReason::Cron,
        'is_feed' => BypassReason::Feed,
        'is_trackback' => BypassReason::Trackback,
        'is_robots' => BypassReason::Robots,
        'is_embed' => BypassReason::Embed,
        'is_preview' => BypassReason::Preview,
        'is_customize_preview' => BypassReason::CustomizePreview,
        'is_search' => BypassReason::Search,
        'is_user_logged_in' => BypassReason::LoggedIn,
        'post_password_required' => BypassReason::PasswordRequired,
    ];

    public function __construct(
        private PageCacheConfig $config,
    ) {}

    /**
     * The rules that need nothing but the request itself.
     *
     * @param array<string, mixed> $server
     * @param array<string, mixed> $cookies
     * @param array<string, mixed> $post
     */
    public function forRequest(array $server, array $cookies = [], array $post = []): ?BypassReason
    {
        if (!$this->config->enabled) {
            return BypassReason::Disabled;
        }

        if (!$this->config->allowsEnvironment()) {
            return BypassReason::Environment;
        }

        foreach (self::REQUEST_CONSTANTS as $constant => $reason) {
            if (defined($constant) && (bool) constant($constant)) {
                return $reason;
            }
        }

        if (($server['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            return BypassReason::Method;
        }

        // A GET carrying a body is not a page request anyone should freeze.
        if ($post !== []) {
            return BypassReason::PostData;
        }

        if (!$this->hostMatches($server)) {
            return BypassReason::Host;
        }

        // Before the key, so a query string this cache will not key reports itself as one
        // rather than as an unusable path: `key()` cannot tell the two apart afterwards.
        if (QueryKey::canonical($this->queryString($server), $this->config) === null) {
            return BypassReason::QueryString;
        }

        $key = $this->key($server);

        if ($key === null) {
            return BypassReason::Path;
        }

        foreach ($cookies as $name => $_value) {
            foreach ($this->config->bypassCookies as $prefix) {
                if (str_starts_with($name, $prefix)) {
                    return BypassReason::Cookie;
                }
            }
        }

        if ($this->isMaintenance($server)) {
            return BypassReason::Maintenance;
        }

        foreach ($this->config->excludedPaths as $prefix) {
            $normalized = '/' . trim($prefix, '/');

            if ($normalized === '/' || str_starts_with(rtrim($key->path, '/') . '/', $normalized . '/')) {
                return BypassReason::ExcludedPath;
            }
        }

        return null;
    }

    /**
     * The rules that need a booted WordPress, plus every rule that does not.
     *
     * @param array<string, mixed> $server
     * @param array<string, mixed> $cookies
     * @param array<string, mixed> $post
     */
    public function forContext(array $server, array $cookies = [], array $post = []): ?BypassReason
    {
        $reason = $this->forRequest($server, $cookies, $post);

        if ($reason !== null) {
            return $reason;
        }

        foreach (self::CONTEXT_CONDITIONALS as $function => $candidate) {
            if (!function_exists($function) || $function() !== true) {
                continue;
            }

            if ($candidate === BypassReason::Search && $this->searchIsKeyed()) {
                continue;
            }

            return $candidate;
        }

        return null;
    }

    /**
     * Whether the project has asked for search results to be cached, by keying `s`.
     *
     * Search bypasses by default, and the reason is the key space rather than the query:
     * `s` takes any string a visitor can type, so keying it without a pattern is one
     * stored file per phrase anybody ever searches for, and a crawler can write them all.
     *
     * Naming `s` in `cacheQueryArgs` is the opt-in, and it cannot be given without also
     * giving the pattern that bounds it — the pattern is what makes the space finite, and
     * a value that does not match it bypasses exactly as before:
     *
     * ```php
     * cacheQueryArgs: ['s' => '^[A-Za-z0-9-]{2,32}$'],
     * ```
     *
     * The rest follows from machinery that already exists: `s` becomes an argument nginx
     * unrolls like `page`, the value lands in the filename, and a search for a phrase the
     * pattern refuses is served by WordPress the way every search is today.
     */
    private function searchIsKeyed(): bool
    {
        return array_key_exists('s', $this->config->getCacheQueryArgs());
    }

    /**
     * The last word: everything above, plus what the response turned out to be.
     *
     * @param array<string, mixed> $server
     * @param array<string, mixed> $cookies
     * @param array<string, mixed> $post
     * @param list<string> $headers As `headers_list()` returns them.
     */
    public function forResponse(
        string $body,
        int $status,
        array $headers,
        array $server,
        array $cookies = [],
        array $post = [],
    ): ?BypassReason {
        $reason = $this->forContext($server, $cookies, $post);

        if ($reason !== null) {
            return $reason;
        }

        if ($status !== 200 && !($status === 404 && $this->config->cacheNotFound)) {
            return BypassReason::Status;
        }

        $contentType = $this->header($headers, 'content-type');

        // WordPress sends `text/html; charset=UTF-8`. Anything else — a JSON
        // endpoint rendered through a template, an XML sitemap — is not this cache's.
        if ($contentType !== null && !str_starts_with(strtolower($contentType), 'text/html')) {
            return BypassReason::ContentType;
        }

        if ($this->header($headers, 'location') !== null) {
            return BypassReason::Redirect;
        }

        $reason = $this->bodyIsComplete($body, $server);

        if ($reason !== null) {
            return $reason;
        }

        foreach ($this->config->excludeWhenBodyContains as $needle) {
            if ($needle !== '' && str_contains($body, $needle)) {
                return BypassReason::BodyExcluded;
            }
        }

        return null;
    }

    /**
     * Whether a body looks like the whole of what was being rendered.
     *
     * See {@see Bypass::BODY_SHAPE} for what "the whole of it" means and why it is two
     * answers rather than one.
     *
     * @param array<string, mixed> $server
     */
    private function bodyIsComplete(string $body, array $server): ?BypassReason
    {
        [$minimum, $terminator] = self::BODY_SHAPE[$this->isSectionRequest($server) ? 'section' : 'page'];

        if (strlen($body) < $minimum) {
            return BypassReason::BodyTooShort;
        }

        return str_ends_with(rtrim($body), $terminator) ? null : BypassReason::BodyIncomplete;
    }

    /**
     * Whether the request asked for named sections of a page rather than the page.
     *
     * Read from the array rather than from the globals, because every other rule in this
     * class is a function of its arguments and this one has to be too: the drop-in, the
     * writer and the test suite all hand `Bypass` a `$_SERVER` that is not necessarily the
     * process's own. Built from the same query string the key is built from, so there is
     * one reading of the request rather than two — and the method is `GET` because this is
     * only ever asked after {@see Bypass::forRequest()} has refused every other one.
     *
     * @param array<string, mixed> $server
     */
    private function isSectionRequest(array $server): bool
    {
        return new SectionRequest('GET', '/?' . $this->queryString($server))->isSelected();
    }

    /**
     * The key for a request, or null when it has none to compute.
     *
     * @param array<string, mixed> $server
     */
    public function key(array $server): ?CacheKey
    {
        if (!$this->hostMatches($server)) {
            return null;
        }

        $variant = QueryKey::canonical($this->queryString($server), $this->config);

        if ($variant === null) {
            return null;
        }

        return CacheKey::create($this->requestHost($server), (string) ($server['REQUEST_URI'] ?? ''), $variant);
    }

    /**
     * The canonical query suffix for a request URI, or null when it is a bypass.
     *
     * A thin wrapper over {@see QueryKey::canonical()} for callers that hold a URI rather
     * than a `$_SERVER` array — the generated snippets are compared against this in
     * `ServerConfigTest`, which is what keeps the readers from drifting apart.
     */
    public function canonicalQuery(string $requestUri): ?string
    {
        $position = strpos($requestUri, '?');

        return QueryKey::canonical($position === false ? '' : substr($requestUri, $position + 1), $this->config);
    }

    /**
     * The raw query string of a request, from the same string the path comes from.
     *
     * `QUERY_STRING` would do as well and would usually agree, but taking both halves of
     * the key from one value means they cannot disagree.
     *
     * @param array<string, mixed> $server
     */
    private function queryString(array $server): string
    {
        $requestUri = (string) ($server['REQUEST_URI'] ?? '');
        $position = strpos($requestUri, '?');

        return $position === false ? '' : substr($requestUri, $position + 1);
    }

    /**
     * Whether the request host is the site's own.
     *
     * @param array<string, mixed> $server
     */
    private function hostMatches(array $server): bool
    {
        $site = CacheKey::siteHost();

        if ($site === null) {
            // With no site host to compare against there is nothing to validate the
            // request host with, and an unvalidated host is a poisoning primitive.
            return false;
        }

        return CacheKey::normalizeHost($this->requestHost($server)) === $site;
    }

    /**
     * @param array<string, mixed> $server
     */
    private function requestHost(array $server): string
    {
        return (string) ($server['HTTP_HOST'] ?? $server['SERVER_NAME'] ?? '');
    }

    /**
     * Whether WordPress is mid-update.
     *
     * Three places are checked because a WordPress in a subdirectory has two roots:
     * core writes `.maintenance` next to `wp-settings.php` (`ABSPATH`), while the
     * document root the server snippets can test is the directory above it. Only the
     * PHP readers can look in both, which is stated plainly in the guide.
     *
     * @param array<string, mixed> $server
     */
    private function isMaintenance(array $server): bool
    {
        $roots = [];
        $documentRoot = $server['DOCUMENT_ROOT'] ?? null;

        if (is_string($documentRoot) && $documentRoot !== '') {
            $roots[] = rtrim($documentRoot, '/');
        }

        if (defined('ABSPATH')) {
            $abspath = rtrim((string) constant('ABSPATH'), '/');
            $roots[] = $abspath;
            $roots[] = dirname($abspath);
        }

        foreach (array_unique($roots) as $root) {
            if (is_file($root . '/.maintenance')) {
                return true;
            }
        }

        return false;
    }

    /**
     * One header's value out of a `headers_list()` array, or null.
     *
     * @param list<string> $headers
     */
    private function header(array $headers, string $name): ?string
    {
        $prefix = strtolower($name) . ':';

        foreach ($headers as $header) {
            if (str_starts_with(strtolower($header), $prefix)) {
                return trim(substr($header, strlen($prefix)));
            }
        }

        return null;
    }
}
