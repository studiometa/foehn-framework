<?php

declare(strict_types=1);

namespace Studiometa\Foehn\PageCache;

use Studiometa\Foehn\Attributes\AsJob;
use Studiometa\Foehn\Config\PageCacheConfig;

/**
 * Fill the cache by requesting pages, so the first visitor after a deploy is not the one
 * who pays for the render.
 *
 * A real HTTP request rather than an internal render, because a render inside the current
 * process would be keyed by the current request and would not go through the writer at
 * all. The request carries no cookies — a warm request must look anonymous, or the
 * eligibility rules bypass it — and an `X-Foehn-Warm: 1` header so a site can recognise
 * its own traffic in a log.
 */
#[AsJob(group: 'foehn-page-cache')]
final readonly class Warmer
{
    /**
     * How many sitemap index levels a walk follows. An index of indexes is rare, and
     * two levels cover it.
     */
    private const MAX_SITEMAP_DEPTH = 2;

    /**
     * The most locations a sitemap walk collects: the limit the sitemap protocol sets
     * for one sitemap file.
     */
    private const MAX_URLS = 50_000;

    public function __construct(
        private PageCacheConfig $config,
        private Bypass $bypass,
    ) {}

    public function __invoke(WarmUrl $job): void
    {
        $this->warm($job->url);
    }

    /**
     * Request one URL. Returns the status code, or null when the request failed.
     */
    public function warm(string $url): ?int
    {
        if (!$this->config->enabled || !$this->config->allowsEnvironment()) {
            return null;
        }

        $response = wp_remote_get($url, [
            'timeout' => 30,
            // No cookies, no redirects: a warm request has to look like an anonymous
            // first visit, and a redirect would warm a URL nobody asked for.
            'cookies' => [],
            'redirection' => 0,
            'blocking' => true,
            'headers' => ['X-Foehn-Warm' => '1'],
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $status = (int) wp_remote_retrieve_response_code($response);

        return $status > 0 ? $status : null;
    }

    /**
     * Every URL the sitemap knows about, less the ones not worth a warm request.
     *
     * The sitemap is the list the site already publishes for crawlers, which makes it the
     * right list to warm: no separate notion of "important pages" to keep in step with
     * the one search engines are given. It is the core WordPress sitemap, or the one
     * `warmSitemap` names when a plugin replaces it.
     *
     * @return list<string>
     */
    public function urls(): array
    {
        $sitemap = $this->config->warmSitemap;
        $listed = $sitemap === null ? $this->coreSitemapUrls() : $this->remoteSitemapUrls($sitemap);

        return $this->warmable([home_url('/'), ...$listed]);
    }

    /**
     * The URLs worth a warm request, each once, in the order given.
     *
     * A URL that matches `warmExclude` is dropped, and so is one the cache would never
     * store: another host, a query string it does not key, a path under `excludedPaths`.
     * A request for those costs a render and fills nothing.
     *
     * @param list<string> $urls
     * @return list<string>
     */
    public function warmable(array $urls): array
    {
        return array_values(array_unique(array_filter(
            $urls,
            fn(string $url): bool => $url !== '' && !$this->config->excludesFromWarmup($url) && $this->isStorable($url),
        )));
    }

    /**
     * Whether the cache could store the page at a URL: the site's own host, a query
     * string it keys, a path it accepts and not under `excludedPaths`.
     *
     * The URL alone decides, through the rules the writer uses. Not
     * {@see Bypass::forRequest()}, which answers "CLI" or "cron" here, because the warmer
     * runs from WP-CLI and from Action Scheduler.
     */
    private function isStorable(string $url): bool
    {
        $key = $this->bypass->key([
            'HTTP_HOST' => (string) parse_url($url, PHP_URL_HOST),
            // Everything after the host, as a request line carries it.
            'REQUEST_URI' => (string) preg_replace('#^[^:/?]+://[^/?]*#', '', $url),
        ]);

        return $key !== null && !$this->config->excludesPath($key->path);
    }

    /**
     * The locations in the core sitemap, or nothing when sitemaps are switched off.
     *
     * @return list<string>
     */
    private function coreSitemapUrls(): array
    {
        if (!function_exists('wp_get_sitemap_providers')) {
            return [];
        }

        $urls = [];

        foreach (wp_get_sitemap_providers() as $provider) {
            foreach ($provider->get_sitemap_type_data() as $type) {
                $name = is_array($type) ? (string) ($type['name'] ?? '') : '';
                $pages = is_array($type) ? (int) ($type['pages'] ?? 1) : 1;

                for ($page = 1; $page <= max(1, $pages); $page++) {
                    foreach ($provider->get_url_list($page, $name) as $entry) {
                        $location = is_array($entry) ? $entry['loc'] ?? null : null;

                        if (is_string($location)) {
                            $urls[] = $location;
                        }
                    }
                }
            }
        }

        return $urls;
    }

    /**
     * The page locations in a sitemap, following a sitemap index to its sitemaps.
     *
     * Read over HTTP, because a plugin that replaces the core sitemap builds its own on
     * request and offers no PHP API that every plugin shares. An index is followed at
     * most {@see Warmer::MAX_SITEMAP_DEPTH} levels down, each sitemap is read once, and
     * the walk stops at {@see Warmer::MAX_URLS} locations. A sitemap that does not answer
     * 200 adds nothing.
     *
     * @return list<string>
     */
    private function remoteSitemapUrls(string $sitemap): array
    {
        $urls = [];
        $queue = [[$sitemap, 0]];
        $read = [];

        while ($queue !== [] && count($urls) < self::MAX_URLS) {
            [$location, $depth] = array_shift($queue);

            if (in_array($location, $read, true)) {
                continue;
            }

            $read[] = $location;
            $body = $this->fetch($location);

            if ($body === null) {
                continue;
            }

            $locations = self::locations($body);

            if (!str_contains($body, '<sitemapindex')) {
                $urls = [...$urls, ...$locations];

                continue;
            }

            if ($depth < self::MAX_SITEMAP_DEPTH) {
                foreach ($locations as $child) {
                    $queue[] = [$child, $depth + 1];
                }
            }
        }

        return array_slice($urls, 0, self::MAX_URLS);
    }

    /**
     * A sitemap's body, or null when it did not answer 200.
     */
    private function fetch(string $url): ?string
    {
        $response = wp_remote_get($url, ['timeout' => 30]);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        return wp_remote_retrieve_body($response);
    }

    /**
     * The `<loc>` values of a sitemap or a sitemap index.
     *
     * Only the bare `<loc>` element: the `<image:loc>` of an image sitemap extension is
     * an image, not a page.
     *
     * @return list<string>
     */
    private static function locations(string $xml): array
    {
        $matches = [];
        preg_match_all('#<loc>\s*(.*?)\s*</loc>#s', $xml, $matches);

        return array_values(array_filter(
            array_map(static fn(string $location): string => html_entity_decode(
                $location,
                ENT_QUOTES | ENT_XML1,
                'UTF-8',
            ), $matches[1]),
            static fn(string $location): bool => $location !== '',
        ));
    }
}
