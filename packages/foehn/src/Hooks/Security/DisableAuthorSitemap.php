<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Hooks\Security;

use Studiometa\Foehn\Attributes\AsAction;
use Studiometa\Foehn\Attributes\AsFilter;

/**
 * Remove the author sitemaps: the core one and the one of Rank Math.
 *
 * An author sitemap lists the archive URL of each author, and that URL holds the user
 * slug, which is usually the login. It hands a list of logins to anybody who tries to
 * guess passwords. {@see RestApiAuth} closes the same list on `/wp/v2/users`.
 *
 * - **Core** (`/wp-sitemap-users-1.xml`): the `users` provider is not registered.
 * - **Rank Math** (`/author-sitemap.xml`): its author sitemap is off for each request, so
 *   the sitemap index does not list it. A request for it answers 404 at once, also when
 *   Rank Math serves its sitemaps from its cache. The index that Rank Math has in its
 *   cache can still list it until Rank Math builds the index again.
 *
 * The author archives themselves are not changed.
 */
final class DisableAuthorSitemap
{
    /**
     * Do not register the `users` provider of the core sitemap.
     *
     * @param \WP_Sitemaps_Provider|false $provider
     * @return \WP_Sitemaps_Provider|false
     */
    #[AsFilter('wp_sitemaps_add_provider', acceptedArgs: 2)]
    public function removeCoreProvider(mixed $provider, string $name): mixed
    {
        return $name === 'users' ? false : $provider;
    }

    /**
     * Turn off the author sitemap of Rank Math for this request, so its sitemap index
     * does not list it.
     *
     * In the settings that Rank Math has loaded, not in the database: the settings
     * screen of Rank Math still shows the saved value. After `init` at 10, where Rank
     * Math loads its settings again on a multilingual site.
     */
    #[AsAction('init', priority: 20)]
    public function turnOffRankMathSitemap(): void
    {
        if (!function_exists('rank_math')) {
            return;
        }

        $settings = rank_math()->settings;
        // Load the settings first: Rank Math loads them on the first read, and a value
        // set before that would be the only setting it has.
        $settings->get('sitemap.authors_sitemap');
        $settings->set('sitemap', 'authors_sitemap', false);
    }

    /**
     * Answer 404 to a request for the author sitemap of Rank Math.
     *
     * Rank Math serves a sitemap from its cache before it asks whether the sitemap is
     * on, so the setting alone leaves a cached author sitemap online.
     *
     * @param array<string, mixed> $vars
     * @return array<string, mixed>
     */
    #[AsFilter('request')]
    public function refuseRankMathRequest(array $vars): array
    {
        if (($vars['sitemap'] ?? null) !== 'author') {
            return $vars;
        }

        return ['error' => '404'];
    }
}
