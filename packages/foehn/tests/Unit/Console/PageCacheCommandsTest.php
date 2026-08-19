<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\PageCacheConfig;
use Studiometa\Foehn\Console\Commands\PageCacheClearCommand;
use Studiometa\Foehn\Console\Commands\PageCacheStatusCommand;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\PageCache\CacheKey;
use Studiometa\Foehn\PageCache\Store;

beforeEach(function () {
    wp_stub_reset();
    $GLOBALS['wp_stub_environment_type'] = 'production';

    $this->root = pageCacheRoot();
    $this->config = new PageCacheConfig(enabled: true, path: $this->root);
    $this->store = new Store($this->config);

    $this->logged = static fn(): string => implode("\n", array_column(
        array_column(wp_stub_get_calls('wp_cli_log'), 'args'),
        'message',
    ));
});

afterEach(function () {
    removeTestDirectory($this->root);
});

describe('cache:clear', function () {
    it('empties the cache and says how much it removed', function () {
        $this->store->put(CacheKey::create('example.com', '/'), '<html>home</html>');
        $this->store->put(CacheKey::create('example.com', '/blog/'), '<html>blog</html>');

        (new PageCacheClearCommand(new WpCli(), $this->config, $this->store))([], []);

        expect($this->store->stats()['files'])->toBe(0);
        expect(wp_stub_get_calls('wp_cli_success')[0]['args']['message'])->toContain('2 files');
    });

    it('clears one URL and its pagination, leaving the rest', function () {
        $this->store->put(CacheKey::create('example.com', '/blog/'), '<html>blog</html>');
        $this->store->put(CacheKey::create('example.com', '/blog/page/2/'), '<html>blog 2</html>');
        $this->store->put(CacheKey::create('example.com', '/about/'), '<html>about</html>');

        (new PageCacheClearCommand(new WpCli(), $this->config, $this->store))([], [
            'url' => 'https://example.com/blog/',
        ]);

        expect($this->store->has(CacheKey::create('example.com', '/blog/')))->toBeFalse();
        expect($this->store->has(CacheKey::create('example.com', '/blog/page/2/')))->toBeFalse();
        expect($this->store->has(CacheKey::create('example.com', '/about/')))->toBeTrue();
    });

    it('refuses a URL it cannot turn into a cache key', function () {
        (new PageCacheClearCommand(new WpCli(), $this->config, $this->store))([], ['url' => '/blog/']);

        expect(wp_stub_get_calls('wp_cli_error'))->toHaveCount(1);
    });

    it('still clears what an earlier release left behind, and says so', function () {
        // A project that switches the cache off wants the stored files gone, not a
        // refusal to touch them.
        $disabled = new PageCacheConfig(enabled: false, path: $this->root);
        $store = new Store($disabled);
        $store->put(CacheKey::create('example.com', '/'), '<html>home</html>');

        (new PageCacheClearCommand(new WpCli(), $disabled, $store))([], []);

        expect($store->stats()['files'])->toBe(0);
        expect(wp_stub_get_calls('wp_cli_warning'))->toHaveCount(1);
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
