<?php

declare(strict_types=1);

use Studiometa\Foehn\Admin\CacheActions;
use Studiometa\Foehn\Config\PageCacheConfig;
use Studiometa\Foehn\PageCache\CacheKey;
use Studiometa\Foehn\PageCache\Invalidator;
use Studiometa\Foehn\PageCache\Store;
use Studiometa\Foehn\Views\Sections\SectionRequest;

/**
 * The one place a browser request becomes a deletion.
 *
 * Every case below is about a request the handlers must refuse, or about the one they may
 * accept doing exactly what it said and nothing more. The refusals are the point: a
 * handler that clears correctly and refuses nothing is a remote cache-deletion endpoint.
 *
 * There is no double for {@see Invalidator} — it is final, and mocking it would prove
 * nothing about the files. Two observable things stand in for it: the tree on disk, which
 * says *what* was deleted, and {@see Invalidator::LAST_FLUSH_OPTION}, which `flush()`
 * writes and the other two operations do not — so its absence is proof that a section
 * clear did not quietly become a full one.
 */

/**
 * A cache holding one page, one 404, one keyed variant and one section fragment per URL.
 */
function cacheActionsFixture(Store $store, string $path): void
{
    $store->put(CacheKey::create('example.com', $path), '<html>page</html>', 200, ['Link: </a.css>; rel=preload']);
    $store->put(CacheKey::create('example.com', $path), '<html>gone</html>', 404);
    $store->put(CacheKey::create('example.com', $path, 'lang=fr&'), '<html>fr</html>');
    $store->put(CacheKey::create('example.com', $path, SectionRequest::PARAMETER . '=posts&'), '<div>posts</div>');
}

beforeEach(function () {
    wp_stub_reset();
    adminCacheRequestReset();

    $this->root = pageCacheRoot();
    $this->config = new PageCacheConfig(enabled: true, path: $this->root);
    $this->store = new Store($this->config);
    $this->actions = adminCacheActions(new Invalidator($this->config, $this->store));

    cacheActionsFixture($this->store, '/blog/');
    cacheActionsFixture($this->store, '/hello-world/');

    $this->post = pageCachePost(41, 'post', 'hello-world');

    $this->bodies = fn(): int => $this->store->stats()['files'];
    $this->flushed = fn(): bool => array_any(
        wp_stub_get_calls('update_option'),
        static fn(array $call): bool => $call['args']['option'] === Invalidator::LAST_FLUSH_OPTION,
    );
    $this->redirect = fn(): string => (string) (wp_stub_get_calls('wp_safe_redirect')[0]['args']['location'] ?? '');
});

afterEach(function () {
    adminCacheRequestReset();
    removeTestDirectory($this->root);
});

/**
 * Run every handler under one request shape and assert the cache is untouched.
 *
 * Each of the four checks is asserted against all three actions rather than against one:
 * three handlers with the same rules is three places for one of them to be forgotten.
 */
function expectEveryHandlerRefuses(object $test): void
{
    $before = ($test->bodies)();

    foreach (['flush', 'flushSections', 'forgetPost'] as $handler) {
        $test->actions->{$handler}();
    }

    expect(($test->bodies)())->toBe($before, 'a refused request deleted something');
    expect(($test->flushed)())->toBeFalse('a refused request recorded a purge');
    expect(wp_stub_get_calls('wp_safe_redirect'))->toBeEmpty('a refused request redirected as if it had worked');
    expect(wp_stub_get_calls('wp_die'))->toHaveCount(3);
}

describe('CacheActions: requests it refuses', function () {
    it('refuses a GET, which admin-post.php fires these hooks for too', function () {
        // `admin-post.php` calls `admin_post_{action}` whatever the method was, so this
        // check is the only thing between a production cache and a link in an email that
        // clears it for whoever clicks. A valid nonce and a capable user do not save it.
        adminCacheRequest(method: 'GET', nonce: wp_create_nonce(CacheActions::FLUSH), postId: 41);

        expectEveryHandlerRefuses($this);
    });

    it('refuses a user without manage_options', function () {
        adminCacheRequest(capable: false, nonce: wp_create_nonce(CacheActions::FLUSH), postId: 41);

        expectEveryHandlerRefuses($this);
    });

    it('refuses a request carrying no nonce', function () {
        adminCacheRequest(postId: 41);

        expectEveryHandlerRefuses($this);
    });

    it('refuses an invalid nonce', function () {
        adminCacheRequest(nonce: 'not-a-nonce', postId: 41);

        expectEveryHandlerRefuses($this);
    });

    it('refuses a nonce minted for a different action', function () {
        // What "action-specific" means, and the easiest of the four to get wrong: a single
        // shared nonce action would make every one of these pass authorisation. The token
        // here is a real, current, valid one — for the wrong action.
        adminCacheRequest(nonce: wp_create_nonce(CacheActions::FLUSH), postId: 41);

        $before = ($this->bodies)();

        $this->actions->flushSections();
        $this->actions->forgetPost();

        expect(($this->bodies)())->toBe($before);
        expect(wp_stub_get_calls('wp_die'))->toHaveCount(2);
    });

    it('does not accept a nonce from the query string', function () {
        // A `GET` is already refused, but a handler reading `$_REQUEST` would accept a
        // token a POST never carried — which is how a nonce that leaked into a URL becomes
        // an authorisation.
        adminCacheRequest();
        $_GET['_wpnonce'] = wp_create_nonce(CacheActions::FLUSH);

        $this->actions->flush();

        expect(($this->bodies)())->toBe(8);
        expect(wp_stub_get_calls('wp_die'))->toHaveCount(1);
    });

    it('answers a refusal with 403 and no detail about which check failed', function () {
        adminCacheRequest(method: 'GET');

        $this->actions->flush();

        $died = wp_stub_get_calls('wp_die')[0]['args'];

        expect($died['args']['response'])->toBe(403);
        expect($died['message'])->toBe('You are not allowed to clear this cache.');
    });

    it('stops rather than falling through after a refusal', function () {
        adminCacheRequest(method: 'GET');

        $this->actions->flush();

        expect($GLOBALS['wp_stub_halted'])->toBe(1);
    });
});

describe('CacheActions: clearing the whole cache', function () {
    beforeEach(function () {
        adminCacheRequest(nonce: wp_create_nonce(CacheActions::FLUSH));
    });

    it('empties the cache and reports how many bodies went', function () {
        $this->actions->flush();

        expect(($this->bodies)())->toBe(0);
        expect(($this->redirect)())->toContain(CacheActions::RESULT_ARG . '=' . CacheActions::CLEARED);
        expect(($this->redirect)())->toContain(CacheActions::COUNT_ARG . '=8');
    });

    it('records the purge once, and nothing else', function () {
        $this->actions->flush();

        // One write. Two would mean the handler called through twice, which on a large
        // cache is a second full tree walk nobody asked for.
        expect(array_filter(
            wp_stub_get_calls('update_option'),
            static fn(array $call): bool => $call['args']['option'] === Invalidator::LAST_FLUSH_OPTION,
        ))
            ->toHaveCount(1);
    });

    it('redirects through wp_safe_redirect and then stops', function () {
        $this->actions->flush();

        expect(wp_stub_get_calls('wp_safe_redirect'))->toHaveCount(1);
        expect($GLOBALS['wp_stub_halted'])->toBe(1);
    });

    it('works while page caching is switched off', function () {
        // The case this feature exists for: a release that had the cache on left files
        // behind, and the operator turning it off is the one who needs them gone.
        $disabled = adminCacheActions(pageCacheInvalidator($this->root, enabled: false));

        $disabled->flush();

        expect(($this->bodies)())->toBe(0);
        expect(($this->redirect)())->toContain(CacheActions::RESULT_ARG . '=' . CacheActions::CLEARED);
    });
});

describe('CacheActions: clearing the section cache', function () {
    beforeEach(function () {
        adminCacheRequest(nonce: wp_create_nonce(CacheActions::FLUSH_SECTIONS));
    });

    it('takes the section entries and leaves the pages', function () {
        $this->actions->flushSections();

        expect(($this->bodies)())->toBe(6);
        expect($this->root . '/example.com/blog/index.html')->toBeFile();
        expect($this->root . '/example.com/blog/index__foehn_sections=posts&.html')->not->toBeFile();
        expect(($this->redirect)())->toContain(CacheActions::COUNT_ARG . '=2');
    });

    it('does not quietly become a full flush', function () {
        $this->actions->flushSections();

        expect(($this->flushed)())->toBeFalse();
    });

    it('works while page caching is switched off', function () {
        adminCacheActions(pageCacheInvalidator($this->root, enabled: false))->flushSections();

        expect(($this->bodies)())->toBe(6);
    });
});

describe('CacheActions: clearing one post', function () {
    beforeEach(function () {
        adminCacheRequest(nonce: wp_create_nonce(CacheActions::FORGET_POST), postId: 41);
    });

    it('clears the post the id names, and nothing beside it', function () {
        $this->actions->forgetPost();

        expect($this->root . '/example.com/hello-world')->not->toBeDirectory();
        expect($this->root . '/example.com/blog/index.html')->toBeFile();
        expect(($this->redirect)())->toContain(CacheActions::COUNT_ARG . '=4');
        expect(($this->flushed)())->toBeFalse();
    });

    it('resolves the permalink itself and ignores a URL the request supplies', function () {
        // The security boundary this whole design exists for. The browser posts an id; the
        // server decides which URL that means. A handler that took the URL would be a
        // deletion endpoint pointed wherever the caller liked.
        adminCacheRequest(nonce: wp_create_nonce(CacheActions::FORGET_POST), postId: 41, extra: [
            'url' => 'https://example.com/blog/',
            'path' => $this->root . '/example.com/blog',
            'permalink' => 'https://example.com/blog/',
            'cache_key' => 'example.com/blog',
        ]);

        $this->actions->forgetPost();

        // `/blog/` is untouched, though every field above pointed at it.
        expect($this->root . '/example.com/blog/index.html')->toBeFile();
        expect($this->root . '/example.com/hello-world')->not->toBeDirectory();
    });

    it('follows the permalink WordPress reports, not the post slug', function () {
        // Proof the resolution really goes through `get_permalink()`: the stored page is at
        // a path the slug does not produce, and the clear still finds it.
        $this->store->put(CacheKey::create('example.com', '/2026/moved/'), '<html>moved</html>');
        $GLOBALS['wp_stub_permalinks'][41] = 'http://example.com/2026/moved/';

        $this->actions->forgetPost();

        expect($this->root . '/example.com/2026/moved')->not->toBeDirectory();
        expect($this->root . '/example.com/hello-world/index.html')->toBeFile();
    });

    it('refuses a post id that is missing, zero or not a number', function () {
        foreach ([null, 0, -41] as $id) {
            wp_stub_reset();
            $actions = adminCacheActions(new Invalidator($this->config, $this->store));
            adminCacheRequest(nonce: wp_create_nonce(CacheActions::FORGET_POST), postId: $id);

            $actions->forgetPost();

            expect(wp_stub_get_calls('wp_die'))->toHaveCount(1, var_export($id, true) . ' should be refused');
        }

        foreach (['', 'abc', '41; DROP TABLE', '../../etc/passwd'] as $raw) {
            wp_stub_reset();
            $actions = adminCacheActions(new Invalidator($this->config, $this->store));
            adminCacheRequest(nonce: wp_create_nonce(CacheActions::FORGET_POST));
            $_POST[CacheActions::POST_ID_FIELD] = $raw;

            $actions->forgetPost();

            expect(wp_stub_get_calls('wp_die'))->toHaveCount(1, var_export($raw, true) . ' should be refused');
        }

        expect(($this->bodies)())->toBe(8);
    });

    it('refuses an id no post row answers to', function () {
        adminCacheRequest(nonce: wp_create_nonce(CacheActions::FORGET_POST), postId: 9999);

        $this->actions->forgetPost();

        expect(wp_stub_get_calls('wp_die'))->toHaveCount(1);
        expect(($this->bodies)())->toBe(8);
    });

    it('refuses a post no visitor could have been served', function () {
        // A draft has no cached page, so an id that resolves to one is either a mistake or
        // a probe. Refusing is both correct and the cheaper thing to reason about.
        $this->post->post_status = 'draft';

        $this->actions->forgetPost();

        expect(wp_stub_get_calls('wp_die'))->toHaveCount(1);
        expect(($this->bodies)())->toBe(8);
    });

    it('reports a failure rather than a clear of nothing for an unkeyable permalink', function () {
        // `forgetUrl()` answers null when a URL cannot become a cache key, and that is a
        // different fact from "nothing was cached". Flattening the two would report success
        // for a permalink structure this cache refuses.
        $GLOBALS['wp_stub_permalinks'][41] = '/relative/with/no/host/';

        $this->actions->forgetPost();

        expect(($this->redirect)())->toContain(CacheActions::RESULT_ARG . '=' . CacheActions::FAILED);
        expect(($this->bodies)())->toBe(8);
    });

    it('works while page caching is switched off', function () {
        adminCacheActions(pageCacheInvalidator($this->root, enabled: false))->forgetPost();

        expect($this->root . '/example.com/hello-world')->not->toBeDirectory();
    });
});

describe('CacheActions: where it sends the browser', function () {
    beforeEach(function () {
        adminCacheRequest(nonce: wp_create_nonce(CacheActions::FLUSH));
    });

    it('falls back to the Føhn dashboard when there is no referrer', function () {
        $this->actions->flush();

        expect(($this->redirect)())->toStartWith('http://example.com/wp/wp-admin/admin.php?');
        expect(($this->redirect)())->toContain('page=' . CacheActions::PAGE);
    });

    it('returns to a referrer on this site', function () {
        $GLOBALS['wp_stub_referer'] = 'http://example.com/wp/wp-admin/edit.php?post_type=page';

        $this->actions->flush();

        expect(($this->redirect)())->toStartWith('http://example.com/wp/wp-admin/edit.php?');
        expect(($this->redirect)())->toContain('post_type=page');
    });

    it('refuses a referrer pointing off this site', function () {
        // Validated through WordPress's own means rather than by a pattern of ours. A
        // handler that trusted `_wp_http_referer` would be an open redirect behind an
        // authenticated POST — which is the shape of it that gets used.
        $GLOBALS['wp_stub_referer'] = 'https://evil.test/collect';

        $this->actions->flush();

        expect(($this->redirect)())->toStartWith('http://example.com/wp/wp-admin/admin.php?');
    });

    it('carries only a fixed result code and an integer count', function () {
        $GLOBALS['wp_stub_referer'] = 'http://example.com/wp/wp-admin/admin.php?page=' . CacheActions::PAGE;

        $this->actions->flush();

        $query = [];
        parse_str((string) parse_url((string) ($this->redirect)(), PHP_URL_QUERY), $query);

        expect(array_keys($query))->toBe(['page', CacheActions::RESULT_ARG, CacheActions::COUNT_ARG]);
        expect($query[CacheActions::RESULT_ARG])->toBe(CacheActions::CLEARED);
        expect($query[CacheActions::COUNT_ARG])->toBe('8');
    });

    it('does not accumulate its own args when a button is pressed twice', function () {
        $GLOBALS['wp_stub_referer'] =
            'http://example.com/wp/wp-admin/admin.php?page='
            . CacheActions::PAGE
            . '&'
            . CacheActions::RESULT_ARG
            . '='
            . CacheActions::CLEARED
            . '&'
            . CacheActions::COUNT_ARG
            . '=99';

        $this->actions->flush();

        expect(substr_count((string) ($this->redirect)(), CacheActions::RESULT_ARG . '='))->toBe(1);
        expect(($this->redirect)())->toContain(CacheActions::COUNT_ARG . '=8');
    });

    it('never puts a path or a URL from the request into the answer', function () {
        adminCacheRequest(nonce: wp_create_nonce(CacheActions::FORGET_POST), postId: 41, extra: [
            'url' => 'https://evil.test/x',
            'path' => '/var/www/secret',
        ]);

        $this->actions->forgetPost();

        expect(($this->redirect)())->not->toContain('evil.test');
        expect(($this->redirect)())->not->toContain('secret');
        expect(($this->redirect)())->not->toContain($this->root);
    });
});

describe('CacheActions: how it is wired', function () {
    it('registers one handler per action, and none for anonymous callers', function () {
        $this->actions->register();

        $hooks = array_map(static fn(array $call): string => $call['args']['hook'], wp_stub_get_calls('add_action'));

        expect($hooks)->toBe([
            'admin_post_' . CacheActions::FLUSH,
            'admin_post_' . CacheActions::FLUSH_SECTIONS,
            'admin_post_' . CacheActions::FORGET_POST,
        ]);
    });

    it('gives every action a nonce action string of its own', function () {
        $strings = array_map(static fn(string $action): string => CacheActions::nonceAction(
            $action,
        ), [CacheActions::FLUSH, CacheActions::FLUSH_SECTIONS, CacheActions::FORGET_POST]);

        expect(array_unique($strings))->toHaveCount(3);
    });

    it('posts to admin-post.php', function () {
        expect(CacheActions::endpoint())->toBe('http://example.com/wp/wp-admin/admin-post.php');
    });
});
