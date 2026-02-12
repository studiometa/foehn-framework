<?php

declare(strict_types=1);

use Studiometa\Foehn\Cache\TransientCache;

describe('TransientCache', function () {
    beforeEach(function () {
        wp_stub_reset();
        $this->cache = new TransientCache();
    });

    describe('get/set', function () {
        it('returns default when key not found', function () {
            $GLOBALS['wp_stub_transients'] = [];

            expect($this->cache->get('missing'))->toBeNull();
            expect($this->cache->get('missing', 'default'))->toBe('default');
        });

        it('stores and retrieves values', function () {
            $this->cache->set('key', 'value', 3600);

            $calls = wp_stub_get_calls('set_transient');
            expect($calls)->toHaveCount(1);
            expect($calls[0]['args']['transient'])->toBe('foehn_key');
            expect($calls[0]['args']['value'])->toBe('value');
            expect($calls[0]['args']['expiration'])->toBe(3600);
        });

        it('uses custom prefix for keys', function () {
            $cache = new TransientCache('custom_');
            $cache->set('key', 'value');

            $calls = wp_stub_get_calls('set_transient');
            expect($calls[0]['args']['transient'])->toBe('custom_key');
        });
    });

    describe('has', function () {
        it('returns false when key not found', function () {
            $GLOBALS['wp_stub_transients'] = [];

            expect($this->cache->has('missing'))->toBeFalse();
        });

        it('returns true when key exists', function () {
            $GLOBALS['wp_stub_transients'] = ['foehn_exists' => 'value'];

            expect($this->cache->has('exists'))->toBeTrue();
        });
    });

    describe('remember', function () {
        it('returns cached value if exists', function () {
            $GLOBALS['wp_stub_transients'] = ['foehn_key' => 'cached'];
            $called = false;

            $result = $this->cache->remember('key', 3600, function () use (&$called) {
                $called = true;

                return 'computed';
            });

            expect($result)->toBe('cached');
            expect($called)->toBeFalse();
        });

        it('computes and caches value if not exists', function () {
            $GLOBALS['wp_stub_transients'] = [];

            $result = $this->cache->remember('key', 3600, fn() => 'computed');

            expect($result)->toBe('computed');

            $calls = wp_stub_get_calls('set_transient');
            expect($calls)->toHaveCount(1);
        });
    });

    describe('forget', function () {
        it('deletes cached value', function () {
            $this->cache->forget('key');

            $calls = wp_stub_get_calls('delete_transient');
            expect($calls)->toHaveCount(1);
            expect($calls[0]['args']['transient'])->toBe('foehn_key');
        });
    });

    describe('forever', function () {
        it('stores value with no expiration', function () {
            $this->cache->forever('key', 'value');

            $calls = wp_stub_get_calls('set_transient');
            expect($calls[0]['args']['expiration'])->toBe(0);
        });
    });

    describe('increment/decrement', function () {
        it('increments numeric value', function () {
            $GLOBALS['wp_stub_transients'] = ['foehn_counter' => 5];

            $result = $this->cache->increment('counter');

            expect($result)->toBe(6);
        });

        it('decrements numeric value', function () {
            $GLOBALS['wp_stub_transients'] = ['foehn_counter' => 5];

            $result = $this->cache->decrement('counter');

            expect($result)->toBe(4);
        });

        it('starts from zero if not exists', function () {
            $GLOBALS['wp_stub_transients'] = [];

            $result = $this->cache->increment('new_counter', 10);

            expect($result)->toBe(10);
        });
    });

    describe('rememberForever', function () {
        it('stores value with no expiration', function () {
            $GLOBALS['wp_stub_transients'] = [];

            $result = $this->cache->rememberForever('key', fn() => 'computed');

            expect($result)->toBe('computed');

            $calls = wp_stub_get_calls('set_transient');
            expect($calls[0]['args']['expiration'])->toBe(0);
        });
    });

    describe('tags', function () {
        it('returns a TaggedCache instance', function () {
            $tagged = $this->cache->tags(['products']);

            expect($tagged)->toBeInstanceOf(\Studiometa\Foehn\Cache\TaggedCache::class);
        });
    });

    describe('flushTags', function () {
        it('flushes multiple tags', function () {
            $GLOBALS['wp_stub_transients'] = [
                'foehn_a' => 'data1',
                'foehn_b' => 'data2',
            ];

            $this->cache->tags(['x'])->put('a', 'data1');
            $this->cache->tags(['y'])->put('b', 'data2');

            $flushed = $this->cache->flushTags(['x', 'y']);

            expect($flushed)->toBe(2);
        });
    });

    describe('getPrefix', function () {
        it('returns the default prefix', function () {
            expect($this->cache->getPrefix())->toBe('foehn_');
        });

        it('returns a custom prefix', function () {
            $cache = new TransientCache('app_');
            expect($cache->getPrefix())->toBe('app_');
        });
    });
});
