<?php

declare(strict_types=1);

use Studiometa\Foehn\Helpers\Cache;
use Studiometa\Foehn\Helpers\TaggedCache;

describe('TaggedCache', function () {
    beforeEach(function () {
        wp_stub_reset();
        Cache::setPrefix('foehn_');
    });

    describe('put', function () {
        it('stores value in cache', function () {
            Cache::tags(['products'])->put('products_list', ['item1', 'item2'], 3600);

            $calls = wp_stub_get_calls('set_transient');
            expect($calls)->toHaveCount(1);
            expect($calls[0]['args']['transient'])->toBe('foehn_products_list');
            expect($calls[0]['args']['value'])->toBe(['item1', 'item2']);
        });

        it('registers key with tags', function () {
            Cache::tags(['products', 'shop'])->put('products_list', 'value');

            $mapping = TaggedCache::getTagsMapping();
            expect($mapping)->toHaveKey('products');
            expect($mapping)->toHaveKey('shop');
            expect($mapping['products'])->toContain('products_list');
            expect($mapping['shop'])->toContain('products_list');
        });

        it('does not duplicate keys in tag mapping', function () {
            Cache::tags(['products'])->put('products_list', 'value1');
            Cache::tags(['products'])->put('products_list', 'value2');

            $mapping = TaggedCache::getTagsMapping();
            expect($mapping['products'])->toHaveCount(1);
        });
    });

    describe('remember', function () {
        it('returns cached value if exists', function () {
            $GLOBALS['wp_stub_transients'] = ['foehn_key' => 'cached'];
            $called = false;

            $result = Cache::tags(['test'])->remember('key', 3600, function () use (&$called) {
                $called = true;

                return 'computed';
            });

            expect($result)->toBe('cached');
            expect($called)->toBeFalse();
        });

        it('computes and caches value with tags if not exists', function () {
            $GLOBALS['wp_stub_transients'] = [];

            $result = Cache::tags(['products', 'archive'])->remember('products_page_1', 3600, fn() => 'computed');

            expect($result)->toBe('computed');

            $mapping = TaggedCache::getTagsMapping();
            expect($mapping['products'])->toContain('products_page_1');
            expect($mapping['archive'])->toContain('products_page_1');
        });
    });

    describe('rememberForever', function () {
        it('stores value with no expiration', function () {
            $GLOBALS['wp_stub_transients'] = [];

            Cache::tags(['permanent'])->rememberForever('key', fn() => 'value');

            $calls = wp_stub_get_calls('set_transient');
            expect($calls[0]['args']['expiration'])->toBe(0);
        });
    });

    describe('forever', function () {
        it('stores value with no expiration', function () {
            Cache::tags(['test'])->forever('key', 'value');

            $calls = wp_stub_get_calls('set_transient');
            expect($calls[0]['args']['expiration'])->toBe(0);
        });
    });

    describe('forget', function () {
        it('removes value from cache', function () {
            Cache::tags(['products'])->put('products_list', 'value');
            Cache::tags(['products'])->forget('products_list');

            $calls = wp_stub_get_calls('delete_transient');
            expect($calls)->toHaveCount(1);
            expect($calls[0]['args']['transient'])->toBe('foehn_products_list');
        });

        it('removes key from tag mapping', function () {
            Cache::tags(['products', 'shop'])->put('products_list', 'value');
            Cache::tags(['products'])->forget('products_list');

            $mapping = TaggedCache::getTagsMapping();
            expect($mapping)->not->toHaveKey('products');
            expect($mapping)->not->toHaveKey('shop');
        });
    });

    describe('flushTag', function () {
        it('flushes all keys with the tag', function () {
            $GLOBALS['wp_stub_transients'] = [
                'foehn_products_page_1' => 'data1',
                'foehn_products_page_2' => 'data2',
                'foehn_categories' => 'data3',
            ];

            Cache::tags(['products'])->put('products_page_1', 'data1');
            Cache::tags(['products'])->put('products_page_2', 'data2');
            Cache::tags(['categories'])->put('categories', 'data3');

            $flushed = Cache::flushTag('products');

            expect($flushed)->toBe(2);

            $deleteCalls = wp_stub_get_calls('delete_transient');
            $deletedKeys = array_map(fn($call) => $call['args']['transient'], $deleteCalls);
            expect($deletedKeys)->toContain('foehn_products_page_1');
            expect($deletedKeys)->toContain('foehn_products_page_2');
        });

        it('removes the tag from mapping', function () {
            Cache::tags(['products'])->put('products_list', 'value');

            Cache::flushTag('products');

            $mapping = TaggedCache::getTagsMapping();
            expect($mapping)->not->toHaveKey('products');
        });

        it('returns 0 for non-existent tag', function () {
            $flushed = Cache::flushTag('nonexistent');

            expect($flushed)->toBe(0);
        });

        it('cleans up keys from other tags when flushed', function () {
            Cache::tags(['products', 'archive'])->put('products_page_1', 'data1');
            Cache::tags(['categories', 'archive'])->put('categories_list', 'data2');

            Cache::flushTag('products');

            $mapping = TaggedCache::getTagsMapping();
            expect($mapping)->not->toHaveKey('products');
            expect($mapping['archive'])->not->toContain('products_page_1');
            expect($mapping['archive'])->toContain('categories_list');
        });

        it('handles failed cache deletion gracefully', function () {
            // Register a key with a tag but don't add it to transients
            // so delete_transient will return false
            Cache::tags(['products'])->put('products_list', 'data');

            // Remove the transient so forget() returns false
            unset($GLOBALS['wp_stub_transients']['foehn_products_list']);

            $flushed = Cache::flushTag('products');

            // Should return 0 since deletion failed
            expect($flushed)->toBe(0);

            // Tag should still be removed from mapping
            $mapping = TaggedCache::getTagsMapping();
            expect($mapping)->not->toHaveKey('products');
        });

        it('removes empty tags after cleanup', function () {
            // Create a scenario where tag 'archive' only contains keys from 'products'
            Cache::tags(['products', 'archive'])->put('products_page_1', 'data1');

            // Flush products - this should also remove 'archive' since it becomes empty
            Cache::flushTag('products');

            $mapping = TaggedCache::getTagsMapping();
            expect($mapping)->not->toHaveKey('products');
            expect($mapping)->not->toHaveKey('archive');
        });
    });

    describe('flushTags', function () {
        it('flushes multiple tags at once', function () {
            $GLOBALS['wp_stub_transients'] = [
                'foehn_products' => 'data1',
                'foehn_categories' => 'data2',
                'foehn_users' => 'data3',
            ];

            Cache::tags(['products'])->put('products', 'data1');
            Cache::tags(['categories'])->put('categories', 'data2');
            Cache::tags(['users'])->put('users', 'data3');

            $flushed = Cache::flushTags(['products', 'categories']);

            expect($flushed)->toBe(2);

            $mapping = TaggedCache::getTagsMapping();
            expect($mapping)->not->toHaveKey('products');
            expect($mapping)->not->toHaveKey('categories');
            expect($mapping)->toHaveKey('users');
        });
    });

    describe('clearTagsMapping', function () {
        it('clears all tag mappings', function () {
            Cache::tags(['products'])->put('key1', 'value1');
            Cache::tags(['categories'])->put('key2', 'value2');

            TaggedCache::clearTagsMapping();

            $mapping = TaggedCache::getTagsMapping();
            expect($mapping)->toBeEmpty();
        });
    });

    describe('getTagsMapping', function () {
        it('returns empty array when no tags exist', function () {
            $mapping = TaggedCache::getTagsMapping();

            expect($mapping)->toBeArray();
            expect($mapping)->toBeEmpty();
        });

        it('returns current mapping', function () {
            Cache::tags(['a', 'b'])->put('key1', 'value1');
            Cache::tags(['b', 'c'])->put('key2', 'value2');

            $mapping = TaggedCache::getTagsMapping();

            expect($mapping)->toHaveKey('a');
            expect($mapping)->toHaveKey('b');
            expect($mapping)->toHaveKey('c');
            expect($mapping['a'])->toBe(['key1']);
            expect($mapping['b'])->toBe(['key1', 'key2']);
            expect($mapping['c'])->toBe(['key2']);
        });
    });
});
