<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\PageCacheConfig;
use Studiometa\Foehn\Console\Commands\PageCacheClearCommand;
use Studiometa\Foehn\Console\Commands\PageCacheConfigCommand;
use Studiometa\Foehn\Console\Commands\PageCacheStatusCommand;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\PageCache\CacheKey;
use Studiometa\Foehn\PageCache\Invalidator;
use Studiometa\Foehn\PageCache\ServerConfig\ApacheSnippet;
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

    it('reports an empty cache without inventing an age for it', function () {
        (new PageCacheStatusCommand(new WpCli(), $this->config, $this->store))([], []);

        expect(($this->logged)())->toContain('Oldest entry: —');
    });
});
