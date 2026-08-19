<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\PageCacheConfig;
use Studiometa\Foehn\PageCache\Bypass;
use Studiometa\Foehn\PageCache\CacheKey;
use Studiometa\Foehn\PageCache\Recorder;
use Studiometa\Foehn\PageCache\Store;

describe('Recorder', function () {
    beforeEach(function () {
        wp_stub_reset();
        $GLOBALS['wp_stub_environment_type'] = 'production';

        $this->root = pageCacheRoot();
        $this->config = new PageCacheConfig(enabled: true, path: $this->root, debugHeaders: false);
        $this->store = new Store($this->config);
        $this->recorder = new Recorder($this->config, $this->store, new Bypass($this->config));

        $_SERVER = pageCacheServer();
        $_COOKIE = [];
        $_POST = [];
    });

    afterEach(function () {
        removeTestDirectory($this->root);
    });

    it('stores an eligible page and hands it back unchanged but for the marker', function () {
        $body = pageCacheBody();
        $returned = $this->recorder->onFlush($body);

        expect($returned)->toStartWith($body);
        expect($this->store->has(CacheKey::create('example.com', '/blog/')))->toBeTrue();
    });

    it('stores exactly the bytes it returns', function () {
        // The marker goes on both, so "is this page the cached one?" is answerable
        // from a browser rather than from an SSH session — and a diff between the two
        // would make that test lie.
        $returned = $this->recorder->onFlush(pageCacheBody());

        expect($this->store->get(CacheKey::create('example.com', '/blog/')))->toBe($returned);
    });

    it('marks the page with the time it was rendered', function () {
        $returned = $this->recorder->onFlush(pageCacheBody());

        expect($returned)->toMatch('/<!-- foehn cache: \d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00 -->/');
    });

    it('stores nothing when the response is not eligible, and changes nothing either', function () {
        $GLOBALS['wp_stub_logged_in'] = true;
        $body = pageCacheBody();

        expect($this->recorder->onFlush($body))->toBe($body);
        expect($this->store->stats()['files'])->toBe(0);
    });

    it('stores nothing for a render that died mid-template', function () {
        $truncated = '<html><body>' . str_repeat('x', 300) . '<b>Fatal error</b>';

        expect($this->recorder->onFlush($truncated))->toBe($truncated);
        expect($this->store->stats()['files'])->toBe(0);
    });

    it('opens no buffer for a request it already knows it will not store', function () {
        // Wrapping a feed or a REST response in ob_start() is a behaviour change this
        // feature has no business making.
        wp_stub_set_conditional('is_feed', true);

        $depth = ob_get_level();
        $this->recorder->start();

        expect(ob_get_level())->toBe($depth);
    });

    it('opens a buffer for a request that could still be stored', function () {
        $depth = ob_get_level();
        $this->recorder->start();

        expect(ob_get_level())->toBe($depth + 1);

        ob_end_clean();
    });

    it('opens one buffer however often it is asked to', function () {
        $depth = ob_get_level();
        $this->recorder->start();
        $this->recorder->start();

        expect(ob_get_level())->toBe($depth + 1);

        ob_end_clean();
    });

    it('captures on template_redirect, before anything picks a template', function () {
        // Føhn's own TemplateControllerDiscovery runs on template_include at priority
        // 5, inside that buffer, so nothing about rendering has to change.
        $this->recorder->register();

        $registered = array_filter(
            wp_stub_get_calls('add_action'),
            static fn(array $call): bool => $call['args']['hook'] === 'template_redirect',
        );

        expect($registered)->toHaveCount(1);
        expect(reset($registered)['args']['priority'])->toBe(0);
    });
});
