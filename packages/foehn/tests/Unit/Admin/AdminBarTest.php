<?php

declare(strict_types=1);

use Studiometa\Foehn\Admin\AdminBar;
use Studiometa\Foehn\Admin\CacheActions;

/**
 * The three clears, one click from wherever an editor already is.
 *
 * Two properties carry the security of this control and both are asserted here rather
 * than reasoned about. **No node href changes anything**, because an admin-bar item is a
 * link and a link that cleared a cache would be cleared by every prefetching browser and
 * every crawler holding a logged-in cookie. **The current-item entry appears only when
 * WordPress supplied the id**, because an item built from the query string is an item
 * that appears on a URL somebody crafted, pointing at a post they chose.
 */

/**
 * The nodes the bar collected for one request shape.
 *
 * @return array<string, array<string, mixed>>
 */
function adminBarNodes(AdminBar $bar): array
{
    $collected = new WP_Admin_Bar();
    $bar->addNodes($collected);

    return $collected->nodes;
}

/**
 * The footer markup for one request shape.
 */
function adminBarFooter(AdminBar $bar): string
{
    ob_start();
    $bar->renderForms();

    return (string) ob_get_clean();
}

/**
 * Present a singular front-end request for a published post.
 */
function adminBarOnSinglePost(int $id = 41): void
{
    $GLOBALS['wp_stub_is_admin'] = false;
    $GLOBALS['wp_stub_template'] = 'single';
    $GLOBALS['wp_stub_queried_object'] = pageCachePost($id);
}

beforeEach(function () {
    wp_stub_reset();
    adminCacheRequestReset();

    $GLOBALS['wp_stub_user_can'][CacheActions::CAPABILITY] = true;

    $this->bar = new AdminBar();
});

afterEach(function () {
    adminCacheRequestReset();
});

describe('AdminBar: who sees it', function () {
    it('adds a Føhn Cache node for manage_options', function () {
        $nodes = adminBarNodes($this->bar);

        expect($nodes)->toHaveKey('foehn-cache');
        expect($nodes['foehn-cache']['title'])->toBe('Føhn Cache');
    });

    it('adds nothing for a user without manage_options', function () {
        $GLOBALS['wp_stub_user_can'][CacheActions::CAPABILITY] = false;

        expect(adminBarNodes($this->bar))->toBe([]);
        expect(adminBarFooter($this->bar))->toBe('');
    });

    it('hangs the two always-available clears off the node', function () {
        $nodes = adminBarNodes($this->bar);

        expect($nodes)->toHaveKey('foehn-cache-' . CacheActions::FLUSH);
        expect($nodes)->toHaveKey('foehn-cache-' . CacheActions::FLUSH_SECTIONS);
        expect($nodes['foehn-cache-' . CacheActions::FLUSH]['parent'])->toBe('foehn-cache');
    });
});

describe('AdminBar: nothing mutates through a link', function () {
    it('gives no item an href that would change anything', function () {
        // The assertion this file exists for. The parent node navigates to the dashboard;
        // every child is inert and the footer script does the work.
        adminBarOnSinglePost();

        foreach (adminBarNodes($this->bar) as $id => $node) {
            $href = (string) ($node['href'] ?? '');

            expect($href)->not->toContain('admin-post.php', $id . ' points at the mutation endpoint');
            expect($href)->not->toContain('action=', $id . ' carries an action in a URL');
            expect($href)->not->toContain('_wpnonce', $id . ' carries a nonce in a URL');
        }
    });

    it('points the parent node at the dashboard and nowhere else', function () {
        expect(adminBarNodes($this->bar)['foehn-cache']['href'])->toBe(CacheActions::dashboardUrl());
    });

    it('leaves every child href inert', function () {
        adminBarOnSinglePost();

        foreach (adminBarNodes($this->bar) as $id => $node) {
            if ($id === 'foehn-cache') {
                continue;
            }

            expect($node['href'])->toBe('#', $id . ' is not inert');
        }
    });
});

describe('AdminBar: the hidden forms', function () {
    it('posts each action to admin-post.php with a nonce of its own', function () {
        $html = adminBarFooter($this->bar);

        expect($html)->toContain('action="http://example.com/wp/wp-admin/admin-post.php"');
        expect(array_map(
            static fn(array $call): string => $call['args']['action'],
            wp_stub_get_calls('wp_nonce_field'),
        ))->toBe([CacheActions::FLUSH, CacheActions::FLUSH_SECTIONS]);
    });

    it('renders one form per item and no more', function () {
        expect(substr_count(adminBarFooter($this->bar), '<form method="post"'))->toBe(2);

        adminBarOnSinglePost();

        expect(substr_count(adminBarFooter($this->bar), '<form method="post"'))->toBe(3);
    });

    it('binds each item to its form with a small inline script', function () {
        $html = adminBarFooter($this->bar);

        expect($html)->toContain('<script')->toContain('preventDefault');
        expect($html)->toContain('wp-admin-bar-foehn-cache-' . CacheActions::FLUSH);
        expect($html)->toContain('foehn-cache-form-' . CacheActions::FLUSH);
    });

    it('registers the forms in both footers and the node on admin_bar_menu', function () {
        $this->bar->register();

        expect(array_map(
            static fn(array $call): string => $call['args']['hook'],
            wp_stub_get_calls('add_action'),
        ))->toBe(['admin_bar_menu', 'wp_footer', 'admin_footer']);
    });
});

describe('AdminBar: the current item', function () {
    it('appears for a singular front-end request', function () {
        adminBarOnSinglePost();

        $nodes = adminBarNodes($this->bar);

        expect($nodes)->toHaveKey('foehn-cache-' . CacheActions::FORGET_POST);
        expect($nodes['foehn-cache-' . CacheActions::FORGET_POST]['title'])->toBe('Clear this page');
    });

    it('appears on a post edit screen', function () {
        $GLOBALS['wp_stub_is_admin'] = true;
        $GLOBALS['wp_stub_current_screen'] = new WP_Screen('post', 'edit');
        pageCachePost(41);
        $GLOBALS['wp_stub_post'] = $GLOBALS['wp_stub_posts'][41];

        expect(adminBarNodes($this->bar))->toHaveKey('foehn-cache-' . CacheActions::FORGET_POST);
    });

    it('is absent on an archive, a search page and the front page', function () {
        // Absent rather than disabled or guessing: an item that appears everywhere and
        // works somewhere is one nobody learns to trust.
        foreach (['archive', 'search', 'front-page', 'home', '404'] as $template) {
            wp_stub_reset();
            $GLOBALS['wp_stub_user_can'][CacheActions::CAPABILITY] = true;
            $GLOBALS['wp_stub_template'] = $template;

            expect(adminBarNodes($this->bar))
                ->not
                ->toHaveKey('foehn-cache-' . CacheActions::FORGET_POST, 'appeared on ' . $template);
        }
    });

    it('is absent on an admin screen that is not a post being edited', function () {
        foreach ([
            null,
            new WP_Screen('edit', ''),
            new WP_Screen('post', 'add'),
            new WP_Screen('options-general', ''),
        ] as $screen) {
            wp_stub_reset();
            $GLOBALS['wp_stub_user_can'][CacheActions::CAPABILITY] = true;
            $GLOBALS['wp_stub_is_admin'] = true;
            $GLOBALS['wp_stub_current_screen'] = $screen;
            pageCachePost(41);
            $GLOBALS['wp_stub_post'] = $GLOBALS['wp_stub_posts'][41];

            expect(adminBarNodes($this->bar))->not->toHaveKey('foehn-cache-' . CacheActions::FORGET_POST);
        }
    });

    it('is absent for a post no visitor could have been served', function () {
        // A draft has no cached page, and `CacheActions` would refuse the id — so showing
        // the item would be offering a control that is always refused.
        adminBarOnSinglePost();
        $GLOBALS['wp_stub_posts'][41]->post_status = 'draft';

        expect(adminBarNodes($this->bar))->not->toHaveKey('foehn-cache-' . CacheActions::FORGET_POST);
    });

    it('takes the id from WordPress and never from the query string', function () {
        // `$_GET['post']` names a different post from the one WordPress resolved. The form
        // must carry the resolved one, because a control whose target the browser chooses
        // is the thing this whole design exists to avoid.
        adminBarOnSinglePost(41);
        $_GET['post'] = '99';
        $_GET[CacheActions::POST_ID_FIELD] = '99';

        $html = adminBarFooter($this->bar);

        expect($html)->toContain('name="' . CacheActions::POST_ID_FIELD . '" value="41"');
        expect($html)->not->toContain('value="99"');
    });

    it('posts an id and nothing else — no URL and no path', function () {
        adminBarOnSinglePost();

        $html = adminBarFooter($this->bar);
        $fields = [];
        preg_match_all('/name="([^"]+)"/', $html, $fields);
        $names = array_values(array_unique($fields[1]));
        sort($names);

        expect($names)->toBe(['_wpnonce', 'action', CacheActions::POST_ID_FIELD]);
    });
});
