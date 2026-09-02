<?php

declare(strict_types=1);

namespace Studiometa\Foehn\PageCache;

/**
 * Stop WordPress from percent-encoding a comma back out of a URL Føhn built.
 *
 * `redirect_canonical()` rebuilds the query string of a paginated URL and encodes its
 * values, so `?foehn_sections=count,index` is answered with a 301 to
 * `?foehn_sections=count%2Cindex`. Any comma does it, in any argument:
 * `/projects/page/2/?foo=a,b` redirects too.
 *
 * That is worse than a wasted round trip, because the comma is load-bearing here. It is
 * in {@see QueryKey::VALUE_CHARACTER_CLASS}, so a multi-value filter and a multi-section
 * request are keyed and stored under a filename with a literal comma in it. nginx keys
 * the same request from `$arg_…`, which is the *raw* query string — so after core's
 * redirect the fast path looks for `index__foehn_sections=count%2Cindex&.html` while the
 * recorder wrote `index__foehn_sections=count,index&.html`. Two readers, two filenames,
 * one response: every request misses and falls through to PHP, and nothing anywhere
 * reports an error. It just stops being fast.
 *
 * So a redirect that changes nothing but that encoding is cancelled, and a redirect that
 * also does something real keeps the literal comma. Only the query string is touched:
 * a `%2C` in a path could in principle be meant, and no part of this problem is there.
 *
 * Registered whether or not the cache is on. The URLs {@see \Studiometa\Foehn\Views\Twig\SectionExtension::url()}
 * emits carry literal commas in every environment, and a rule that only holds in
 * production is one nobody meets until it is expensive.
 */
final readonly class CanonicalRedirect
{
    public function register(): void
    {
        add_filter('redirect_canonical', $this->filter(...), 10, 2);
    }

    /**
     * @param string|false $redirectUrl Where core wants to send this request, or false.
     * @return string|false The corrected target, or false to stay here.
     */
    public function filter(mixed $redirectUrl, string $requestedUrl): string|false
    {
        if (!is_string($redirectUrl) || $redirectUrl === '') {
            return false;
        }

        $corrected = self::withLiteralCommas($redirectUrl);

        return $corrected === self::withLiteralCommas($requestedUrl) ? false : $corrected;
    }

    /**
     * The same URL with the commas in its query string spelled as commas.
     */
    private static function withLiteralCommas(string $url): string
    {
        $parts = explode('?', $url, 2);

        if (count($parts) === 1) {
            return $url;
        }

        $rest = explode('#', $parts[1], 2);

        // Case-insensitive: core writes `%2C`, a client may send `%2c`, and they are the
        // same character — which is the whole point of normalising them here.
        $query = (string) preg_replace('/%2c/i', ',', $rest[0]);
        $fragment = $rest[1] ?? null;

        return $parts[0] . '?' . $query . ($fragment === null ? '' : '#' . $fragment);
    }
}
