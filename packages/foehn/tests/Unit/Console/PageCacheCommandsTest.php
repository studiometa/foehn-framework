<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\PageCacheConfig;
use Studiometa\Foehn\Config\PageCacheStorage;
use Studiometa\Foehn\Console\Commands\PageCacheClearCommand;
use Studiometa\Foehn\Console\Commands\PageCacheConfigCommand;
use Studiometa\Foehn\Console\Commands\PageCacheStatusCommand;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\PageCache\BucketStore;
use Studiometa\Foehn\PageCache\CacheKey;
use Studiometa\Foehn\PageCache\Invalidator;
use Studiometa\Foehn\PageCache\ServerConfig\ApacheSnippet;
use Studiometa\Foehn\PageCache\ServerConfig\NginxSnippet;
use Studiometa\Foehn\PageCache\ServerConfig\SnippetPolicy;
use Studiometa\Foehn\PageCache\Store;

beforeEach(function () {
    wp_stub_reset();
    $GLOBALS['wp_stub_environment_type'] = 'production';

    $this->root = pageCacheRoot();
    $this->config = new PageCacheConfig(enabled: true, path: $this->root);
    $this->store = new Store($this->config);
    $this->invalidator = new Invalidator($this->config, $this->store);

    $this->logged = static fn(): string => implode("\n", array_column(
        array_column(wp_stub_get_calls('wp_cli_log'), 'args'),
        'message',
    ));
});

afterEach(function () {
    removeTestDirectory($this->root);
});

describe('cache:clear', function () {
    it('empties the cache and says how many pages it removed', function () {
        $this->store->put(CacheKey::create('example.com', '/'), '<html>home</html>');
        $this->store->put(CacheKey::create('example.com', '/blog/'), '<html>blog</html>');

        (new PageCacheClearCommand(new WpCli(), $this->config, $this->invalidator))([], []);

        expect($this->store->stats()['files'])->toBe(0);
        // Pages, not files. An entry is a body and usually a headers sidecar, so the
        // file count is about double and is a number nobody can act on.
        expect(wp_stub_get_calls('wp_cli_success')[0]['args']['message'])->toContain('2 pages');
    });

    it('clears one URL and its pagination, leaving the rest', function () {
        $this->store->put(CacheKey::create('example.com', '/blog/'), '<html>blog</html>');
        $this->store->put(CacheKey::create('example.com', '/blog/page/2/'), '<html>blog 2</html>');
        $this->store->put(CacheKey::create('example.com', '/about/'), '<html>about</html>');

        (new PageCacheClearCommand(new WpCli(), $this->config, $this->invalidator))([], [
            'url' => 'https://example.com/blog/',
        ]);

        expect($this->store->has(CacheKey::create('example.com', '/blog/')))->toBeFalse();
        expect($this->store->has(CacheKey::create('example.com', '/blog/page/2/')))->toBeFalse();
        expect($this->store->has(CacheKey::create('example.com', '/about/')))->toBeTrue();
    });

    it('refuses a URL it cannot turn into a cache key', function () {
        (new PageCacheClearCommand(new WpCli(), $this->config, $this->invalidator))([], ['url' => '/blog/']);

        expect(wp_stub_get_calls('wp_cli_error'))->toHaveCount(1);
        expect(wp_stub_get_calls('wp_cli_success'))->toBe([]);
    });

    it('reports a URL nothing was cached for as a clear, not as an error', function () {
        // The distinction the null return exists for: a URL this cache would refuse to
        // key is an argument mistake, and a valid URL with no stored page is a clear
        // that had nothing to do.
        (new PageCacheClearCommand(new WpCli(), $this->config, $this->invalidator))([], [
            'url' => 'https://example.com/never-visited/',
        ]);

        expect(wp_stub_get_calls('wp_cli_error'))->toBe([]);
        expect(wp_stub_get_calls('wp_cli_success')[0]['args']['message'])->toContain('0 pages');
    });

    it('still clears what an earlier release left behind, and says so', function () {
        // A project that switches the cache off wants the stored files gone, not a
        // refusal to touch them.
        $disabled = new PageCacheConfig(enabled: false, path: $this->root);
        $store = new Store($disabled);
        $store->put(CacheKey::create('example.com', '/'), '<html>home</html>');

        (new PageCacheClearCommand(new WpCli(), $disabled, new Invalidator($disabled, $store)))([], []);

        expect($store->stats()['files'])->toBe(0);
        expect(wp_stub_get_calls('wp_cli_warning'))->toHaveCount(1);
    });
});

describe('cache:config', function () {
    beforeEach(function () {
        // A cache under the document root, because that is the only kind a web server can
        // reach by filename — the other cases are covered below.
        $this->served = new PageCacheConfig(enabled: true, path: constant('WP_CONTENT_DIR') . '/cache/foehn/pages');
    });

    it('prints the nginx snippet without writing anything', function () {
        (new PageCacheConfigCommand(new WpCli(), $this->served))([], ['server' => 'nginx']);

        $printed = implode("\n", array_column(array_column(wp_stub_get_calls('wp_cli_line'), 'args'), 'message'));

        expect($printed)->toContain('rewrite ^ "$foehn_url" last;');
        expect(wp_stub_get_calls('wp_cli_success'))->toBe([]);
    });

    it('prints a snippet that reads a keyed arg in both its spellings', function () {
        // The snippet a deploy installs is this command's output, so the bracketed capture
        // has to survive the trip through WP-CLI and not only exist in the generator.
        $keyed = new PageCacheConfig(enabled: true, path: $this->served->path, cacheQueryArgs: ['genre']);

        (new PageCacheConfigCommand(new WpCli(), $keyed))([], ['server' => 'nginx']);

        $printed = implode("\n", array_column(array_column(wp_stub_get_calls('wp_cli_line'), 'args'), 'message'));

        expect($printed)
            ->toContain('set $foehn_val_genre $arg_genre;')
            ->toContain('if ($args ~ "(?:^|&)genre(?:\\[\\]|%5[Bb]%5[Dd])=([^&]*)") { set $foehn_val_genre "$1"; }')
            ->toContain('genre(?:\\[\\]|%5[Bb]%5[Dd])?');
    });

    it('prints the Apache block', function () {
        (new PageCacheConfigCommand(new WpCli(), $this->served))([], ['server' => 'apache']);

        $printed = implode("\n", array_column(array_column(wp_stub_get_calls('wp_cli_line'), 'args'), 'message'));

        expect($printed)->toContain(ApacheSnippet::BEGIN);
    });

    it('refuses a server it cannot generate for', function (array $assocArgs) {
        (new PageCacheConfigCommand(new WpCli(), $this->config))([], $assocArgs);

        expect(wp_stub_get_calls('wp_cli_error'))->toHaveCount(1);
    })->with([
        'nothing at all' => [[]],
        'a server nobody supports' => [['server' => 'caddy']],
    ]);

    it('refuses a header of a hit it cannot write, for either server', function (string $server) {
        // Apache writes none of them, but the drop-in sends them on every setup, and one
        // it refuses would stop it from serving without a word.
        $unsafe = new PageCacheConfig(enabled: true, path: $this->served->path, responseHeaders: [
            'X-Frame-Options' => 'SAMEORIGIN"; return 200 "owned',
        ]);

        (new PageCacheConfigCommand(new WpCli(), $unsafe))([], ['server' => $server]);

        expect(wp_stub_get_calls('wp_cli_error')[0]['args']['message'])->toContain('responseHeaders: X-Frame-Options');
        expect(wp_stub_get_calls('wp_cli_line'))->toBe([]);
    })->with(['nginx', 'apache']);

    it('refuses Apache in bucket storage, which only nginx serves', function () {
        $bucket = new PageCacheConfig(enabled: true, storage: PageCacheStorage::Bucket);

        (new PageCacheConfigCommand(new WpCli(), $bucket))([], ['server' => 'apache']);

        expect(wp_stub_get_calls('wp_cli_error')[0]['args']['message'])->toContain('nginx only');
        expect(wp_stub_get_calls('wp_cli_line'))->toBe([]);
    });

    it('prints the bucket variant of the nginx snippet', function () {
        pageCacheBucketEnv(['S3_UPLOADS_BUCKET' => 'uploads', 'S3_UPLOADS_ENDPOINT' => 'https://s3.example.com']);
        $bucket = new PageCacheConfig(enabled: true, storage: PageCacheStorage::Bucket);

        (new PageCacheConfigCommand(new WpCli(), $bucket))([], ['server' => 'nginx']);
        pageCacheBucketEnv();

        $printed = implode("\n", array_column(array_column(wp_stub_get_calls('wp_cli_line'), 'args'), 'message'));

        expect($printed)->toContain('proxy_pass $foehn_page_upstream;')->toContain('uploads.s3.example.com');
    });

    it('says which variables are missing rather than printing a bucket snippet without a bucket', function () {
        pageCacheBucketEnv();
        $bucket = new PageCacheConfig(enabled: true, storage: PageCacheStorage::Bucket);

        (new PageCacheConfigCommand(new WpCli(), $bucket))([], ['server' => 'nginx']);

        expect(wp_stub_get_calls('wp_cli_error')[0]['args']['message'])->toContain('S3_UPLOADS_BUCKET');
        expect(wp_stub_get_calls('wp_cli_line'))->toBe([]);
    });

    it('says so rather than generating a snippet no server could use', function () {
        // A cache outside the document root has no filename a web server can reach. The
        // drop-in still serves it, which is worth saying out loud.
        $outside = new PageCacheConfig(enabled: true, path: '/srv/elsewhere/pages');

        (new PageCacheConfigCommand(new WpCli(), $outside))([], ['server' => 'nginx']);

        expect(wp_stub_get_calls('wp_cli_error')[0]['args']['message'])->toContain('drop-in still serves it');
    });
});

describe('cache:status', function () {
    it('reports the path, the environment and what is stored', function () {
        $this->store->put(CacheKey::create('example.com', '/'), str_repeat('x', 2048));

        (new PageCacheStatusCommand(new WpCli(), $this->config, $this->store))([], []);

        expect(($this->logged)())
            ->toContain('Enabled: Yes')
            ->toContain('Environment: production')
            ->toContain($this->root)
            ->toContain('Files: 1 (2.0 KB)');
        expect(wp_stub_get_calls('wp_cli_success'))->toHaveCount(1);
    });

    it('says the cache is off rather than claiming it is active', function () {
        $disabled = new PageCacheConfig(enabled: false, path: $this->root);

        (new PageCacheStatusCommand(new WpCli(), $disabled, new Store($disabled)))([], []);

        expect(($this->logged)())->toContain('Enabled: No');
        expect(wp_stub_get_calls('wp_cli_success'))->toBe([]);
    });

    it('warns when the cache is on but inert in this environment', function () {
        // The state a project lands in locally after enabling it for production, and
        // the one that otherwise reads as "it is broken".
        $GLOBALS['wp_stub_environment_type'] = 'local';

        (new PageCacheStatusCommand(new WpCli(), $this->config, $this->store))([], []);

        expect(wp_stub_get_calls('wp_cli_warning'))->toHaveCount(1);
    });

    it('warns rather than claiming it is active when the drop-in refuses a header of a hit', function () {
        $unsafe = new PageCacheConfig(enabled: true, path: $this->root, cacheControl: 'no-cache"');

        (new PageCacheStatusCommand(new WpCli(), $unsafe, new Store($unsafe)))([], []);

        expect(wp_stub_get_calls('wp_cli_warning')[0]['args']['message'])->toContain('cacheControl');
        expect(wp_stub_get_calls('wp_cli_success'))->toBe([]);
    });

    it('says what bucket storage does not do, and warns when the bucket is not configured', function () {
        // The suite defines no S3_UPLOADS_BUCKET: the store writes nothing, and saying the
        // cache is active would send somebody looking in the wrong place.
        $bucket = new PageCacheConfig(enabled: true, storage: PageCacheStorage::Bucket);

        (new PageCacheStatusCommand(new WpCli(), $bucket, BucketStore::forUploads($bucket)))([], []);

        expect(($this->logged)())
            ->toContain('Storage: bucket')
            ->toContain('drop-in (does not read in bucket storage)')
            ->toContain('apache (not supported in bucket storage)');
        expect(wp_stub_get_calls('wp_cli_warning')[0]['args']['message'])->toContain('S3_UPLOADS_BUCKET');
        expect(wp_stub_get_calls('wp_cli_success'))->toBe([]);
    });

    it('reports an empty cache without inventing an age for it', function () {
        (new PageCacheStatusCommand(new WpCli(), $this->config, $this->store))([], []);

        expect(($this->logged)())->toContain('Oldest entry: —');
    });

    describe('an installed nginx include', function () {
        beforeEach(function () {
            // Where `cache:config --write` puts it: under the project, which is the
            // directory above the document root the snippets derive from ABSPATH.
            $this->include = dirname(SnippetPolicy::documentRoot() ?? '') . '/' . PageCacheConfigCommand::NGINX_PATH;

            if (!is_dir(dirname($this->include))) {
                mkdir(dirname($this->include), 0o777, true);
            }
        });

        afterEach(function () {
            @unlink($this->include);
        });

        it('is current when it carries the policy hash this release computes', function () {
            file_put_contents(
                $this->include,
                "# Foehn page cache\n# policy: " . new NginxSnippet($this->config)->hash() . "\n",
            );

            (new PageCacheStatusCommand(new WpCli(), $this->config, $this->store))([], []);

            expect(($this->logged)())
                ->toContain('✓ nginx (' . $this->include . ')')
                ->not->toContain('re-run cache:config');
        });

        it('is out of date when an older generator wrote it, even from the same config', function () {
            // The hash an include generated before #193 carries: the configuration alone.
            // The rules it states are not the ones this release generates, so it must be
            // reported — a HIT under old rules is the failure mode the hash exists for.
            $policy = new SnippetPolicy($this->config);
            $older = substr(
                sha1((string) json_encode([
                    $policy->cacheUrlPath(),
                    $this->config->bypassCookies,
                    $this->config->getIgnoredQueryArgs(),
                    $this->config->getCacheQueryArgs(),
                    $this->config->cacheNotFound,
                    $this->config->browserMaxAge,
                ])),
                0,
                12,
            );
            file_put_contents($this->include, "# Foehn page cache\n# policy: {$older}\n");

            (new PageCacheStatusCommand(new WpCli(), $this->config, $this->store))([], []);

            expect(($this->logged)())
                ->toContain(
                    '! nginx ('
                    . $this->include
                    . ') — generated from a different config or an older release, re-run cache:config',
                );
        });
    });
});
