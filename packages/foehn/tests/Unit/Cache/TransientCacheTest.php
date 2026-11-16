<?php

declare(strict_types=1);

use Psr\SimpleCache\CacheInterface as SimpleCacheInterface;
use Psr\SimpleCache\InvalidArgumentException as PsrInvalidArgumentException;
use Studiometa\Foehn\Cache\InvalidArgumentException;
use Studiometa\Foehn\Cache\TaggedCache;
use Studiometa\Foehn\Cache\TransientCache;

describe('TransientCache', function () {
    beforeEach(function () {
        wp_stub_reset();
        $GLOBALS['wpdb'] = new wpdb();
        $this->cache = new TransientCache();
    });

    it('is a PSR-16 cache', function () {
        expect($this->cache)->toBeInstanceOf(SimpleCacheInterface::class);
    });

    describe('get/set', function () {
        it('returns default when key not found', function () {
            expect($this->cache->get('missing'))->toBeNull();
            expect($this->cache->get('missing', 'default'))->toBe('default');
        });

        it('stores and retrieves values', function () {
            expect($this->cache->set('key', 'value', 3600))->toBeTrue();

            $calls = wp_stub_get_calls('set_transient');
            expect($calls)->toHaveCount(1);
            expect($calls[0]['args']['transient'])->toBe('foehn_1_key');
            expect($calls[0]['args']['value'])->toBe(['value' => 'value']);
            expect($calls[0]['args']['expiration'])->toBe(3600);
            expect($this->cache->get('key'))->toBe('value');
        });

        it('returns false, null and other values exactly as stored', function (mixed $value) {
            $this->cache->set('key', $value);

            expect($this->cache->get('key', 'default'))->toBe($value);
            expect($this->cache->has('key'))->toBeTrue();
        })->with([
            'false' => [false],
            'null' => [null],
            'zero' => [0],
            'empty string' => [''],
            'array' => [['a' => 1]],
        ]);

        it('reads a transient that is not one of its entries as a miss', function () {
            $GLOBALS['wp_stub_transients'] = ['foehn_1_key' => 'raw'];

            expect($this->cache->get('key', 'default'))->toBe('default');
            expect($this->cache->has('key'))->toBeFalse();
        });

        it('stores with no expiration when the TTL is null', function () {
            $this->cache->set('key', 'value');

            $calls = wp_stub_get_calls('set_transient');
            expect($calls[0]['args']['expiration'])->toBe(0);
        });

        it('converts a DateInterval TTL to seconds', function () {
            $this->cache->set('key', 'value', new DateInterval('PT1H'));

            $calls = wp_stub_get_calls('set_transient');
            expect($calls[0]['args']['expiration'])->toBe(3600);
        });

        it('deletes the entry when the TTL is zero or negative', function (int|DateInterval $ttl) {
            $this->cache->set('key', 'value');

            expect($this->cache->set('key', 'new', $ttl))->toBeTrue();
            expect($this->cache->has('key'))->toBeFalse();
        })->with([
            'zero' => [0],
            'negative' => [-10],
            'past interval' => [DateInterval::createFromDateString('-1 hour')],
        ]);

        it('answers true when the stored value does not change', function () {
            $this->cache->set('key', 'value');
            $GLOBALS['wp_stub_set_transient_result'] = false;

            try {
                expect($this->cache->set('key', 'value'))->toBeTrue();
                expect($this->cache->set('key', 'other'))->toBeFalse();
            } finally {
                unset($GLOBALS['wp_stub_set_transient_result']);
            }
        });

        it('uses custom prefix for keys', function () {
            $cache = new TransientCache('custom_');
            $cache->set('key', 'value');

            $calls = wp_stub_get_calls('set_transient');
            expect($calls[0]['args']['transient'])->toBe('custom_1_key');
        });
    });

    describe('keys', function () {
        it('rejects an invalid key', function (string $key) {
            expect(fn() => $this->cache->get($key))->toThrow(InvalidArgumentException::class);
            expect(fn() => $this->cache->set($key, 'value'))->toThrow(InvalidArgumentException::class);
            expect(fn() => $this->cache->has($key))->toThrow(InvalidArgumentException::class);
            expect(fn() => $this->cache->delete($key))->toThrow(InvalidArgumentException::class);
        })->with(['', 'a{b', 'a}b', 'a(b', 'a)b', 'a/b', 'a\\b', 'a@b', 'a:b']);

        it('throws an exception PSR-16 consumers can catch', function () {
            expect(new InvalidArgumentException())->toBeInstanceOf(PsrInvalidArgumentException::class);
        });

        it('accepts the characters and the length PSR-16 requires', function () {
            $key = str_repeat('aZ0_.', 12) . 'abcd';

            expect(strlen($key))->toBe(64);
            expect($this->cache->set($key, 'value'))->toBeTrue();
            expect($this->cache->get($key))->toBe('value');
        });
    });

    describe('has', function () {
        it('returns false when key not found', function () {
            expect($this->cache->has('missing'))->toBeFalse();
        });

        it('returns true when key exists', function () {
            $this->cache->set('exists', 'value');

            expect($this->cache->has('exists'))->toBeTrue();
        });
    });

    describe('delete', function () {
        it('deletes cached value', function () {
            $this->cache->set('key', 'value');

            expect($this->cache->delete('key'))->toBeTrue();

            $calls = wp_stub_get_calls('delete_transient');
            expect($calls)->toHaveCount(1);
            expect($calls[0]['args']['transient'])->toBe('foehn_1_key');
            expect($this->cache->has('key'))->toBeFalse();
        });

        it('answers true for a missing key', function () {
            expect($this->cache->delete('missing'))->toBeTrue();
        });
    });

    describe('clear', function () {
        it('makes every entry a miss', function () {
            $this->cache->set('a', 1);
            $this->cache->set('b', 2);

            expect($this->cache->clear())->toBeTrue();

            expect($this->cache->has('a'))->toBeFalse();
            expect($this->cache->get('b', 'default'))->toBe('default');
        });

        it('writes new entries under the next version', function () {
            $this->cache->clear();
            $this->cache->set('key', 'value');

            expect(get_option('foehn_cache_version'))->toBe(2);
            expect($GLOBALS['wp_stub_transients'])->toHaveKey('foehn_2_key');
        });

        it('leaves the entries of another prefix', function () {
            $other = new TransientCache('other_');
            $other->set('key', 'kept');

            $this->cache->clear();

            expect($other->get('key'))->toBe('kept');
        });

        it('forgets the tag mapping of its prefix only', function () {
            $other = new TransientCache('other_');
            $this->cache->tags(['x'])->set('a', 1);
            $other->tags(['x'])->set('b', 1);

            $this->cache->clear();

            expect(TaggedCache::getTagsMapping('foehn_cache_tags'))->toBeEmpty();
            expect(TaggedCache::getTagsMapping('other_cache_tags'))->toBe(['x' => ['b']]);
        });

        it('deletes the rows of its prefix from wp_options without an object cache', function () {
            $this->cache->clear();

            expect($GLOBALS['wpdb']->queries)->toHaveCount(1);
            expect($GLOBALS['wpdb']->queries[0])
                ->toContain('DELETE FROM wp_options')
                ->toContain("\\_transient\\_foehn\\_%")
                ->toContain("\\_transient\\_timeout\\_foehn\\_%");
        });

        it('leaves wp_options alone with a persistent object cache', function () {
            $GLOBALS['wp_stub_ext_object_cache'] = true;

            $this->cache->set('key', 'value');
            $this->cache->clear();

            expect($GLOBALS['wpdb']->queries)->toBe([]);
            expect($this->cache->has('key'))->toBeFalse();
        });
    });

    describe('getMultiple/setMultiple/deleteMultiple', function () {
        it('stores and reads several values', function () {
            expect($this->cache->setMultiple(['a' => 1, 'b' => false], 60))->toBeTrue();

            expect($this->cache->getMultiple(['a', 'b', 'c'], 'default'))->toBe([
                'a' => 1,
                'b' => false,
                'c' => 'default',
            ]);
        });

        it('accepts any iterable', function () {
            $values = (static function (): Generator {
                yield 'a' => 1;
                yield 'b' => 2;
            })();

            $this->cache->setMultiple($values);

            expect($this->cache->getMultiple(new ArrayIterator(['a', 'b'])))->toBe(['a' => 1, 'b' => 2]);
        });

        it('accepts the integer keys PHP makes of numeric strings', function () {
            $this->cache->setMultiple(['123' => 'value']);

            expect($this->cache->get('123'))->toBe('value');
        });

        it('deletes several values', function () {
            $this->cache->setMultiple(['a' => 1, 'b' => 2, 'c' => 3]);

            expect($this->cache->deleteMultiple(['a', 'b']))->toBeTrue();

            expect($this->cache->getMultiple(['a', 'b', 'c']))->toBe(['a' => null, 'b' => null, 'c' => 3]);
        });

        it('deletes the entries when the TTL is zero', function () {
            $this->cache->setMultiple(['a' => 1, 'b' => 2]);

            $this->cache->setMultiple(['a' => 3, 'b' => 4], 0);

            expect($this->cache->getMultiple(['a', 'b']))->toBe(['a' => null, 'b' => null]);
        });

        it('rejects a key that is not a string', function () {
            expect(fn() => $this->cache->getMultiple([1]))->toThrow(InvalidArgumentException::class);
            expect(fn() => $this->cache->deleteMultiple([null]))->toThrow(InvalidArgumentException::class);
        });

        it('rejects an invalid key', function () {
            expect(fn() => $this->cache->getMultiple(['a:b']))->toThrow(InvalidArgumentException::class);
            expect(fn() => $this->cache->setMultiple(['' => 1]))->toThrow(InvalidArgumentException::class);
            expect(fn() => $this->cache->deleteMultiple(['a@b']))->toThrow(InvalidArgumentException::class);
        });
    });

    describe('remember', function () {
        it('returns cached value if exists', function () {
            $this->cache->set('key', 'cached');
            $called = false;

            $result = $this->cache->remember('key', 3600, function () use (&$called) {
                $called = true;

                return 'computed';
            });

            expect($result)->toBe('cached');
            expect($called)->toBeFalse();
        });

        it('returns a cached null or false without computing', function (mixed $value) {
            $this->cache->set('key', $value);

            expect($this->cache->remember('key', 3600, fn() => 'computed'))->toBe($value);
        })->with([[null], [false]]);

        it('computes and caches value if not exists', function () {
            $result = $this->cache->remember('key', 3600, fn() => 'computed');

            expect($result)->toBe('computed');

            $calls = wp_stub_get_calls('set_transient');
            expect($calls)->toHaveCount(1);
            expect($calls[0]['args']['expiration'])->toBe(3600);
        });

        it('stores with no expiration when the TTL is null', function () {
            $this->cache->remember('key', null, fn() => 'computed');

            $calls = wp_stub_get_calls('set_transient');
            expect($calls[0]['args']['expiration'])->toBe(0);
        });
    });

    describe('increment/decrement', function () {
        it('increments numeric value', function () {
            $this->cache->set('counter', 5);

            expect($this->cache->increment('counter'))->toBe(6);
            expect($this->cache->get('counter'))->toBe(6);
        });

        it('decrements numeric value', function () {
            $this->cache->set('counter', 5);

            expect($this->cache->decrement('counter'))->toBe(4);
        });

        it('starts from zero if not exists', function () {
            expect($this->cache->increment('new_counter', 10))->toBe(10);

            $calls = wp_stub_get_calls('set_transient');
            expect($calls[0]['args']['expiration'])->toBe(0);
        });
    });

    describe('tags', function () {
        it('returns a TaggedCache instance', function () {
            $tagged = $this->cache->tags(['products']);

            expect($tagged)->toBeInstanceOf(TaggedCache::class);
        });

        it('keeps the tag mapping of a prefix in its own option', function () {
            new TransientCache('app_')->tags(['x'])->set('a', 1);

            expect(TaggedCache::getTagsMapping('app_cache_tags'))->toBe(['x' => ['a']]);
            expect(TaggedCache::getTagsMapping())->toBeEmpty();
        });
    });

    describe('flushTags', function () {
        it('flushes multiple tags', function () {
            $this->cache->tags(['x'])->set('a', 'data1');
            $this->cache->tags(['y'])->set('b', 'data2');

            $flushed = $this->cache->flushTags(['x', 'y']);

            expect($flushed)->toBe(2);
            expect($this->cache->has('a'))->toBeFalse();
            expect($this->cache->has('b'))->toBeFalse();
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
