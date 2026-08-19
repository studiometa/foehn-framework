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
    public function __construct(
        private PageCacheConfig $config,
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
     * Every URL WordPress's own sitemap knows about.
     *
     * The core sitemap is the list the site already publishes for crawlers, which makes
     * it the right list to warm: no separate notion of "important pages" to keep in step
     * with the one search engines are given.
     *
     * @return list<string>
     */
    public function urls(): array
    {
        $urls = [home_url('/')];
        $provider = $this->sitemapEntries();

        foreach ($provider as $entry) {
            $location = $entry['loc'] ?? null;

            if (is_string($location) && $location !== '') {
                $urls[] = $location;
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * The sitemap's entries, or nothing when sitemaps are switched off.
     *
     * @return list<array<string, mixed>>
     */
    private function sitemapEntries(): array
    {
        if (!function_exists('wp_get_sitemap_providers')) {
            return [];
        }

        $entries = [];

        foreach (wp_get_sitemap_providers() as $provider) {
            foreach ($provider->get_sitemap_type_data() as $type) {
                $name = is_array($type) ? (string) ($type['name'] ?? '') : '';
                $pages = is_array($type) ? (int) ($type['pages'] ?? 1) : 1;

                for ($page = 1; $page <= max(1, $pages); $page++) {
                    $entries = [...$entries, ...$provider->get_url_list($page, $name)];
                }
            }
        }

        return $entries;
    }
}
