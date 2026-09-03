<?php

declare(strict_types=1);

use Studiometa\Foehn\Smoke\Support\Client;
use Studiometa\Foehn\Smoke\Support\Site;

/**
 * The admin cache handlers, against a real `admin-post.php`.
 *
 * The unit suite proves the rules against function stubs, which is exactly what it cannot
 * prove here: that `admin-post.php` reaches these handlers at all, and that an
 * unauthenticated request to one of them is refused by WordPress and by Føhn rather than
 * by a stub that happened to return false.
 *
 * Two shapes of hostile request, and both leave the cache alone:
 *
 * - **a GET.** `admin-post.php` fires `admin_post_{action}` whatever the method was, so a
 *   link in an email or a prefetching browser would otherwise clear a production cache.
 * - **a POST with no valid nonce.** The capability check refuses this one first, which is
 *   the order the handlers promise — and the assertion is the same either way: the files
 *   that were there before are still there.
 *
 * Every case warms two pages first, so "nothing was deleted" is a claim about files that
 * existed rather than about an empty directory.
 */

beforeAll(function () {
    if (!Site::isRunning()) {
        return;
    }

    Site::enableCache();
});

afterAll(function () {
    if (!Site::isRunning()) {
        return;
    }

    Site::disableCache();
    Site::clearCache();
});

beforeEach(function () {
    if (!Site::isRunning()) {
        $this->markTestSkipped('ddev is not running — run `ddev start` and try again.');
    }

    Site::clearCache();

    // Two stored pages, so a refusal that deleted something would be visible.
    smokeWarm('/');
    smokeWarm('/?foehn_sections=posts');

    $this->stored = Site::cachedPages();

    expect($this->stored)->not->toBeEmpty('nothing was cached, so this file would pass for the wrong reason');
});

/**
 * The three actions, and one post id for the third.
 *
 * @return array<string, array<string, string>>
 */
function adminPostRequests(): array
{
    return [
        'foehn_cache_flush' => [],
        'foehn_cache_flush_sections' => [],
        'foehn_cache_forget_post' => ['foehn_post_id' => '1'],
    ];
}

function adminPostUrl(): string
{
    return Site::url() . '/wp/wp-admin/admin-post.php';
}

describe('admin cache handlers over HTTP, unauthenticated', function () {
    it('refuses a GET to each action and clears nothing', function () {
        foreach (adminPostRequests() as $action => $fields) {
            $query = http_build_query(['action' => $action, '_wpnonce' => 'anything', ...$fields]);
            $response = Client::get(adminPostUrl() . '?' . $query);

            expect($response->status)->not->toBe(302, $action . ' redirected as if it had worked');
            expect(Site::cachedPages())->toBe($this->stored, $action . ' deleted something for a GET');
        }
    });

    it('refuses a POST with no nonce and clears nothing', function () {
        foreach (adminPostRequests() as $action => $fields) {
            Client::post(adminPostUrl(), ['action' => $action, ...$fields]);

            expect(Site::cachedPages())->toBe($this->stored, $action . ' deleted something without a nonce');
        }
    });

    it('refuses a POST with an invented nonce and clears nothing', function () {
        foreach (adminPostRequests() as $action => $fields) {
            Client::post(adminPostUrl(), ['action' => $action, '_wpnonce' => 'deadbeef', ...$fields]);

            expect(Site::cachedPages())->toBe($this->stored, $action . ' accepted an invented nonce');
        }
    });

    it('never answers a refusal with a redirect an attacker could read as success', function () {
        // The handlers answer 403 rather than redirecting, so a probe cannot distinguish
        // "refused" from "cleared" by following a `Location`.
        $response = Client::post(adminPostUrl(), ['action' => 'foehn_cache_flush']);

        expect($response->header('location'))->toBeNull();
    });
});

describe('admin cache handlers inside a booted WordPress', function () {
    it('resolves the permalink server-side for a real post', function () {
        // Over HTTP this needs a logged-in administrator and a live nonce, neither of
        // which a smoke client can mint. Inside WordPress, with a `manage_options` user
        // set as current, the handler can be called for real — and what this asserts is
        // the one property the whole design rests on: the request supplies an id, and the
        // page that goes is the one `get_permalink()` names.
        $output = Site::wp('wp eval-file tests/smoke/admin-cache-assertions.php');

        expect($output)->not->toBeNull('wp eval-file could not run');
        expect($output)->toContain('assertions passed');
    });
});
