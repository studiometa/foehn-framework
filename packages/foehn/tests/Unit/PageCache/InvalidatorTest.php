<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\PageCacheConfig;
use Studiometa\Foehn\PageCache\CacheKey;
use Studiometa\Foehn\PageCache\Invalidator;
use Studiometa\Foehn\PageCache\Store;
use Studiometa\Foehn\Views\Sections\SectionRequest;

/**
 * Fill a cache with one page, one 404, one keyed variant and one section fragment for
 * the same URL, plus an unrelated page — the shape every assertion below needs.
 */
function invalidatorFixture(Store $store, string $path = '/blog/'): void
{
    $store->put(CacheKey::create('example.com', $path), '<html>page</html>', 200, ['Link: </a.css>; rel=preload']);
    $store->put(CacheKey::create('example.com', $path), '<html>gone</html>', 404);
    $store->put(CacheKey::create('example.com', $path, 'lang=fr&'), '<html>fr</html>');
    $store->put(
        CacheKey::create('example.com', $path, SectionRequest::PARAMETER . '=posts&'),
        '<div>posts</div>',
        200,
        ['X-Robots-Tag: noindex, nofollow'],
    );
}

beforeEach(function () {
    wp_stub_reset();

    $this->root = pageCacheRoot();
    $this->config = new PageCacheConfig(enabled: true, path: $this->root);
    $this->store = new Store($this->config);
    $this->invalidator = new Invalidator($this->config, $this->store);

    $this->files = fn(): array => array_map(fn(string $file): string => substr(
        $file,
        strlen($this->root) + 1,
    ), array_values(array_filter((array) glob($this->root . '/**/**/*'), 'is_file')));
});

afterEach(function () {
    removeTestDirectory($this->root);
});

describe('Invalidator: clearing everything', function () {
    it('empties the cache and counts pages rather than files', function () {
        invalidatorFixture($this->store);

        // Four bodies and two sidecars are on disk. The count is four: an operator told
        // "six" for four pages has been handed a number they cannot act on.
        expect($this->invalidator->flush())->toBe(4);
        expect($this->store->stats()['files'])->toBe(0);
        expect(($this->files)())->toBe([]);
    });

    it('has nothing to remove from a cache nobody has written to', function () {
        expect($this->invalidator->flush())->toBe(0);
    });

    it('leaves everything outside its own root alone', function () {
        // The cache root is `…/cache/foehn/pages`, and its siblings are the discovery
        // cache and the transformed images — different lifecycles, and none of them a
        // thing a content edit should touch. This is the assertion that keeps a content
        // purge from turning into a cold start.
        // A cache root nested one level down, so this test owns the parent directory
        // rather than the one every other case in the file shares.
        $foehn = pageCacheRoot();
        $root = $foehn . '/pages';
        mkdir($foehn . '/discovery', 0o755, true);
        file_put_contents($foehn . '/discovery/items.php', '<?php return [];');
        mkdir($foehn . '/images', 0o755, true);
        file_put_contents($foehn . '/images/photo.jpg', 'binary');

        try {
            $config = new PageCacheConfig(enabled: true, path: $root);
            invalidatorFixture(new Store($config));

            expect(new Invalidator($config, new Store($config))->flush())->toBe(4);

            expect($foehn . '/discovery/items.php')->toBeFile();
            expect($foehn . '/images/photo.jpg')->toBeFile();
        } finally {
            removeTestDirectory($foehn);
        }
    });

    it('clears what an earlier release left behind while caching is off', function () {
        // Nothing here reads `enabled`, on purpose: a project turning the cache off is
        // exactly the one who needs the stored pages gone.
        invalidatorFixture($this->store);

        $disabled = pageCacheInvalidator($this->root, enabled: false);

        expect($disabled->flush())->toBe(4);
        expect(($this->files)())->toBe([]);
    });
});

describe('Invalidator: clearing the section cache only', function () {
    it('removes the section entries and their sidecars', function () {
        invalidatorFixture($this->store);

        expect($this->invalidator->flushSections())->toBe(1);

        $directory = $this->root . '/example.com/blog';
        expect($directory . '/index__foehn_sections=posts&.html')->not->toBeFile();
        expect($directory . '/index__foehn_sections=posts&.html.headers')->not->toBeFile();
    });

    it('preserves the whole page, its 404 and its unrelated keyed variants', function () {
        invalidatorFixture($this->store);
        $this->invalidator->flushSections();

        $directory = $this->root . '/example.com/blog';
        expect($directory . '/index.html')->toBeFile();
        expect($directory . '/index.html.headers')->toBeFile();
        expect($directory . '/index--404.html')->toBeFile();
        expect($directory . '/index__lang=fr&.html')->toBeFile();
    });

    it('reaches every directory in the tree, not only the one it was pointed at', function () {
        invalidatorFixture($this->store, '/blog/');
        invalidatorFixture($this->store, '/about/');
        invalidatorFixture($this->store, '/blog/page/2/');

        expect($this->invalidator->flushSections())->toBe(3);
        expect(array_filter(($this->files)(), static fn(string $file): bool => str_contains(
            $file,
            'foehn_sections',
        )))->toBe([]);
    });

    it('keys a section variant beside other keyed args and still finds it', function () {
        // The canonical order is the configuration's, sorted — so `foehn_sections` is
        // not necessarily first in the filename. Matching a substring would work here
        // and searching for `foehn_sections=` anywhere would too; parsing is what makes
        // the case below work.
        $key = CacheKey::create('example.com', '/blog/', 'foehn_sections=posts&lang=fr&');
        $this->store->put($key, '<div>posts</div>');

        expect($this->store->has($key))->toBeTrue();
        expect($this->invalidator->flushSections())->toBe(1);
        expect($this->store->has($key))->toBeFalse();
    });

    it('leaves a project argument whose name merely ends in the reserved one', function () {
        // `my_foehn_sections` is a name a project may legitimately key, and a button
        // labelled "clear section cache" has no business deleting its variants. This is
        // why the filename is parsed rather than searched for `foehn_sections=`.
        $config = new PageCacheConfig(enabled: true, path: $this->root, cacheQueryArgs: ['my_foehn_sections']);
        $store = new Store($config);
        $key = CacheKey::create('example.com', '/blog/', 'my_foehn_sections=posts&');
        $store->put($key, '<html>a project variant</html>');

        expect(new Invalidator($config, $store)->flushSections())->toBe(0);
        expect($store->has($key))->toBeTrue();
    });

    it('prunes the directories it emptied and keeps the ones it did not', function () {
        $sections = CacheKey::create('example.com', '/blog/deep/', 'foehn_sections=posts&');
        $this->store->put($sections, '<div>posts</div>');
        $this->store->put(CacheKey::create('example.com', '/about/'), '<html>about</html>');
        $this->store->put(CacheKey::create('example.com', '/about/', 'foehn_sections=posts&'), '<div>posts</div>');

        $this->invalidator->flushSections();

        // Nothing else was in `/blog/deep/`, so the directory goes with the file — and
        // `/blog/` with it, since it held nothing of its own.
        expect($this->root . '/example.com/blog')->not->toBeDirectory();
        // `/about/` still holds its page.
        expect($this->root . '/example.com/about/index.html')->toBeFile();
    });

    it('never touches a file this cache did not write', function () {
        // A deletion that walks a tree has to refuse anything it does not recognise: a
        // developer's own copy of a page, something another tool left there.
        mkdir($this->root . '/example.com/blog', 0o755, true);
        $stray = $this->root . '/example.com/blog/index__foehn_sections=posts&.html.bak';
        file_put_contents($stray, 'not ours');

        expect($this->invalidator->flushSections())->toBe(0);
        expect($stray)->toBeFile();
    });

    it('clears sections left behind while caching is off', function () {
        invalidatorFixture($this->store);

        expect(pageCacheInvalidator($this->root, enabled: false)->flushSections())->toBe(1);
    });
});

describe('Invalidator: clearing one URL', function () {
    it('removes the page with every variant and sidecar it owns', function () {
        invalidatorFixture($this->store);
        invalidatorFixture($this->store, '/about/');

        // Four bodies for this URL; the two sidecars go with them and are not counted.
        expect($this->invalidator->forgetUrl('https://example.com/blog/'))->toBe(4);
        expect($this->root . '/example.com/blog')->not->toBeDirectory();
        expect($this->root . '/example.com/about/index.html')->toBeFile();
    });

    it('takes the pagination subtree when asked, and leaves it when not', function () {
        invalidatorFixture($this->store, '/blog/');
        $this->store->put(CacheKey::create('example.com', '/blog/page/2/'), '<html>blog 2</html>');

        expect($this->invalidator->forgetUrl('https://example.com/blog/'))->toBe(4);
        expect($this->root . '/example.com/blog/page/2/index.html')->toBeFile();

        expect($this->invalidator->forgetUrl('https://example.com/blog/', paginated: true))->toBe(1);
        expect($this->root . '/example.com/blog')->not->toBeDirectory();
    });

    it('answers zero for a URL nothing was ever cached for', function () {
        expect($this->invalidator->forgetUrl('https://example.com/never-visited/'))->toBe(0);
    });

    it('collapses the two spellings of an accented permalink onto one file', function () {
        // WordPress builds a non-ASCII slug with lowercase percent escapes and a browser
        // sends uppercase ones, so `get_permalink()` hands this a different spelling of
        // the URL than the recorder was asked for. One decode, in one place, is what
        // makes a purge find the file — and doing it twice is how category and author
        // archives came to serve stale pages after every edit in wp-super-cache.
        $this->store->put(CacheKey::create('example.com', '/à-propos/'), '<html>about</html>');

        expect($this->invalidator->forgetUrl('https://example.com/%c3%a0-propos/'))->toBe(1);
        expect($this->store->has(CacheKey::create('example.com', '/à-propos/')))->toBeFalse();
    });

    it('refuses a URL with no host', function () {
        expect($this->invalidator->forgetUrl('/blog/'))->toBeNull();
    });

    it('refuses a URL whose path this cache would not write', function () {
        // Null, not zero. A caller has to be able to tell "that is not a cache key" from
        // "nothing was cached", because the first is an argument mistake to report and
        // the second is a clear that had nothing to do.
        foreach ([
            'https://example.com/../../etc/passwd',
            'https://example.com/x/:8080/evil',
            'https://example.com/a b/',
            'https://exa mple.com/blog/',
        ] as $url) {
            expect($this->invalidator->forgetUrl($url))->toBeNull($url . ' should have no cache key');
        }
    });

    it('cannot delete anything outside the cache root', function () {
        $outside = dirname($this->root) . '/outside.html';
        file_put_contents($outside, 'not ours');

        try {
            foreach ([
                'https://example.com/../outside.html',
                'https://example.com/..%2foutside.html',
                'https://example.com/blog/../../../outside.html',
            ] as $url) {
                expect($this->invalidator->forgetUrl($url))->toBeNull($url . ' should have no cache key');
            }

            expect($outside)->toBeFile();
        } finally {
            unlink($outside);
        }
    });

    it('clears one URL left behind while caching is off', function () {
        invalidatorFixture($this->store);

        expect(pageCacheInvalidator($this->root, enabled: false)->forgetUrl('https://example.com/blog/'))->toBe(4);
    });
});

describe('Invalidator: what it reports about itself', function () {
    it('names the cache root every operation stays inside', function () {
        expect($this->invalidator->root())->toBe($this->root);
    });
});
