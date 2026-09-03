<?php

declare(strict_types=1);

use Studiometa\Foehn\Indexing\IndexingProtection;

/**
 * Keeping staging out of the index, and keeping production exactly as it was.
 *
 * Two halves, and the second is the one worth the trouble. A guard that fires outside
 * production is easy to check; a guard that provably does *nothing* in production is
 * what makes it safe to wire unconditionally into the Kernel — so the production cases
 * assert an empty hook list rather than only an unchanged value.
 *
 * `blog_public` gets its own case for the same reason. Writing the option would be the
 * obvious implementation and the wrong one: a staging database copied to production
 * carries the staging answer with it, and de-indexes the live site from a row nobody
 * thinks to look at.
 */
beforeEach(function (): void {
    wp_stub_reset();
    // `wp_stub_reset()` deliberately leaves this global alone, so a value one case sets
    // is still what `wp_get_environment_type()` answers in the next. Unset on both sides
    // of every case: the production assertions are exactly the ones a leaked `staging`
    // would turn green for the wrong reason.
    unset($GLOBALS['wp_stub_environment_type']);
});

afterEach(function (): void {
    unset($GLOBALS['wp_stub_environment_type']);
});

/**
 * The hooks a registered instance added, as `hook => priority`.
 *
 * @return array<string, int>
 */
function indexingHooks(): array
{
    $hooks = [];

    foreach (['add_filter', 'add_action'] as $registrar) {
        foreach (wp_stub_get_calls($registrar) as $call) {
            $hooks[$call['args']['hook']] = $call['args']['priority'];
        }
    }

    return $hooks;
}

describe('outside production', function () {
    it('registers all four protections', function (string $environment) {
        $GLOBALS['wp_stub_environment_type'] = $environment;

        new IndexingProtection()->register();

        expect(indexingHooks())->toHaveKeys(['wp_robots', 'send_headers', 'robots_txt', 'wp_sitemaps_enabled']);
    })->with(['staging', 'development', 'local']);

    it('forces noindex and nofollow into the robots meta tag', function (string $environment) {
        $GLOBALS['wp_stub_environment_type'] = $environment;

        new IndexingProtection()->register();

        expect(apply_filters('wp_robots', []))->toBe(['noindex' => true, 'nofollow' => true]);
    })->with(['staging', 'development', 'local']);

    it('disallows the whole host in robots.txt', function (string $environment) {
        $GLOBALS['wp_stub_environment_type'] = $environment;

        new IndexingProtection()->register();

        expect(apply_filters('robots_txt', "User-agent: *\nSitemap: https://example.com/wp-sitemap.xml\n", true))
            ->toContain('User-agent: *')
            ->toContain('Disallow: /')
            // Replaced, not appended to: a file that disallows everything and then points
            // at an index of every URL is a mixed message.
            ->not->toContain('Sitemap:');
    })->with(['staging', 'development', 'local']);

    it('disables the core sitemap', function (string $environment) {
        $GLOBALS['wp_stub_environment_type'] = $environment;

        new IndexingProtection()->register();

        expect(apply_filters('wp_sitemaps_enabled', true))->toBeFalse();
    })->with(['staging', 'development', 'local']);

    it('sends the same X-Robots-Tag line a section response sends', function () {
        // `header()` replaces a field by default and a section response sets this one in
        // every environment, so the two spellings have to be identical — otherwise the
        // last writer decides what a non-production section response says.
        expect(IndexingProtection::HEADER)->toBe('X-Robots-Tag: noindex, nofollow');
    });

    it('removes the contradictory index and follow directives', function () {
        // Not tidying: `wp_robots_no_robots()` adds `follow` when `blog_public` is on, and
        // WordPress renders whatever keys it is handed — so leaving them in emits
        // `content="follow, noindex, nofollow"` and asks a crawler to pick a side.
        $GLOBALS['wp_stub_environment_type'] = 'staging';

        new IndexingProtection()->register();

        expect(apply_filters('wp_robots', ['index' => true, 'follow' => true]))->toBe([
            'noindex' => true,
            'nofollow' => true,
        ]);
    });

    it('leaves robots directives that are about something else alone', function () {
        // `max-image-preview` and its neighbours say how to present a page that *is*
        // indexed. They have no opinion about one that is not, so dropping them would be
        // this class deciding something outside its business.
        $GLOBALS['wp_stub_environment_type'] = 'staging';

        new IndexingProtection()->register();

        expect(apply_filters('wp_robots', ['max-image-preview' => 'large', 'index' => true]))->toBe([
            'max-image-preview' => 'large',
            'noindex' => true,
            'nofollow' => true,
        ]);
    });

    it('never writes blog_public, or any other option', function (string $environment) {
        // The failure this design avoids: the option persists, and a database copied from
        // here to production takes the staging answer with it.
        $GLOBALS['wp_stub_environment_type'] = $environment;

        $protection = new IndexingProtection();
        $protection->register();

        apply_filters('wp_robots', []);
        apply_filters('robots_txt', '', true);
        apply_filters('wp_sitemaps_enabled', true);

        expect(wp_stub_get_calls('update_option'))->toBeEmpty();
        expect(wp_stub_get_calls('delete_option'))->toBeEmpty();
    })->with(['staging', 'development', 'local']);

    it('reports itself active', function (string $environment) {
        $GLOBALS['wp_stub_environment_type'] = $environment;

        expect(new IndexingProtection()->isActive())->toBeTrue();
    })->with(['staging', 'development', 'local']);
});

describe('in production', function () {
    beforeEach(function (): void {
        $GLOBALS['wp_stub_environment_type'] = 'production';
    });

    it('adds no hooks at all', function () {
        // The assertion that makes unconditional registration in the Kernel safe. An
        // unchanged value would not prove this: a filter that happens to be a no-op today
        // is still a filter, and still one more thing between a request and its response.
        new IndexingProtection()->register();

        expect(indexingHooks())->toBe([]);
    });

    it('leaves every value it would otherwise change untouched', function () {
        $robotsTxt = "User-agent: *\nSitemap: https://example.com/wp-sitemap.xml\n";

        new IndexingProtection()->register();

        expect(apply_filters('wp_robots', ['index' => true, 'follow' => true]))->toBe([
            'index' => true,
            'follow' => true,
        ]);
        expect(apply_filters('robots_txt', $robotsTxt, true))->toBe($robotsTxt);
        expect(apply_filters('wp_sitemaps_enabled', true))->toBeTrue();
    });

    it('writes no option either', function () {
        new IndexingProtection()->register();

        expect(wp_stub_get_calls('update_option'))->toBeEmpty();
    });

    it('reports itself inactive', function () {
        // Production verification reads this and expects false. It is the healthy answer
        // there, which is why the seam is a method rather than a private branch.
        expect(new IndexingProtection()->isActive())->toBeFalse();
    });
});
