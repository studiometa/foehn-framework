<?php

declare(strict_types=1);

use Psr\SimpleCache\CacheInterface as SimpleCacheInterface;
use Studiometa\Foehn\Cache\InvalidArgumentException;
use Studiometa\Foehn\Cache\TaggedCache;
use Studiometa\Foehn\Cache\TransientCache;

describe('TaggedCache', function () {
    beforeEach(function () {
        wp_stub_reset();
        $this->cache = new TransientCache();
    });

    describe('set', function () {
        it('stores value in cache', function () {
            $this->cache->tags(['products'])->set('products_list', ['item1', 'item2'], 3600);

            $calls = wp_stub_get_calls('set_transient');
            expect($calls)->toHaveCount(1);
            expect($calls[0]['args']['transient'])->toBe('foehn_1_products_list');
            expect($calls[0]['args']['value'])->toBe(['value' => ['item1', 'item2']]);
        });

        it('registers key with tags', function () {
            $this->cache->tags(['products', 'shop'])->set('products_list', 'value');

            $mapping = TaggedCache::getTagsMapping();
            expect($mapping)->toHaveKey('products');
            expect($mapping)->toHaveKey('shop');
            expect($mapping['products'])->toContain('products_list');
            expect($mapping['shop'])->toContain('products_list');
        });

        it('does not duplicate keys in tag mapping', function () {
            $this->cache->tags(['products'])->set('products_list', 'value1');
            $this->cache->tags(['products'])->set('products_list', 'value2');

            $mapping = TaggedCache::getTagsMapping();
            expect($mapping['products'])->toHaveCount(1);
        });
    });

    it('is a PSR-16 cache', function () {
        expect($this->cache->tags(['products']))->toBeInstanceOf(SimpleCacheInterface::class);
    });

    describe('get/has', function () {
        it('reads the entries of the wrapped cache', function () {
            $this->cache->tags(['products'])->set('list', [1, 2]);

            $tagged = $this->cache->tags(['other']);
            expect($tagged->get('list'))->toBe([1, 2]);
            expect($tagged->has('list'))->toBeTrue();
            expect($tagged->get('missing', 'default'))->toBe('default');
            expect($tagged->has('missing'))->toBeFalse();
        });
    });

    describe('set with a TTL', function () {
        it('passes the TTL to the wrapped cache', function () {
            $this->cache->tags(['products'])->set('list', 'value', new DateInterval('PT2M'));

            $calls = wp_stub_get_calls('set_transient');
            expect($calls[0]['args']['expiration'])->toBe(120);
        });

        it('stores with no expiration when the TTL is null', function () {
            $this->cache->tags(['products'])->set('list', 'value');

            $calls = wp_stub_get_calls('set_transient');
            expect($calls[0]['args']['expiration'])->toBe(0);
        });

        it('deletes the entry and its tag associations when the TTL is zero or negative', function (int $ttl) {
            $tagged = $this->cache->tags(['products']);
            $tagged->set('list', 'value');

            expect($tagged->set('list', 'value', $ttl))->toBeTrue();

            expect($tagged->has('list'))->toBeFalse();
            expect(TaggedCache::getTagsMapping())->toBeEmpty();
        })->with([0, -1]);

        it('rejects an invalid key before recording it', function () {
            expect(fn() => $this->cache->tags(['products'])->set('a:b', 'value'))
                ->toThrow(InvalidArgumentException::class);

            expect(TaggedCache::getTagsMapping())->toBeEmpty();
        });
    });

    describe('clear', function () {
        it('flushes the tags of the instance only', function () {
            $this->cache->tags(['products'])->set('a', 1);
            $this->cache->tags(['categories'])->set('b', 2);

            expect($this->cache->tags(['products'])->clear())->toBeTrue();

            expect($this->cache->has('a'))->toBeFalse();
            expect($this->cache->has('b'))->toBeTrue();
            expect(TaggedCache::getTagsMapping())->toBe(['categories' => ['b']]);
        });
    });

    describe('getMultiple/setMultiple/deleteMultiple', function () {
        it('records every key under the tags', function () {
            $tagged = $this->cache->tags(['products']);

            expect($tagged->setMultiple(['a' => 1, 'b' => 2], 60))->toBeTrue();

            expect($tagged->getMultiple(['a', 'b', 'c']))->toBe(['a' => 1, 'b' => 2, 'c' => null]);
            expect(TaggedCache::getTagsMapping())->toBe(['products' => ['a', 'b']]);
        });

        it('deletes the keys and their tag associations', function () {
            $tagged = $this->cache->tags(['products']);
            $tagged->setMultiple(['a' => 1, 'b' => 2, 'c' => 3]);

            expect($tagged->deleteMultiple(['a', 'b']))->toBeTrue();

            expect($tagged->getMultiple(['a', 'b', 'c']))->toBe(['a' => null, 'b' => null, 'c' => 3]);
            expect(TaggedCache::getTagsMapping())->toBe(['products' => ['c']]);
        });

        it('rejects a key that is not a string', function () {
            expect(fn() => $this->cache->tags(['products'])->deleteMultiple([1]))
                ->toThrow(InvalidArgumentException::class);
        });
    });

    describe('remember', function () {
        it('returns cached value if exists', function () {
            $this->cache->set('key', 'cached');
            $called = false;

            $result = $this->cache->tags(['test'])->remember('key', 3600, function () use (&$called) {
                $called = true;

                return 'computed';
            });

            expect($result)->toBe('cached');
            expect($called)->toBeFalse();
        });

        it('returns a cached null without computing', function () {
            $this->cache->set('key', null);

            expect($this->cache->tags(['test'])->remember('key', 3600, fn() => 'computed'))->toBeNull();
        });

        it('computes and caches value with tags if not exists', function () {
            $result = $this->cache->tags(['products', 'archive'])->remember(
                'products_page_1',
                3600,
                fn() => 'computed',
            );

            expect($result)->toBe('computed');

            $mapping = TaggedCache::getTagsMapping();
            expect($mapping['products'])->toContain('products_page_1');
            expect($mapping['archive'])->toContain('products_page_1');
        });
    });

    describe('delete', function () {
        it('removes value from cache', function () {
            $this->cache->tags(['products'])->set('products_list', 'value');
            $this->cache->tags(['products'])->delete('products_list');

            $calls = wp_stub_get_calls('delete_transient');
            expect($calls)->toHaveCount(1);
            expect($calls[0]['args']['transient'])->toBe('foehn_1_products_list');
        });

        it('removes key from tag mapping', function () {
            $this->cache->tags(['products', 'shop'])->set('products_list', 'value');
            $this->cache->tags(['products'])->delete('products_list');

            $mapping = TaggedCache::getTagsMapping();
            expect($mapping)->not->toHaveKey('products');
            expect($mapping)->not->toHaveKey('shop');
        });
    });

    describe('flushTag', function () {
        it('flushes all keys with the tag', function () {
            $this->cache->tags(['products'])->set('products_page_1', 'data1');
            $this->cache->tags(['products'])->set('products_page_2', 'data2');
            $this->cache->tags(['categories'])->set('categories', 'data3');

            $flushed = $this->cache->flushTag('products');

            expect($flushed)->toBe(2);

            $deleteCalls = wp_stub_get_calls('delete_transient');
            $deletedKeys = array_map(fn($call) => $call['args']['transient'], $deleteCalls);
            expect($deletedKeys)->toContain('foehn_1_products_page_1');
            expect($deletedKeys)->toContain('foehn_1_products_page_2');
            expect($this->cache->has('categories'))->toBeTrue();
        });

        it('removes the tag from mapping', function () {
            $this->cache->tags(['products'])->set('products_list', 'value');

            $this->cache->flushTag('products');

            $mapping = TaggedCache::getTagsMapping();
            expect($mapping)->not->toHaveKey('products');
        });

        it('returns 0 for non-existent tag', function () {
            $flushed = $this->cache->flushTag('nonexistent');

            expect($flushed)->toBe(0);
        });

        it('cleans up keys from other tags when flushed', function () {
            $this->cache->tags(['products', 'archive'])->set('products_page_1', 'data1');
            $this->cache->tags(['categories', 'archive'])->set('categories_list', 'data2');

            $this->cache->flushTag('products');

            $mapping = TaggedCache::getTagsMapping();
            expect($mapping)->not->toHaveKey('products');
            expect($mapping['archive'])->not->toContain('products_page_1');
            expect($mapping['archive'])->toContain('categories_list');
        });

        it('does not count an entry that has expired', function () {
            $this->cache->tags(['products'])->set('products_list', 'data');

            // The entry has expired: there is nothing left to flush
            unset($GLOBALS['wp_stub_transients']['foehn_1_products_list']);

            $flushed = $this->cache->flushTag('products');

            expect($flushed)->toBe(0);

            $mapping = TaggedCache::getTagsMapping();
            expect($mapping)->not->toHaveKey('products');
        });

        it('drops a key PSR-16 does not allow from an older mapping', function () {
            $GLOBALS['wp_stub_options']['foehn_cache_tags'] = ['users' => ['user:1', 'user_2']];
            $this->cache->set('user_2', 'data');

            expect($this->cache->flushTag('users'))->toBe(1);
            expect(TaggedCache::getTagsMapping())->toBeEmpty();
        });

        it('removes empty tags after cleanup', function () {
            $this->cache->tags(['products', 'archive'])->set('products_page_1', 'data1');

            $this->cache->flushTag('products');

            $mapping = TaggedCache::getTagsMapping();
            expect($mapping)->not->toHaveKey('products');
            expect($mapping)->not->toHaveKey('archive');
        });
    });

    describe('flushTags', function () {
        it('flushes multiple tags at once', function () {
            $this->cache->tags(['products'])->set('products', 'data1');
            $this->cache->tags(['categories'])->set('categories', 'data2');
            $this->cache->tags(['users'])->set('users', 'data3');

            $flushed = $this->cache->flushTags(['products', 'categories']);

            expect($flushed)->toBe(2);

            $mapping = TaggedCache::getTagsMapping();
            expect($mapping)->not->toHaveKey('products');
            expect($mapping)->not->toHaveKey('categories');
            expect($mapping)->toHaveKey('users');
        });
    });

    describe('clearTagsMapping', function () {
        it('clears all tag mappings', function () {
            $this->cache->tags(['products'])->set('key1', 'value1');
            $this->cache->tags(['categories'])->set('key2', 'value2');

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
            $this->cache->tags(['a', 'b'])->set('key1', 'value1');
            $this->cache->tags(['b', 'c'])->set('key2', 'value2');

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
