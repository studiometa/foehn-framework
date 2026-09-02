<?php

declare(strict_types=1);

namespace Studiometa\Foehn\PageCache;

use Studiometa\Foehn\Views\Sections\SectionRequest;

/**
 * A request turned into the one filename that every reader can compute.
 *
 * ```
 * {cache root}/{host}/{path}/index.html                 # no query, or ignored args only
 * {cache root}/{host}/{path}/index__lang=fr&page=2&.html # keyed args, in canonical order
 * ```
 *
 * Two properties make this class load-bearing rather than string concatenation.
 *
 * **The host is validated, never trusted.** A cache path built from an unchecked
 * `Host` header is a cache-poisoning primitive: one request with
 * `Host: evil.test` writes a file that a later request for that host reads back. So
 * the host is compared to the site's own, and a mismatch is a bypass rather than a
 * write. It stays in the path because the read side cannot query WordPress, and
 * because multisite then fits without a change of layout.
 *
 * **The path is decoded exactly once, then validated.** Decoded, because nginx's
 * `$uri` is decoded and the readers have to agree on the filename — French slugs make
 * this real: `/%C3%A0-propos/` and `/à-propos/` are one page and must be one file.
 * Once, because a value that still contains a `%` after decoding was double-encoded,
 * and there is no reading of it that all four readers would share.
 */
final readonly class CacheKey
{
    /**
     * The stored filename for a request whose query string does not change the page.
     */
    public const FILENAME = 'index.html';

    /**
     * What separates the keyed query args from `index` in a filename.
     *
     * Doubled so it cannot be confused with a `-` inside a value, and the trailing `&`
     * of {@see QueryKey::canonical()} is kept rather than trimmed: nginx cannot strip a
     * character from a variable, so the format keeps the separator instead of asking PHP
     * and nginx to agree about removing it.
     */
    private const VARIANT_SEPARATOR = '__';

    /** Longest single path segment, in bytes. */
    private const MAX_SEGMENT_BYTES = 200;

    /** Longest whole path, in bytes. */
    private const MAX_PATH_BYTES = 512;

    /**
     * Anything a decoded path may not contain.
     *
     * An allowlist rather than a denylist, and a rejection rather than a rewrite. The
     * unreserved characters of RFC 3986 plus `/`, plus any non-ASCII codepoint so that
     * `/à-propos/` survives — and nothing else. A space, a colon, a quote, an angle
     * bracket, a backslash, a control character, a leftover `%`: any of them fails the
     * whole path.
     *
     * Sanitizing instead would be worse than refusing. `/x/:8080/evil` is a real probe,
     * and a cache that quietly turns a probe into a valid filename is a cache that has
     * agreed to store the attacker's page.
     */
    private const FORBIDDEN_IN_PATH = '#[^A-Za-z0-9/_.~\-\x{0080}-\x{10FFFF}]#u';

    /**
     * The filenames this cache is allowed to write.
     *
     * Checked immediately before the write, though every part of the name was
     * validated on the way in: the string that reaches a filename has passed through
     * more hands than the one that was validated, and this is the last of them.
     *
     * Built from {@see QueryKey::VALUE_CHARACTER_CLASS} rather than spelled again, plus
     * the `=` and `&` that join a name to its value. A literal here was a third copy of
     * one decision, and it drifted the first time the value charset moved: adding the
     * comma for multi-value filters left this pattern refusing `index__genre=rock,jazz&`,
     * so every one of those requests bypassed with a reason that said `path` — a message
     * about the URL, for a filename this file would not write. Derived, it cannot say no
     * to a value the rest of the cache has already said yes to.
     */
    public const FILENAME_PATTERN =
        '/^index(__[' . QueryKey::VALUE_CHARACTER_CLASS . '=&]+)?(' . self::NOT_FOUND_SUFFIX . ')?\.html$/';

    /**
     * What marks a stored file as a 404 rather than a 200.
     *
     * A cache stores a body, and a body alone cannot say what status it was sent with —
     * so `cacheNotFound` used to store a "not found" page and both readers served it as
     * `200 OK`. Search engines call that a soft 404 and monitoring cannot see it at all.
     *
     * The status therefore goes in the name, and it can go there unambiguously: a variant
     * always ends with `&`, because {@see QueryKey::canonical()} appends one per argument.
     * So a name ending in `--404.html` is one this cache wrote for a 404 and can never be
     * a keyed variant that happens to look like one.
     *
     * It is also what keeps nginx honest without teaching it a trick. The generated
     * snippet only ever builds `index.html` and `index__variant.html`, so it never finds
     * a 404 file, forwards the request to PHP, and the drop-in answers with the right
     * status. nginx cannot set a status on a static response without `error_page`, and
     * this way it does not have to.
     */
    public const NOT_FOUND_SUFFIX = '--404';

    /** What a stored entry's headers file is called, relative to its body. */
    public const HEADERS_SUFFIX = '.headers';

    private function __construct(
        /** Lowercased, port-stripped request host. */
        public string $host,
        /** Decoded path, leading slash, no trailing slash. The site root is `/`. */
        public string $path,
        /** Canonical keyed query args, or an empty string. See {@see QueryKey}. */
        public string $variant,
    ) {}

    /**
     * Build a key, or return null when the request cannot be keyed safely.
     *
     * Null is never an error to report to a visitor — it is a bypass. Every rejection
     * here is a request whose filename this cache would rather not compute.
     *
     * @param string $variant The canonical query suffix from {@see QueryKey::canonical()}.
     */
    public static function create(string $host, string $requestUri, string $variant = ''): ?self
    {
        $normalizedHost = self::normalizeHost($host);

        if ($normalizedHost === null) {
            return null;
        }

        $path = self::normalizePath($requestUri);

        if ($path === null) {
            return null;
        }

        // The variant arrives validated argument by argument, and is checked again here as
        // one assembled string. The name that reaches a filename has passed through more
        // hands than the values that were validated, and this is the last of them.
        if (!self::isWritableFilename(self::filenameFor($variant))) {
            return null;
        }

        return new self($normalizedHost, $path, $variant);
    }

    /**
     * The stored filename for this key.
     */
    public function filename(int $status = 200): string
    {
        return self::filenameFor($this->variant, $status);
    }

    /**
     * The file holding this entry's recorded response headers.
     *
     * A sibling of the body rather than a header block inside it, so the body stays a
     * file a webserver can send untouched — which is the whole point of storing one.
     */
    public function headersFilename(int $status = 200): string
    {
        return $this->filename($status) . self::HEADERS_SUFFIX;
    }

    /**
     * The path of the stored file, relative to the cache root.
     */
    public function relativePath(int $status = 200): string
    {
        return $this->relativeDirectory() . '/' . $this->filename($status);
    }

    /**
     * The path of this entry's stored headers, relative to the cache root.
     */
    public function headersRelativePath(int $status = 200): string
    {
        return $this->relativeDirectory() . '/' . $this->headersFilename($status);
    }

    /**
     * The filename a canonical query suffix stores under.
     */
    private static function filenameFor(string $variant, int $status = 200): string
    {
        $suffix = $status === 404 ? self::NOT_FOUND_SUFFIX : '';

        if ($variant === '' && $suffix === '') {
            return self::FILENAME;
        }

        $keyed = $variant === '' ? '' : self::VARIANT_SEPARATOR . $variant;

        return 'index' . $keyed . $suffix . '.html';
    }

    /**
     * The directory the stored file lives in, relative to the cache root.
     */
    public function relativeDirectory(): string
    {
        return rtrim($this->host . $this->path, '/');
    }

    /**
     * A host reduced to what a directory name can hold, or null when it is not a host.
     *
     * The port goes: `example.com` and `example.com:8443` are one site, and a colon is
     * not a character to put in a path on every filesystem.
     */
    public static function normalizeHost(string $rawHost): ?string
    {
        // Before trimming, because PHP's trim() strips a null byte and a newline and
        // would hand back a host that looks clean. A Host header carrying either is
        // not a host to normalise, it is a request to refuse.
        if (preg_match('/[\x00-\x1f\x7f]/', $rawHost) === 1) {
            return null;
        }

        // An IPv6 literal in a Host header is bracketed; a port follows the brackets.
        $host = (string) preg_replace('/:\d+$/', '', strtolower(trim($rawHost)));
        $host = trim($host, '[]');

        if ($host === '' || strlen($host) > 253) {
            return null;
        }

        // Letters, digits, dots, hyphens and colons (IPv6) only. Everything a Host
        // header could smuggle — a slash, a dot-dot, an encoded byte — is refused
        // rather than escaped, because refusing is the behaviour we can reason about.
        if (preg_match('/^[a-z0-9.:_-]+$/', $host) !== 1) {
            return null;
        }

        if (str_contains($host, '..')) {
            return null;
        }

        // A colon is legal in an IPv6 literal but not in a directory name everywhere.
        return str_replace(':', '-', $host);
    }

    /**
     * The site's own host, as the key must match it, or null when it cannot be read.
     */
    public static function siteHost(): ?string
    {
        $home = null;

        if (defined('WP_HOME')) {
            $home = (string) constant('WP_HOME');
        }

        if (($home === null || $home === '') && function_exists('home_url')) {
            $home = home_url('/');
        }

        if (!is_string($home) || $home === '') {
            return null;
        }

        $host = parse_url($home, PHP_URL_HOST);

        if (!is_string($host)) {
            return null;
        }

        return self::normalizeHost($host);
    }

    /**
     * A request URI reduced to a validated path, or null when it cannot be one.
     */
    public static function normalizePath(string $requestUri): ?string
    {
        // The query string is never part of the key. Args that survive
        // `PageCacheConfig::$ignoredQueryArgs` are a bypass, decided by `Bypass`.
        $path = explode('?', $requestUri, 2)[0];
        $path = explode('#', $path, 2)[0];

        // Once. A value that still holds a `%` after this was double-encoded, and the
        // four readers would each pick a different meaning for it.
        //
        // Decoding is also what makes invalidation work at all. WordPress stores a
        // non-ASCII slug with *lowercase* percent escapes — `utf8_uri_encode()` builds
        // them with `dechex()` — while a browser sends uppercase ones. So
        // `get_permalink()` hands the purger a different spelling of the URL than the
        // one the recorder was asked for, and only a decode collapses the two onto one
        // file. Every caller goes through this one function for that reason.
        $path = rawurldecode($path);

        // Invalid UTF-8 would be written to disk as bytes nginx's decoded `$uri` never
        // produces, so the file would exist and never be found. Checked before the
        // allowlist, which is a `/u` pattern and would simply fail on bad bytes.
        if (preg_match('//u', $path) !== 1) {
            return null;
        }

        if (preg_match(self::FORBIDDEN_IN_PATH, $path) === 1) {
            return null;
        }

        if (str_contains($path, '..')) {
            return null;
        }

        $path = '/' . trim((string) preg_replace('#/+#', '/', $path), '/');

        // The front controller is the home page, not a second URL for it.
        if ($path === '/index.php') {
            $path = '/';
        }

        if (strlen($path) > self::MAX_PATH_BYTES) {
            return null;
        }

        foreach (explode('/', trim($path, '/')) as $segment) {
            if (strlen($segment) > self::MAX_SEGMENT_BYTES) {
                return null;
            }

            // A segment of dots is a filesystem reference, not a slug.
            if ($segment !== '' && trim($segment, '.') === '') {
                return null;
            }
        }

        return $path;
    }

    /**
     * Whether a filename is one this cache is allowed to write.
     */
    public static function isWritableFilename(string $filename): bool
    {
        return preg_match(self::FILENAME_PATTERN, $filename) === 1;
    }

    /**
     * Whether a filename is a section-cache entry, body or sidecar.
     *
     * A section entry is a stored variant whose canonical query carries
     * {@see SectionRequest::PARAMETER} — the fragment responses, as opposed to the whole
     * pages and the other keyed variants that sit in the same directory.
     *
     * Matched by *parsing* the variant rather than searching the string for
     * `foehn_sections=`. A keyed argument name may contain an underscore, so a project
     * that configured `my_foehn_sections` would otherwise have its variants deleted by a
     * button labelled "clear section cache". Values cannot contain `=` or `&` — see
     * {@see QueryKey::VALUE_CHARACTER_CLASS} — so splitting on those two is exact.
     */
    public static function isSectionEntry(string $filename): bool
    {
        $variant = self::variantOf($filename);

        if ($variant === null || $variant === '') {
            return false;
        }

        foreach (explode('&', $variant) as $pair) {
            if (explode('=', $pair, 2)[0] === SectionRequest::PARAMETER) {
                return true;
            }
        }

        return false;
    }

    /**
     * The canonical query suffix a stored filename carries, or null when the name is not
     * one this cache writes.
     *
     * The null is the load-bearing half. Deletion walks a directory tree, and a file the
     * cache did not write is a file it has no business removing — a developer's stray
     * copy, something another tool left there. An unrecognised name is refused rather
     * than parsed as far as it goes.
     *
     * An empty string means a recognised name with no variant: the plain `index.html`.
     */
    public static function variantOf(string $filename): ?string
    {
        if (str_ends_with($filename, self::HEADERS_SUFFIX)) {
            $filename = substr($filename, 0, -strlen(self::HEADERS_SUFFIX));
        }

        if (!self::isWritableFilename($filename)) {
            return null;
        }

        // Peeled off rather than read from the pattern's capture group. A keyed value may
        // contain `-`, so the group is greedy enough to swallow the `--404` suffix and
        // hand back `lang=fr&--404` as the variant. The suffixes are unambiguous from the
        // right, though: a variant always ends with `&`, because
        // {@see QueryKey::canonical()} appends one per argument, so a name ending in
        // `--404.html` is one this cache wrote for a 404 and never a variant that looks
        // like one.
        $stem = substr($filename, 0, -strlen('.html'));

        if (str_ends_with($stem, self::NOT_FOUND_SUFFIX)) {
            $stem = substr($stem, 0, -strlen(self::NOT_FOUND_SUFFIX));
        }

        $prefix = 'index' . self::VARIANT_SEPARATOR;

        return str_starts_with($stem, $prefix) ? substr($stem, strlen($prefix)) : '';
    }
}
