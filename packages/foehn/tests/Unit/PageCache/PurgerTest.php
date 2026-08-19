<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\PageCacheConfig;
use Studiometa\Foehn\PageCache\CacheKey;
use Studiometa\Foehn\PageCache\Purger;
use Studiometa\Foehn\PageCache\Store;

describe('Purger: what a post invalidates', function () {
    beforeEach(function () {
        wp_stub_reset();
        $this->root = sys_get_temp_dir() . '/foehn-tests/purger-' . uniqid('', true);
        $this->config = new PageCacheConfig(enabled: true, path: $this->root);
        $this->store = new Store($this->config);
        $this->purger = new Purger($this->config, $this->store);
    });

    it('takes the post, the front page, the author archive and the month', function () {
        $this->purger->purgePost(pageCachePost(12)->ID);

        expect($this->purger->targets())->toBe([
            'http://example.com/',
            'http://example.com/2026/08/',
            'http://example.com/author/7/',
            'http://example.com/hello-world/',
        ]);
    });

    it('takes the month archive the post appears in', function () {
        $post = pageCachePost(12);
        $post->post_date = '2024-03-07 10:00:00';

        $this->purger->purgePost($post->ID);

        expect($this->purger->targets())->toContain('http://example.com/2024/03/');
    });

    it('takes the post type archive when the type has one', function () {
        $GLOBALS['wp_stub_archive_links']['product'] = 'http://example.com/products/';

        $this->purger->purgePost(pageCachePost(12, 'product', 'a-product')->ID);

        expect($this->purger->targets())->toContain('http://example.com/products/');
    });

    it('takes the posts page when the front page is static', function () {
        $GLOBALS['wp_stub_options']['show_on_front'] = 'page';
        $GLOBALS['wp_stub_options']['page_for_posts'] = 41;
        $GLOBALS['wp_stub_permalinks'][41] = 'http://example.com/journal/';

        $this->purger->purgePost(pageCachePost(12)->ID);

        expect($this->purger->targets())->toContain('http://example.com/journal/');
    });

    it('takes every term archive the post belongs to', function () {
        $GLOBALS['wp_stub_object_taxonomies']['post'] = ['category', 'post_tag'];
        $GLOBALS['wp_stub_post_terms'][12] = [
            'category' => [pageCacheTerm(1, 'news', 'category')],
            'post_tag' => [pageCacheTerm(2, 'php', 'post_tag'), pageCacheTerm(3, 'wordpress', 'post_tag')],
        ];

        $this->purger->purgePost(pageCachePost(12)->ID);

        expect($this->purger->targets())
            ->toContain('http://example.com/category/news/')
            ->toContain('http://example.com/post_tag/php/')
            ->toContain('http://example.com/post_tag/wordpress/');
    });

    it('takes the ancestors, which list the page that changed', function () {
        $GLOBALS['wp_stub_post_ancestors'][12] = [4, 2];
        $GLOBALS['wp_stub_permalinks'][4] = 'http://example.com/services/';
        $GLOBALS['wp_stub_permalinks'][2] = 'http://example.com/';

        $this->purger->purgePost(pageCachePost(12, 'page', 'consulting')->ID);

        expect($this->purger->targets())->toContain('http://example.com/services/');
    });

    it('takes both neighbours, whose previous/next links now point elsewhere', function () {
        $GLOBALS['wp_stub_adjacent_posts'][12] = [
            'previous' => pageCachePost(11, 'post', 'older'),
            'next' => pageCachePost(13, 'post', 'newer'),
        ];

        $this->purger->purgePost(pageCachePost(12)->ID);

        expect($this->purger->targets())
            ->toContain('http://example.com/older/')
            ->toContain('http://example.com/newer/');
    });

    it('lets a project add a page only it knows goes stale', function () {
        add_filter('foehn/page_cache/purge_urls', static fn(array $urls): array => [
            ...$urls,
            'http://example.com/sitemap-page/',
        ]);

        $this->purger->purgePost(pageCachePost(12)->ID);

        expect($this->purger->targets())->toContain('http://example.com/sitemap-page/');
    });

    it('lets a project drop a target it knows is safe', function () {
        add_filter('foehn/page_cache/purge_urls', static fn(array $urls): array => array_values(array_filter(
            $urls,
            static fn(string $url): bool => $url !== 'http://example.com/',
        )));

        $this->purger->purgePost(pageCachePost(12)->ID);

        expect($this->purger->targets())->not->toContain('http://example.com/');
    });

    it('flushes rather than walking a long tail of terms', function () {
        // Past fifty terms, enumerating the archives costs more than rebuilding the
        // whole cache does, and the result is the same either way.
        $terms = [];

        for ($i = 1; $i <= 60; $i++) {
            $terms[] = pageCacheTerm($i, 'tag-' . $i, 'post_tag');
        }

        $GLOBALS['wp_stub_object_taxonomies']['post'] = ['post_tag'];
        $GLOBALS['wp_stub_post_terms'][12] = ['post_tag' => $terms];

        $this->purger->purgePost(pageCachePost(12)->ID);

        expect($this->purger->isFlushQueued())->toBeTrue();
        expect($this->purger->targets())->toBe([]);
    });

    it('ignores a post type that never had a URL', function (string $type) {
        $this->purger->purgePost(pageCachePost(12, $type)->ID);

        expect($this->purger->targets())->toBe([]);
    })->with([['revision'], ['nav_menu_item'], ['wp_template'], ['wp_block']]);

    it('ignores an auto-draft, which no visitor has seen', function () {
        $post = pageCachePost(12);
        $post->post_status = 'auto-draft';

        $this->purger->purgePost($post->ID);

        expect($this->purger->targets())->toBe([]);
    });

    it('ignores a post id nothing answers to', function () {
        $this->purger->purgePost(999);

        expect($this->purger->targets())->toBe([]);
    });
});

describe('Purger: batching', function () {
    beforeEach(function () {
        wp_stub_reset();
        $this->root = sys_get_temp_dir() . '/foehn-tests/purger-' . uniqid('', true);
        $this->config = new PageCacheConfig(enabled: true, path: $this->root);
        $this->store = new Store($this->config);
        $this->purger = new Purger($this->config, $this->store);
    });

    afterEach(function () {
        removeTestDirectory($this->root);
    });

    it('collects a target once however often it is queued', function () {
        // A bulk edit queues the front page forty times. It is deleted once.
        for ($i = 1; $i <= 5; $i++) {
            $this->purger->purgePost(pageCachePost($i, 'post', 'post-' . $i)->ID);
        }

        expect(array_count_values($this->purger->targets())['http://example.com/'] ?? 0)->toBe(1);
    });

    it('deletes nothing until the request ends', function () {
        $this->store->put(CacheKey::create('example.com', '/hello-world/'), '<html>hi</html>');

        $this->purger->purgePost(pageCachePost(12)->ID);

        expect($this->store->has(CacheKey::create('example.com', '/hello-world/')))->toBeTrue();

        $this->purger->commit();

        expect($this->store->has(CacheKey::create('example.com', '/hello-world/')))->toBeFalse();
    });

    it('takes an archive pagination with the archive it queued', function () {
        $this->store->put(CacheKey::create('example.com', '/'), '<html>home</html>');
        $this->store->put(CacheKey::create('example.com', '/page/2/'), '<html>home 2</html>');

        $this->purger->purgePost(pageCachePost(12)->ID);
        $this->purger->commit();

        expect($this->store->has(CacheKey::create('example.com', '/page/2/')))->toBeFalse();
    });

    it('leaves a single post pagination alone, which is a different page', function () {
        // `<!--nextpage-->` paginates a post at /hello-world/2/, not under page/.
        $this->store->put(CacheKey::create('example.com', '/hello-world/2/'), '<html>part 2</html>');

        $this->purger->purgePost(pageCachePost(12)->ID);
        $this->purger->commit();

        expect($this->store->has(CacheKey::create('example.com', '/hello-world/2/')))->toBeTrue();
    });

    it('empties the whole cache when a flush is queued', function () {
        $this->store->put(CacheKey::create('example.com', '/one/'), '<html>one</html>');
        $this->store->put(CacheKey::create('example.com', '/two/'), '<html>two</html>');

        $this->purger->queueFlush();
        $this->purger->commit();

        expect($this->store->stats()['files'])->toBe(0);
    });

    it('drops the targets it had queued when a flush supersedes them', function () {
        $this->purger->purgePost(pageCachePost(12)->ID);
        $this->purger->queueFlush();

        expect($this->purger->targets())->toBe([]);
        expect($this->purger->isFlushQueued())->toBeTrue();
    });

    it('queues nothing more once a flush is queued', function () {
        $this->purger->queueFlush();
        $this->purger->purgePost(pageCachePost(12)->ID);

        expect($this->purger->targets())->toBe([]);
    });

    it('can commit twice without acting twice', function () {
        // `shutdown` can fire more than once in a WP-CLI process, and the second run
        // must not walk directories the first one removed.
        $this->store->put(CacheKey::create('example.com', '/hello-world/'), '<html>hi</html>');

        $this->purger->purgePost(pageCachePost(12)->ID);
        $this->purger->commit();
        $this->purger->commit();

        expect($this->purger->targets())->toBe([]);
    });

    it('flushes instead of purging during an import', function () {
        // An import purges once per post, thousands of times. One flush is cheaper,
        // and correct for the same reason.
        //
        // In a separate process, because WP_IMPORTING is a constant: defining it here
        // would leave every later test in this run behaving as if an import were
        // running, which is exactly the kind of green nobody should trust.
        $script = <<<'PHP'
            require %s;
            define('WP_IMPORTING', true);
            $config = new Studiometa\Foehn\Config\PageCacheConfig(enabled: true, path: sys_get_temp_dir() . '/foehn-import');
            $purger = new Studiometa\Foehn\PageCache\Purger($config, new Studiometa\Foehn\PageCache\Store($config));
            $post = new WP_Post();
            $post->ID = 12;
            $GLOBALS['wp_stub_posts'][12] = $post;
            $purger->purgePost(12);
            echo $purger->isFlushQueued() ? 'flush' : implode(',', $purger->targets());
            PHP;

        $output = [];
        $status = 0;
        exec(
            'php -r '
            . escapeshellarg(sprintf($script, var_export(dirname(__DIR__, 2) . '/bootstrap.php', true)))
            . ' 2>&1',
            $output,
            $status,
        );

        expect($status)->toBe(0, implode("\n", $output));
        expect($output[0] ?? '')->toBe('flush');
    });

    it('does nothing at all while the cache is off', function () {
        $config = new PageCacheConfig(enabled: false, path: $this->root);
        $purger = new Purger($config, new Store($config));

        $purger->purgePost(pageCachePost(12)->ID);

        expect($purger->targets())->toBe([]);
    });
});

describe('Purger: terms and comments', function () {
    beforeEach(function () {
        wp_stub_reset();
        $this->root = sys_get_temp_dir() . '/foehn-tests/purger-' . uniqid('', true);
        $this->config = new PageCacheConfig(enabled: true, path: $this->root);
        $this->purger = new Purger($this->config, new Store($this->config));
    });

    it('takes a term archive and the front page when a term is edited', function () {
        $this->purger->purgeTerm(pageCacheTerm(4, 'news', 'category'), 4, 'category');

        expect($this->purger->targets())->toBe(['http://example.com/', 'http://example.com/category/news/']);
    });

    it('builds a deleted term URL from the object the hook hands over', function () {
        // `delete_term` fires after the row is gone, so a term id resolves to nothing.
        // Its fourth argument is the only thing left that knows the slug.
        $this->purger->purgeTerm(4, 9, 'category', pageCacheTerm(4, 'gone', 'category'));

        expect($this->purger->targets())->toContain('http://example.com/category/gone/');
    });

    it('takes the post a comment was left on', function () {
        pageCachePost(12);
        $GLOBALS['wp_stub_comments'][3] = (object) ['comment_post_ID' => 12];

        $this->purger->purgeComment(3);

        expect($this->purger->targets())->toContain('http://example.com/hello-world/');
    });

    it('takes the post when a comment leaves moderation', function () {
        pageCachePost(12);

        $this->purger->purgeCommentTransition('approved', 'hold', (object) ['comment_post_ID' => 12]);

        expect($this->purger->targets())->toContain('http://example.com/hello-world/');
    });

    it('ignores a comment transition that changed nothing', function () {
        pageCachePost(12);

        $this->purger->purgeCommentTransition('approved', 'approved', (object) ['comment_post_ID' => 12]);

        expect($this->purger->targets())->toBe([]);
    });
});
