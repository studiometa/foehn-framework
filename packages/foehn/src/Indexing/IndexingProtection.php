<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Indexing;

use Studiometa\Foehn\Helpers\Env;

/**
 * Keep every environment that is not production out of the search index.
 *
 * A staging copy of a site is the same site with the same content and, usually, a
 * crawlable hostname. Indexed, it competes with the real one for the real one's own
 * pages — and the damage is done by the time anybody notices, because nothing about it
 * is visible from inside WordPress.
 *
 * **The HTML directive is the protection that matters, not `robots.txt`.** Føhn serves
 * cached pages from a file: nginx or the drop-in answers, and PHP does not run at all on
 * later requests. A rule that lives in a header PHP sends is therefore a rule that
 * applies to the first visitor and to nobody after — so `noindex, nofollow` goes into the
 * document itself through `wp_robots`, where it is stored with the page and is still
 * there whichever reader hands the file over. `robots.txt` is advisory, is fetched once
 * for the whole host, and is the weakest of the four; it is here for completeness.
 *
 * Four measures, because they cover different crawlers:
 *
 * - `wp_robots` — the `<meta name="robots">` tag, the one that survives the cache;
 * - `send_headers` — `X-Robots-Tag`, which covers responses that are not HTML;
 * - `robots_txt` — a whole-host `Disallow: /` for crawlers that ask first;
 * - `wp_sitemaps_enabled` — core's sitemap, which would otherwise hand a crawler the
 *   complete list of URLs it is being asked not to read.
 *
 * **`blog_public` is never written.** Marking a site private is the same policy said in
 * a place it cannot be taken back from: the option lives in the database, and a database
 * copied from staging to production — which is how staging databases usually arrive
 * anywhere — carries the staging answer with it and silently de-indexes the live site.
 * Deployment configuration owns `WP_ENVIRONMENT_TYPE`; this module only reads it.
 *
 * **Production adds no hooks at all.** {@see IndexingProtection::register()} returns
 * before the first `add_filter()`, so a production response is byte-for-byte what it
 * would be without this class — there is no filter to misbehave and no header to
 * accidentally send.
 *
 * **Known limit:** this reaches only what PHP and WordPress emit. A CDN or web server
 * that adds an `X-Robots-Tag` of its own is outside it, and so is a `robots.txt` served
 * as a real file before WordPress is reached. Deployment infrastructure that needs the
 * guarantee has to inspect the public HTTP response.
 *
 * @see https://developer.wordpress.org/reference/hooks/wp_robots/
 */
final readonly class IndexingProtection
{
    /**
     * The header line, spelled exactly as {@see \Studiometa\Foehn\Views\Sections\SectionResponse::headers()}
     * spells it.
     *
     * A section response sends `X-Robots-Tag` unconditionally, in every environment, and
     * `header()` replaces a field by default rather than appending to it. Two spellings
     * would mean the last writer decided what a non-production section response says —
     * identical ones mean it does not matter which of them ran.
     */
    public const HEADER = 'X-Robots-Tag: noindex, nofollow';

    /**
     * Late enough to be the last word.
     *
     * An SEO plugin that sets `index` on the page it has just optimised is doing its job;
     * it simply does not know which environment it is in. Running after everything else
     * is what makes this a guard rather than one more opinion in the pile.
     */
    private const PRIORITY = PHP_INT_MAX;

    /**
     * Whether this environment is being kept out of the index.
     *
     * An instance method, and public, because production verification reads it to report
     * that a production site is *not* protected — which is the healthy answer there.
     */
    public function isActive(): bool
    {
        return !Env::isProduction();
    }

    public function register(): void
    {
        if (!$this->isActive()) {
            return;
        }

        add_filter('wp_robots', $this->robots(...), self::PRIORITY);
        add_action('send_headers', $this->sendHeader(...), self::PRIORITY);
        add_filter('robots_txt', $this->robotsTxt(...), self::PRIORITY);
        add_filter('wp_sitemaps_enabled', $this->sitemapsEnabled(...), self::PRIORITY);
    }

    /**
     * Force `noindex, nofollow` into the robots directives of a document.
     *
     * The removals are not tidying. WordPress renders whatever keys it is given, and
     * `wp_robots_no_robots()` adds `follow` when `blog_public` is on — so leaving the
     * positive keys in place emits `<meta name="robots" content="follow, noindex,
     * nofollow">` and asks a crawler to settle a contradiction. Every other key is left
     * alone: `max-image-preview` and its neighbours say how to present a page that is
     * indexed, and have nothing to say about one that is not.
     *
     * @param array<string, mixed> $robots
     * @return array<string, mixed>
     */
    public function robots(array $robots): array
    {
        unset($robots['index'], $robots['follow']);

        $robots['noindex'] = true;
        $robots['nofollow'] = true;

        return $robots;
    }

    /**
     * @codeCoverageIgnore Cannot test header() in CLI environment
     */
    public function sendHeader(): void
    {
        header(self::HEADER);
    }

    /**
     * The whole `robots.txt` body, replacing whatever core assembled.
     *
     * Replaced rather than appended to: core's version lists the sitemap, and a file that
     * says `Disallow: /` and then points at an index of everything is a mixed message.
     */
    public function robotsTxt(): string
    {
        return "User-agent: *\nDisallow: /\n";
    }

    public function sitemapsEnabled(): bool
    {
        return false;
    }
}
