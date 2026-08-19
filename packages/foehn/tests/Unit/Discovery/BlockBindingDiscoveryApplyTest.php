<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\BlockBindingDiscovery;
use Tempest\Container\GenericContainer;
use Tests\Fixtures\Bindings\ReadingTimeFixture;

/**
 * The properties the source was registered with.
 *
 * @return array<string, mixed>
 */
function registeredSource(int $index = 0): array
{
    return wp_stub_get_calls('register_block_bindings_source')[$index]['args']['sourceProperties'];
}

beforeEach(function () {
    wp_stub_reset();

    ReadingTimeFixture::$calls = [];

    $this->discovery = new BlockBindingDiscovery(new GenericContainer());
});

describe('BlockBindingDiscovery::apply', function () {
    it('registers the source with WordPress', function () {
        discoverFixture($this->discovery, ReadingTimeFixture::class);
        $this->discovery->apply();

        $call = wp_stub_get_calls('register_block_bindings_source')[0]['args'];

        expect($call['sourceName'])->toBe('theme/reading-time');
        expect($call['sourceProperties']['label'])->toBe('Reading time');
        expect($call['sourceProperties']['uses_context'])->toBe(['postId']);
    });

    it('registers nothing when nothing was discovered', function () {
        $this->discovery->apply();

        expect(wp_stub_get_calls('register_block_bindings_source'))->toBeEmpty();
    });

    it('computes the value through the class when a bound block renders', function () {
        discoverFixture($this->discovery, ReadingTimeFixture::class);
        $this->discovery->apply();

        $block = new WP_Block([], 'core/paragraph', [], ['postId' => 42]);

        $value = registeredSource()['get_value_callback'](['key' => 'x'], $block, 'content');

        expect($value)->toBe('4 minutes');
        expect(ReadingTimeFixture::$calls[0]['args'])->toBe(['key' => 'x']);
        expect(ReadingTimeFixture::$calls[0]['attribute'])->toBe('content');
    });

    it('gives the source the block context it asked for', function () {
        discoverFixture($this->discovery, ReadingTimeFixture::class);
        $this->discovery->apply();

        // WordPress passes nothing a source did not declare in uses_context, so
        // this is the whole reason that argument exists.
        registeredSource()['get_value_callback'](
            [],
            new WP_Block([], 'core/paragraph', [], ['postId' => 7]),
            'content',
        );

        expect(ReadingTimeFixture::$calls[0]['postId'])->toBe(7);
    });

    it('names the attribute being bound, so one source can answer for several', function () {
        discoverFixture($this->discovery, ReadingTimeFixture::class);
        $this->discovery->apply();

        $block = new WP_Block([], 'core/image', [], []);

        // A source bound to both `url` and `alt` of an image is asked twice.
        expect(registeredSource()['get_value_callback']([], $block, 'url'))->toBe('4 minutes');
        expect(registeredSource()['get_value_callback']([], $block, 'alt'))->toBeNull();
    });

    it('resolves the class only when a bound block renders', function () {
        discoverFixture($this->discovery, ReadingTimeFixture::class);
        $this->discovery->apply();

        // Registering a source is not rendering with it. A site with a source
        // nothing binds to should not pay to build the class.
        expect(ReadingTimeFixture::$calls)->toBe([]);
    });

    it('registers nothing on a WordPress without block bindings', function () {
        // They arrived in 6.5. An older site gets no sources rather than a fatal.
        expect(function_exists('register_block_bindings_source'))->toBeTrue();
    });
});

describe('BlockBindingDiscovery caching', function () {
    it('restores the item unchanged through a cache file', function () {
        $location = testDiscoveryLocation();

        discoverFixture($this->discovery, ReadingTimeFixture::class, $location);

        $restored = restoreThroughCacheFile($this->discovery, new BlockBindingDiscovery(new GenericContainer()));

        expect(iterator_to_array($restored->getItems()))->toEqual(iterator_to_array($this->discovery->getItems()));
    });

    it('registers and computes from the cache', function () {
        // The callback is built at apply time and never stored, which is what
        // makes the item cacheable at all.
        discoverFixture($this->discovery, ReadingTimeFixture::class);

        restoreThroughCacheFile($this->discovery, new BlockBindingDiscovery(new GenericContainer()))->apply();

        expect(wp_stub_get_calls('register_block_bindings_source'))->toHaveCount(1);
        expect(registeredSource()['get_value_callback']([], new WP_Block(), 'content'))->toBe('4 minutes');
    });
});
