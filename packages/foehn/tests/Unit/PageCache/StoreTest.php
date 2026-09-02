<?php

declare(strict_types=1);

use Studiometa\Foehn\PageCache\CacheKey;

describe('Store', function () {
    beforeEach(function () {
        $this->root = pageCacheRoot();
        $this->store = pageCacheStore($this->root);
        $this->key = CacheKey::create('example.com', '/blog/');
    });

    afterEach(function () {
        removeTestDirectory($this->root);
    });

    it('stores a body at the path every reader computes', function () {
        expect($this->store->put($this->key, '<html>hello</html>'))->toBeTrue();

        expect($this->root . '/example.com/blog/index.html')->toBeFile();
        expect(file_get_contents($this->root . '/example.com/blog/index.html'))->toBe('<html>hello</html>');
    });

    it('leaves no temporary file behind, because nginx would serve one', function () {
        $this->store->put($this->key, '<html>hello</html>');

        expect(glob($this->root . '/example.com/blog/*'))->toBe([$this->root . '/example.com/blog/index.html']);
    });

    it('writes a file the web server can read and a visitor cannot write', function () {
        $this->store->put($this->key, '<html>hello</html>');

        expect(substr(sprintf('%o', fileperms($this->root . '/example.com/blog/index.html')), -3))->toBe('644');
        expect(substr(sprintf('%o', fileperms($this->root . '/example.com/blog')), -3))->toBe('755');
    });

    it('replaces a stored page rather than appending to it', function () {
        $this->store->put($this->key, '<html>first</html>');
        $this->store->put($this->key, '<html>second</html>');

        expect(file_get_contents($this->root . '/example.com/blog/index.html'))->toBe('<html>second</html>');
    });

    it('reads back what it stored', function () {
        $this->store->put($this->key, '<html>hello</html>');

        expect($this->store->get($this->key))->toBe('<html>hello</html>');
        expect($this->store->has($this->key))->toBeTrue();
    });

    it('has nothing to read for a page nobody stored', function () {
        expect($this->store->get($this->key))->toBeNull();
        expect($this->store->has($this->key))->toBeFalse();
    });

    it('refuses to write outside its own root, even past a symlink', function () {
        // CacheKey already refuses a traversal. This is the second lock on the same
        // door: a symlink dropped into the tree is a path that looks contained and
        // is not, and only realpath() can tell.
        $outside = pageCacheRoot();
        mkdir($outside . '/example.com', 0o777, true);
        mkdir($this->root, 0o777, true);
        symlink($outside . '/example.com', $this->root . '/example.com');

        try {
            expect($this->store->put($this->key, '<html>hello</html>'))->toBeFalse();
            expect($outside . '/example.com/blog/index.html')->not->toBeFile();
        } finally {
            unlink($this->root . '/example.com');
            removeTestDirectory($outside);
        }
    });

    it('forgets one page and prunes the directories it emptied', function () {
        $this->store->put($this->key, '<html>hello</html>');

        expect($this->store->forget($this->key))->toBe(1);
        expect($this->root . '/example.com/blog')->not->toBeDirectory();
    });

    it('leaves a sibling page alone when it forgets one', function () {
        $other = CacheKey::create('example.com', '/about/');
        $this->store->put($this->key, '<html>blog</html>');
        $this->store->put($other, '<html>about</html>');

        $this->store->forget($this->key);

        expect($this->store->has($other))->toBeTrue();
    });

    it('takes an archive pagination with the archive', function () {
        // /blog/page/2/ holds the same posts /blog/ does, one screen further down.
        $this->store->put($this->key, '<html>page 1</html>');
        $this->store->put(CacheKey::create('example.com', '/blog/page/2/'), '<html>page 2</html>');
        $this->store->put(CacheKey::create('example.com', '/blog/page/3/'), '<html>page 3</html>');

        expect($this->store->forgetPaginated($this->key))->toBe(3);
        expect($this->root . '/example.com/blog')->not->toBeDirectory();
    });

    it('empties itself', function () {
        $this->store->put($this->key, '<html>blog</html>');
        $this->store->put(CacheKey::create('example.com', '/about/'), '<html>about</html>');
        $this->store->put(CacheKey::create('other.test', '/'), '<html>other</html>');

        expect($this->store->flush())->toBe(3);
        expect(glob($this->root . '/*'))->toBe([]);
    });

    it('has nothing to empty when it was never written to', function () {
        expect($this->store->flush())->toBe(0);
    });

    it('never expires a page when the TTL says to keep it until a purge', function () {
        $this->store->put($this->key, '<html>hello</html>');
        touch($this->root . '/example.com/blog/index.html', time() - 86400);

        expect($this->store->get($this->key))->not->toBeNull();
        expect($this->store->sweep())->toBe(0);
    });

    it('sweeps what has outlived the TTL and keeps what has not', function () {
        // nginx's try_files cannot check a file's age and neither can mod_rewrite, so
        // with a TTL set the sweep interval is the real bound on staleness.
        $store = pageCacheStore($this->root, ttl: 3600);
        $stale = CacheKey::create('example.com', '/stale/');
        $fresh = CacheKey::create('example.com', '/fresh/');

        $store->put($stale, '<html>stale</html>');
        $store->put($fresh, '<html>fresh</html>');
        touch($this->root . '/example.com/stale/index.html', time() - 7200);

        expect($store->sweep())->toBe(1);
        expect($store->has($stale))->toBeFalse();
        expect($store->has($fresh))->toBeTrue();
        expect($this->root . '/example.com/stale')->not->toBeDirectory();
    });

    it('will not read a file the TTL has expired, even before the sweep runs', function () {
        $store = pageCacheStore($this->root, ttl: 3600);
        $store->put($this->key, '<html>hello</html>');
        touch($this->root . '/example.com/blog/index.html', time() - 7200);

        expect($store->get($this->key))->toBeNull();
    });

    it('reports what is in it', function () {
        $this->store->put($this->key, str_repeat('x', 100));
        touch($this->root . '/example.com/blog/index.html', 1_700_000_000);
        $this->store->put(CacheKey::create('example.com', '/about/'), str_repeat('x', 50));
        touch($this->root . '/example.com/about/index.html', 1_800_000_000);

        expect($this->store->stats())->toBe([
            'files' => 2,
            'bytes' => 150,
            'oldest' => 1_700_000_000,
            'newest' => 1_800_000_000,
        ]);
    });

    it('reports an empty cache without inventing entries', function () {
        expect($this->store->stats())->toBe(['files' => 0, 'bytes' => 0, 'oldest' => null, 'newest' => null]);
    });

    describe('status and headers', function () {
        it('stores a 404 under a name of its own', function () {
            // The body alone cannot say what status it was sent with, so the name does.
            expect($this->store->put($this->key, '<html>gone</html>', 404))->toBeTrue();

            expect($this->root . '/example.com/blog/index--404.html')->toBeFile();
            expect($this->root . '/example.com/blog/index.html')->not->toBeFile();
        });

        it('keeps a 200 and a 404 for one URL apart', function () {
            $this->store->put($this->key, '<html>page</html>');
            $this->store->put($this->key, '<html>gone</html>', 404);

            expect($this->store->get($this->key))->toBe('<html>page</html>');
            expect($this->store->get($this->key, 404))->toBe('<html>gone</html>');
        });

        it('cannot confuse a 404 name with a keyed variant', function () {
            // A variant always ends with `&`, so it can never produce the 404 suffix.
            $keyed = CacheKey::create('example.com', '/blog/', 'x=--404&');

            expect($keyed?->filename())->toBe('index__x=--404&.html');
            expect($keyed?->filename(404))->toBe('index__x=--404&--404.html');
        });

        it('stores the headers a response carried, beside the body', function () {
            $this->store->put($this->key, '<html>hi</html>', 200, [
                'Link: </a.css>; rel=preload',
                'Set-Cookie: session=secret',
            ]);

            expect($this->store->headers($this->key))->toBe(['Link: </a.css>; rel=preload']);
            expect($this->root . '/example.com/blog/index.html.headers')->toBeFile();
        });

        it('writes no headers file when a response had nothing worth keeping', function () {
            $this->store->put($this->key, '<html>hi</html>', 200, ['Set-Cookie: a=b']);

            expect($this->root . '/example.com/blog/index.html.headers')->not->toBeFile();
            expect($this->store->headers($this->key))->toBe([]);
        });

        it('drops a stale headers file when the page stops sending any', function () {
            $this->store->put($this->key, '<html>hi</html>', 200, ['X-Robots-Tag: noindex']);
            $this->store->put($this->key, '<html>hi</html>', 200, []);

            expect($this->root . '/example.com/blog/index.html.headers')->not->toBeFile();
        });

        it('reports no headers when a page never stored any', function () {
            $this->store->put($this->key, '<html>hi</html>');

            expect($this->store->headers($this->key))->toBe([]);
        });

        it('points at a headers file beside the body it belongs to', function () {
            expect($this->store->headersFile($this->key))
                ->toBe($this->root . '/example.com/blog/index.html.headers');
            expect($this->store->headersFile($this->key, 404))
                ->toBe($this->root . '/example.com/blog/index--404.html.headers');
        });

        it('counts pages rather than files in its stats', function () {
            // `cache:status` reports this number. With a headers sibling per entry,
            // counting files would tell somebody they have twice the pages they have.
            $this->store->put($this->key, '<html>hi</html>', 200, ['X-Robots-Tag: noindex']);

            $stats = $this->store->stats();

            expect($stats['files'])->toBe(1);
            expect($stats['bytes'])->toBeGreaterThan(strlen('<html>hi</html>'));
        });

        it('purges the headers and the 404 with the page', function () {
            $this->store->put($this->key, '<html>hi</html>', 200, ['X-Robots-Tag: noindex']);
            $this->store->put($this->key, '<html>gone</html>', 404, ['X-Robots-Tag: noindex']);

            $this->store->forget($this->key);

            // A sidecar that outlived its body would be replayed onto the next page
            // stored at the same URL.
            expect(glob($this->root . '/example.com/blog/*'))->toBe([]);
        });
    });
});
