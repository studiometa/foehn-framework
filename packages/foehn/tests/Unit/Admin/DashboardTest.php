<?php

declare(strict_types=1);

use Studiometa\Foehn\Admin\CacheActions;
use Studiometa\Foehn\Admin\Dashboard;
use Studiometa\Foehn\Config\PageCacheConfig;
use Studiometa\Foehn\Cron\Heartbeat;
use Studiometa\Foehn\PageCache\CacheKey;
use Studiometa\Foehn\PageCache\Invalidator;
use Studiometa\Foehn\PageCache\Store;
use Studiometa\Foehn\Views\Sections\SectionRequest;

/**
 * The page an operator reads when a page is stale and they have no shell.
 *
 * Every assertion here is against real files in a real temporary cache root rather than
 * against a stubbed count. A dashboard whose numbers come from a double is a dashboard
 * that agrees with the test and not with the disk, which is the one failure this page
 * cannot afford: it is read precisely when the configuration is the thing under
 * suspicion.
 */

/**
 * Render the page and hand back its markup.
 */
function dashboardHtml(Dashboard $dashboard): string
{
    ob_start();
    $dashboard->render();

    return (string) ob_get_clean();
}

beforeEach(function () {
    wp_stub_reset();
    adminCacheRequestReset();

    $GLOBALS['wp_stub_user_can'][CacheActions::CAPABILITY] = true;

    $this->root = pageCacheRoot();
    $this->page = function (array $overrides = []): Dashboard {
        $config = new PageCacheConfig(...['enabled' => true, 'path' => $this->root, ...$overrides]);

        return new Dashboard($config, new Store($config), new Heartbeat());
    };
});

afterEach(function () {
    adminCacheRequestReset();
    removeTestDirectory($this->root);
});

describe('Dashboard: the environment it reports', function () {
    it('labels the two values with the constant names a report can name', function () {
        // Prose labels would be friendlier and useless: when somebody says "it thinks it
        // is staging", the two of you have to be looking at the same name.
        $html = dashboardHtml(($this->page)());

        expect($html)->toContain('WP_ENVIRONMENT_TYPE')->toContain('WP_DEBUG');
    });

    it('shows the environment as resolved, not as configured', function () {
        $GLOBALS['wp_stub_environment_type'] = 'staging';

        expect(dashboardHtml(($this->page)()))->toContain('<code>staging</code>');
    });

    it('shows WP_DEBUG as resolved', function () {
        // WP_DEBUG is defined false by the test bootstrap, which is the production shape.
        expect(dashboardHtml(($this->page)()))->toContain('WP_DEBUG');
        expect(dashboardHtml(($this->page)()))->toContain('<code>No</code>');
    });
});

describe('Dashboard: the cache it reports', function () {
    it('shows the configured state and the path from the config it was given', function () {
        $html = dashboardHtml(($this->page)());

        expect($html)->toContain('<code>' . htmlspecialchars($this->root, ENT_QUOTES) . '</code>');
        expect($html)->toContain('Configured');
    });

    it('calls the cache active when it is on and allowed here', function () {
        $GLOBALS['wp_stub_environment_type'] = 'production';

        expect(dashboardHtml(($this->page)()))->toContain('Active');
    });

    it('calls it disabled when nothing is switched on', function () {
        $config = new PageCacheConfig(enabled: false, path: $this->root);

        expect(dashboardHtml(new Dashboard($config, new Store($config), new Heartbeat())))->toContain('Disabled');
    });

    it('distinguishes enabled from enabled here', function () {
        // The third state, and the reason it has a row: `enabled: true` in an environment
        // the config does not list writes nothing and serves nothing, and a page that said
        // only "enabled" would send somebody hunting a broken cache rather than a missing
        // environment name.
        $GLOBALS['wp_stub_environment_type'] = 'staging';

        $html = dashboardHtml(($this->page)(['environments' => ['production']]));

        expect($html)->toContain('unavailable in staging');
        expect($html)->toContain('allowed: production');
    });

    it('reports the TTL, and says what a zero means', function () {
        expect(dashboardHtml(($this->page)()))->toContain('purge-driven');
        expect(dashboardHtml(($this->page)(['ttl' => 3600])))->toContain('3600 seconds');
    });

    it('counts what is actually on disk, pages and sections apart', function () {
        $store = pageCacheStore($this->root);
        $store->put(CacheKey::create('example.com', '/blog/'), str_repeat('x', 100));
        $store->put(CacheKey::create('example.com', '/about/'), str_repeat('x', 100));
        $store->put(CacheKey::create('example.com', '/blog/', SectionRequest::PARAMETER . '=posts&'), 'x');

        $html = dashboardHtml(($this->page)());

        // Three bodies in total, one of which is a section.
        expect($html)->toContain('Cached responses')->toContain('<code>3</code>');
        expect($html)->toContain('Section responses')->toContain('<code>1</code>');
        expect($html)->toContain('Total size');
    });

    it('reports an empty cache as empty rather than as blank', function () {
        $html = dashboardHtml(($this->page)());

        expect($html)->toContain('<code>0</code>')->toContain('0 B');
    });

    it('reports the last full purge the invalidator recorded', function () {
        // Through the invalidator rather than by writing the option, so this breaks if the
        // two ever stop naming the same row.
        pageCacheInvalidator($this->root)->flush();
        $GLOBALS['wp_stub_options'][Invalidator::LAST_FLUSH_OPTION] -= 7200;

        expect(dashboardHtml(($this->page)()))->toContain('Last full purge')->toContain('2 hours ago');
    });

    it('says nobody has purged when nobody has', function () {
        expect(dashboardHtml(($this->page)()))->toContain('Last full purge')->toContain('<code>Never</code>');
    });
});

describe('Dashboard: the cron heartbeat it reports', function () {
    it('reports the age of the recorded run', function () {
        $GLOBALS['wp_stub_options'][Heartbeat::OPTION] = time() - 600;

        expect(dashboardHtml(($this->page)()))->toContain('10 mins ago');
    });

    it('says never while nothing writes the option', function () {
        // The state of every site until the Docker cron runner ships, and of every site
        // whose runner is broken.
        expect(dashboardHtml(($this->page)()))->toContain('Last real run');
        expect(dashboardHtml(($this->page)()))->toContain('Never');
    });

    it('says never for a heartbeat that is not a timestamp', function () {
        $GLOBALS['wp_stub_options'][Heartbeat::OPTION] = 'broken';

        expect(dashboardHtml(($this->page)()))->toContain('Never');
    });
});

describe('Dashboard: the buttons', function () {
    it('posts each clear to admin-post.php as its own form', function () {
        $html = dashboardHtml(($this->page)());

        expect(substr_count($html, '<form method="post"'))->toBe(2);
        expect($html)->toContain('action="http://example.com/wp/wp-admin/admin-post.php"');
        expect($html)->toContain('value="' . CacheActions::FLUSH . '"');
        expect($html)->toContain('value="' . CacheActions::FLUSH_SECTIONS . '"');
    });

    it('gives each form a nonce minted for its own action', function () {
        // Two forms rather than one with two submits, so the token on "clear everything"
        // cannot authorise anything else.
        dashboardHtml(($this->page)());

        $minted = array_map(
            static fn(array $call): string => $call['args']['action'],
            wp_stub_get_calls('wp_nonce_field'),
        );

        expect($minted)->toBe([CacheActions::FLUSH, CacheActions::FLUSH_SECTIONS]);
    });

    it('needs no script to work', function () {
        // This page is the no-JavaScript path. The admin bar's controls need a script to
        // turn a menu item into a POST; these are plain forms, and that is what makes them
        // the ones an operator can rely on.
        expect(dashboardHtml(($this->page)()))->not->toContain('<script');
    });

    it('says out loud that clearing still works with caching off', function () {
        $config = new PageCacheConfig(enabled: false, path: $this->root);
        $html = dashboardHtml(new Dashboard($config, new Store($config), new Heartbeat()));

        expect(substr_count($html, '<form method="post"'))->toBe(2);
        expect($html)->toContain('switched off');
    });
});

describe('Dashboard: what it prints', function () {
    it('escapes a cache path carrying markup', function () {
        $root = $this->root . '/<script>alert(1)</script>';

        expect(dashboardHtml(($this->page)(['path' => $root])))
            ->not
            ->toContain('<script>alert(1)</script>')
            ->toContain('&lt;script&gt;');
    });

    it('escapes the environment name it was handed', function () {
        // `WP_ENVIRONMENT_TYPE` comes from a constant or an environment variable, which is
        // deployment configuration rather than user input — and a page that escapes only
        // what it thinks is dangerous is a page whose next value is not escaped.
        $GLOBALS['wp_stub_environment_type'] = '<img src=x onerror=alert(1)>';

        expect(dashboardHtml(($this->page)()))->not->toContain('<img src=x')->toContain('&lt;img');
    });

    it('matches a result code from the query string rather than printing it', function () {
        $_GET[CacheActions::RESULT_ARG] = '"><script>alert(1)</script>';
        $_GET[CacheActions::COUNT_ARG] = '3';

        $html = dashboardHtml(($this->page)());

        expect($html)->not->toContain('<script>alert(1)</script>');
        expect($html)->not->toContain('notice-success');
    });

    it('reports a clear with the count it was given, as an integer', function () {
        $_GET[CacheActions::RESULT_ARG] = CacheActions::CLEARED;
        $_GET[CacheActions::COUNT_ARG] = '4 <b>injected</b>';

        $html = dashboardHtml(($this->page)());

        expect($html)->toContain('notice-success')->toContain('4 cached responses removed.');
        expect($html)->not->toContain('<b>injected</b>');
    });

    it('reports a failure as a failure', function () {
        $_GET[CacheActions::RESULT_ARG] = CacheActions::FAILED;

        expect(dashboardHtml(($this->page)()))->toContain('notice-error');
    });
});

describe('Dashboard: how it is wired', function () {
    it('registers one top-level page for manage_options', function () {
        ($this->page)()->addPage();

        $added = wp_stub_get_calls('add_menu_page');

        expect($added)->toHaveCount(1);
        expect($added[0]['args']['menuSlug'])->toBe(CacheActions::PAGE);
        expect($added[0]['args']['capability'])->toBe(CacheActions::CAPABILITY);
    });

    it('owns no settings, so registers none', function () {
        // Explicitly not `#[AsSettingsPage]`: this page reads state and clears a cache. A
        // `register_setting()` here would be the start of the settings framework the spec
        // rules out.
        $page = ($this->page)();
        $page->addPage();
        dashboardHtml($page);

        expect(wp_stub_get_calls('register_setting'))->toBeEmpty();
        expect(wp_stub_get_calls('add_submenu_page'))->toBeEmpty();
    });

    it('adds the page on admin_menu and nothing else', function () {
        ($this->page)()->register();

        expect(array_map(
            static fn(array $call): string => $call['args']['hook'],
            wp_stub_get_calls('add_action'),
        ))->toBe(['admin_menu']);
    });

    it('renders nothing for a user who lost the capability between menu and render', function () {
        $GLOBALS['wp_stub_user_can'][CacheActions::CAPABILITY] = false;

        expect(dashboardHtml(($this->page)()))->toBe('');
    });
});
