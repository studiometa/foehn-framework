<?php

declare(strict_types=1);

use League\Flysystem\Config;
use League\Flysystem\DecoratedAdapter;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnableToDeleteFile;
use Studiometa\Foehn\Config\PageCacheConfig;
use Studiometa\Foehn\Config\PageCacheStorage;
use Studiometa\Foehn\PageCache\BucketStore;
use Studiometa\Foehn\PageCache\CacheKey;

/**
 * A local adapter that keeps the config of each write, as the S3 adapter would receive it.
 */
final class RecordingAdapter extends DecoratedAdapter
{
    /** @var array<string, Config> */
    public array $writes = [];

    public bool $failDeletes = false;

    public function write(string $path, string $contents, Config $config): void
    {
        $this->writes[$path] = $config;

        parent::write($path, $contents, $config);
    }

    public function delete(string $path): void
    {
        if ($this->failDeletes) {
            throw UnableToDeleteFile::atLocation($path, 'refused');
        }

        parent::delete($path);
    }
}

/**
 * A bucket store on a local directory: the same Flysystem calls as on S3.
 */
function bucketStore(string $root, int $ttl = 0, ?RecordingAdapter $adapter = null): BucketStore
{
    $config = new PageCacheConfig(enabled: true, storage: PageCacheStorage::Bucket, ttl: $ttl);
    $adapter ??= new RecordingAdapter(new LocalFilesystemAdapter($root));

    return new BucketStore($config, static fn() => new Filesystem($adapter), 'memory://cache/pages');
}

describe('BucketStore', function () {
    beforeEach(function () {
        $this->root = pageCacheRoot();
        $this->adapter = new RecordingAdapter(new LocalFilesystemAdapter($this->root));
        $this->store = bucketStore($this->root, adapter: $this->adapter);
        $this->key = CacheKey::create('example.com', '/blog/');
    });

    afterEach(function () {
        removeTestDirectory($this->root);
    });

    it('stores a page at the key nginx assembles', function () {
        expect($this->store->put($this->key, '<html>hello</html>'))->toBeTrue();

        expect(file_get_contents($this->root . '/example.com/blog/index.html'))->toBe('<html>hello</html>');
    });

    it('writes a public-read HTML object, because nginx reads the bucket anonymously', function () {
        $this->store->put($this->key, '<html>hello</html>');

        $config = $this->adapter->writes['example.com/blog/index.html'];

        expect($config->get('visibility'))->toBe('public');
        expect($config->get('ContentType'))->toBe('text/html; charset=UTF-8');
    });

    it('stores a keyed variant under its own name', function () {
        $key = CacheKey::create('example.com', '/blog/', 'foehn_sections=listing&');

        expect($this->store->put($key, '<p>fragment</p>'))->toBeTrue();
        expect($this->root . '/example.com/blog/index__foehn_sections=listing&.html')->toBeFile();
    });

    it('writes no headers sidecar and no 404, because nothing reads them from a bucket', function () {
        expect($this->store->put($this->key, '<html>page</html>', 200, ['Link: <https://example.com/>; rel=shortlink']))
            ->toBeTrue();
        expect($this->store->put($this->key, '<html>missing</html>', 404))->toBeFalse();

        expect(glob($this->root . '/example.com/blog/*'))->toBe([$this->root . '/example.com/blog/index.html']);
    });

    it('forgets the index objects of one URL and leaves the URLs below it', function () {
        $this->store->put($this->key, '<html>blog</html>');
        $this->store->put(CacheKey::create('example.com', '/blog/', 'foehn_sections=listing&'), '<p>listing</p>');
        $this->store->put(CacheKey::create('example.com', '/blog/post/'), '<html>post</html>');

        expect($this->store->forget($this->key))->toBe(2);
        expect($this->root . '/example.com/blog/post/index.html')->toBeFile();
        expect($this->store->stats()['files'])->toBe(1);
    });

    it('forgets the page/** subtree with an archive', function () {
        $this->store->put($this->key, '<html>blog</html>');
        $this->store->put(CacheKey::create('example.com', '/blog/page/2/'), '<html>2</html>');
        $this->store->put(CacheKey::create('example.com', '/blog/page/3/'), '<html>3</html>');
        $this->store->put(CacheKey::create('example.com', '/blog/post/'), '<html>post</html>');

        expect($this->store->forgetPaginated($this->key))->toBe(3);
        expect($this->store->stats()['files'])->toBe(1);
    });

    it('flushes every page', function () {
        $this->store->put($this->key, '<html>blog</html>');
        $this->store->put(CacheKey::create('example.com', '/'), '<html>home</html>');
        $this->store->put(CacheKey::create('other.example', '/'), '<html>other</html>');

        expect($this->store->flush())->toBe(3);
        expect($this->store->stats()['files'])->toBe(0);
    });

    it('flushes the sections and leaves the pages beside them', function () {
        $this->store->put($this->key, '<html>blog</html>');
        $this->store->put(CacheKey::create('example.com', '/blog/', 'page=2&'), '<html>2</html>');
        $this->store->put(CacheKey::create('example.com', '/blog/', 'foehn_sections=listing&'), '<p>listing</p>');

        expect($this->store->sectionStats()['files'])->toBe(1);
        expect($this->store->flushSections())->toBe(1);
        expect($this->store->stats()['files'])->toBe(2);
        expect($this->store->sectionStats()['files'])->toBe(0);
    });

    it('sweeps the objects older than the TTL', function () {
        $store = bucketStore($this->root, 3600, $this->adapter);
        $store->put(CacheKey::create('example.com', '/stale/'), '<html>stale</html>');
        $store->put(CacheKey::create('example.com', '/fresh/'), '<html>fresh</html>');
        touch($this->root . '/example.com/stale/index.html', time() - 7200);

        expect($store->sweep())->toBe(1);
        expect($this->root . '/example.com/fresh/index.html')->toBeFile();
    });

    it('sweeps nothing when pages stay until something purges them', function () {
        $this->store->put($this->key, '<html>blog</html>');
        touch($this->root . '/example.com/blog/index.html', time() - 86400);

        expect($this->store->sweep())->toBe(0);
    });

    it('reports what it holds from one listing', function () {
        $this->store->put($this->key, '<html>blog</html>');
        $this->store->put(CacheKey::create('example.com', '/'), '<html>h</html>');
        touch($this->root . '/example.com/index.html', 1_000_000);

        $stats = $this->store->stats();

        expect($stats['files'])->toBe(2);
        expect($stats['bytes'])->toBe(strlen('<html>blog</html>') + strlen('<html>h</html>'));
        expect($stats['oldest'])->toBe(1_000_000);
        expect($stats['newest'])->toBeGreaterThan(1_000_000);
    });

    it('counts nothing for a delete the bucket refused', function () {
        $this->store->put($this->key, '<html>blog</html>');
        $this->adapter->failDeletes = true;

        expect($this->store->flush())->toBe(0);
        expect($this->store->stats()['files'])->toBe(1);
    });

    it('says where the pages are', function () {
        expect($this->store->root())->toBe('memory://cache/pages');
    });
});

describe('BucketStore::forUploads', function () {
    it('never throws when the bucket is not configured, so a site still boots', function () {
        // The suite defines no S3_UPLOADS_BUCKET, which is the state of a site that
        // turned bucket storage on and forgot the bucket.
        $store = BucketStore::forUploads(new PageCacheConfig(enabled: true, storage: PageCacheStorage::Bucket));
        $key = CacheKey::create('example.com', '/');

        expect($store->root())->toContain('S3_UPLOADS_BUCKET is not defined');
        expect($store->put($key, '<html>home</html>'))->toBeFalse();
        expect($store->flush())->toBe(0);
        expect($store->stats())->toBe(['files' => 0, 'bytes' => 0, 'oldest' => null, 'newest' => null]);
    });
});
