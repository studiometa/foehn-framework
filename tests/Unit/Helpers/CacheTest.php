<?php

declare(strict_types=1);

use Studiometa\Foehn\Helpers\Cache;

describe('Cache', function () {
    beforeEach(function () {
        wp_stub_reset();
        Cache::setPrefix('foehn_');
    });

    describe('get/set', function () {
        it('returns default when key not found', function () {
            $GLOBALS['wp_stub_transients'] = [];

            expect(Cache::get('missing'))->toBeNull();
            expect(Cache::get('missing', 'default'))->toBe('default');
        });

        it('stores and retrieves values', function () {
            Cache::set('key', 'value', 3600);

            $calls = wp_stub_get_calls('set_transient');
            expect($calls)->toHaveCount(1);
            expect($calls[0]['args']['transient'])->toBe('foehn_key');
            expect($calls[0]['args']['value'])->toBe('value');
            expect($calls[0]['args']['expiration'])->toBe(3600);
        });

        it('uses prefix for keys', function () {
            Cache::setPrefix('custom_');
            Cache::set('key', 'value');

            $calls = wp_stub_get_calls('set_transient');
            expect($calls[0]['args']['transient'])->toBe('custom_key');
        });
    });

    describe('has', function () {
        it('returns false when key not found', function () {
            $GLOBALS['wp_stub_transients'] = [];

            expect(Cache::has('missing'))->toBeFalse();
        });

        it('returns true when key exists', function () {
            $GLOBALS['wp_stub_transients'] = ['foehn_exists' => 'value'];

            expect(Cache::has('exists'))->toBeTrue();
        });
    });

    describe('remember', function () {
        it('returns cached value if exists', function () {
            $GLOBALS['wp_stub_transients'] = ['foehn_key' => 'cached'];
            $called = false;

            $result = Cache::remember('key', 3600, function () use (&$called) {
                $called = true;

                return 'computed';
            });

            expect($result)->toBe('cached');
            expect($called)->toBeFalse();
        });

        it('computes and caches value if not exists', function () {
            $GLOBALS['wp_stub_transients'] = [];

            $result = Cache::remember('key', 3600, fn() => 'computed');

            expect($result)->toBe('computed');

            $calls = wp_stub_get_calls('set_transient');
            expect($calls)->toHaveCount(1);
        });
    });

    describe('forget', function () {
        it('deletes cached value', function () {
            Cache::forget('key');

            $calls = wp_stub_get_calls('delete_transient');
            expect($calls)->toHaveCount(1);
            expect($calls[0]['args']['transient'])->toBe('foehn_key');
        });
    });

    describe('forever', function () {
        it('stores value with no expiration', function () {
            Cache::forever('key', 'value');

            $calls = wp_stub_get_calls('set_transient');
            expect($calls[0]['args']['expiration'])->toBe(0);
        });
    });

    describe('increment/decrement', function () {
        it('increments numeric value', function () {
            $GLOBALS['wp_stub_transients'] = ['foehn_counter' => 5];

            $result = Cache::increment('counter');

            expect($result)->toBe(6);
        });

        it('decrements numeric value', function () {
            $GLOBALS['wp_stub_transients'] = ['foehn_counter' => 5];

            $result = Cache::decrement('counter');

            expect($result)->toBe(4);
        });

        it('starts from zero if not exists', function () {
            $GLOBALS['wp_stub_transients'] = [];

            $result = Cache::increment('new_counter', 10);

            expect($result)->toBe(10);
        });
    });
});
