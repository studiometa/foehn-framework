<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\PostMetaDiscovery;
use Tests\Fixtures\PostMeta\Gallery;
use Tests\Fixtures\PostMeta\Product;

/**
 * The arguments register_meta() was called with, keyed by meta key.
 *
 * @return array<string, array<string, mixed>>
 */
function registeredMeta(): array
{
    $calls = array_column(wp_stub_get_calls('register_meta'), 'args');

    return array_combine(
        array_column($calls, 'metaKey'),
        array_map(static fn(array $call): array => $call['args'] + ['objectType' => $call['objectType']], $calls),
    );
}

beforeEach(fn() => wp_stub_reset());

describe('PostMetaDiscovery::apply', function () {
    it('registers every declared key', function () {
        $discovery = new PostMetaDiscovery();

        discoverFixture($discovery, Product::class);
        $discovery->apply();

        expect(array_keys(registeredMeta()))->toBe(['price', 'sku', 'gallery']);
    });

    it('registers nothing when nothing was discovered', function () {
        new PostMetaDiscovery()->apply();

        expect(wp_stub_get_calls('register_meta'))->toBeEmpty();
    });

    it('passes the type, the subtype and the description through', function () {
        $discovery = new PostMetaDiscovery();

        discoverFixture($discovery, Product::class);
        $discovery->apply();

        $price = registeredMeta()['price'];

        expect($price['objectType'])->toBe('post');
        expect($price['type'])->toBe('number');
        expect($price['single'])->toBeTrue();
        expect($price['object_subtype'])->toBe('product');
        expect($price['description'])->toBe('What it costs');
    });

    it('shows a key in REST by default', function () {
        $discovery = new PostMetaDiscovery();

        discoverFixture($discovery, Product::class);
        $discovery->apply();

        // Without REST the field is invisible to the editor and to bindings,
        // which is the whole reason to declare it.
        expect(registeredMeta()['price']['show_in_rest'])->toBeTrue();
        expect(registeredMeta()['sku']['show_in_rest'])->toBeFalse();
    });

    it('passes an array schema through as show_in_rest', function () {
        $discovery = new PostMetaDiscovery();

        discoverFixture($discovery, Gallery::class);
        $discovery->apply();

        expect(registeredMeta()['credits']['show_in_rest'])->toBe(['schema' => ['items' => ['type' => 'string']]]);
    });

    it('registers a repeatable key as not single', function () {
        $discovery = new PostMetaDiscovery();

        discoverFixture($discovery, Product::class);
        $discovery->apply();

        expect(registeredMeta()['gallery']['single'])->toBeFalse();
        expect(registeredMeta()['gallery']['default'])->toBe(0);
    });

    it('leaves out a default that was never declared', function () {
        $discovery = new PostMetaDiscovery();

        discoverFixture($discovery, Product::class);
        $discovery->apply();

        // register_meta() distinguishes "no default" from "a default of null":
        // the second makes the key appear in REST with a null value.
        expect(registeredMeta()['price'])->not->toHaveKey('default');
    });

    it('builds the auth callback from the declared capability', function () {
        $discovery = new PostMetaDiscovery();

        discoverFixture($discovery, Product::class);
        $discovery->apply();

        registeredMeta()['price']['auth_callback']();

        expect(wp_stub_get_calls('current_user_can')[0]['args']['capability'])->toBe('edit_posts');
    });

    it('resolves the sanitizer to a method on the declaring class', function () {
        $discovery = new PostMetaDiscovery();

        discoverFixture($discovery, Product::class);
        $discovery->apply();

        $callback = registeredMeta()['sku']['sanitize_callback'];

        expect($callback)->toBe([Product::class, 'sanitizeSku']);
        expect($callback('abc-1'))->toBe('ABC-1');
    });

    it('sets no sanitizer when none was named', function () {
        $discovery = new PostMetaDiscovery();

        discoverFixture($discovery, Product::class);
        $discovery->apply();

        expect(registeredMeta()['price'])->not->toHaveKey('sanitize_callback');
    });
});
