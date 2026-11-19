<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\DiscoveryProblems;
use Studiometa\Foehn\Discovery\PostMetaDiscovery;
use Studiometa\Foehn\PostTypes\PostMetaBox;
use Tests\Fixtures\PostMeta\Event;
use Tests\Fixtures\PostMeta\EventVenue;
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
        $discovery = new PostMetaDiscovery(new DiscoveryProblems());

        discoverFixture($discovery, Product::class);
        $discovery->apply();

        expect(array_keys(registeredMeta()))->toBe(['price', 'sku', 'gallery']);
    });

    it('registers nothing when nothing was discovered', function () {
        new PostMetaDiscovery(new DiscoveryProblems())->apply();

        expect(wp_stub_get_calls('register_meta'))->toBeEmpty();
    });

    it('passes the type, the subtype and the description through', function () {
        $discovery = new PostMetaDiscovery(new DiscoveryProblems());

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
        $discovery = new PostMetaDiscovery(new DiscoveryProblems());

        discoverFixture($discovery, Product::class);
        $discovery->apply();

        // Without REST the field is invisible to the editor and to bindings,
        // which is the whole reason to declare it.
        expect(registeredMeta()['price']['show_in_rest'])->toBeTrue();
        expect(registeredMeta()['sku']['show_in_rest'])->toBeFalse();
    });

    it('passes an array schema through as show_in_rest', function () {
        $discovery = new PostMetaDiscovery(new DiscoveryProblems());

        discoverFixture($discovery, Gallery::class);
        $discovery->apply();

        expect(registeredMeta()['credits']['show_in_rest'])->toBe(['schema' => ['items' => ['type' => 'string']]]);
    });

    it('registers a repeatable key as not single', function () {
        $discovery = new PostMetaDiscovery(new DiscoveryProblems());

        discoverFixture($discovery, Product::class);
        $discovery->apply();

        expect(registeredMeta()['gallery']['single'])->toBeFalse();
        expect(registeredMeta()['gallery']['default'])->toBe(0);
    });

    it('leaves out a default that was never declared', function () {
        $discovery = new PostMetaDiscovery(new DiscoveryProblems());

        discoverFixture($discovery, Product::class);
        $discovery->apply();

        // register_meta() distinguishes "no default" from "a default of null":
        // the second makes the key appear in REST with a null value.
        expect(registeredMeta()['price'])->not->toHaveKey('default');
    });

    it('builds the auth callback from the declared capability', function () {
        $discovery = new PostMetaDiscovery(new DiscoveryProblems());

        discoverFixture($discovery, Product::class);
        $discovery->apply();

        registeredMeta()['price']['auth_callback']();

        expect(wp_stub_get_calls('current_user_can')[0]['args']['capability'])->toBe('edit_posts');
    });

    it('resolves the sanitizer to a method on the declaring class', function () {
        $discovery = new PostMetaDiscovery(new DiscoveryProblems());

        discoverFixture($discovery, Product::class);
        $discovery->apply();

        $callback = registeredMeta()['sku']['sanitize_callback'];

        expect($callback)->toBe([Product::class, 'sanitizeSku']);
        expect($callback('abc-1'))->toBe('ABC-1');
    });

    it('sets no sanitizer when none was named', function () {
        $discovery = new PostMetaDiscovery(new DiscoveryProblems());

        discoverFixture($discovery, Product::class);
        $discovery->apply();

        expect(registeredMeta()['price'])->not->toHaveKey('sanitize_callback');
    });

    it('adds no meta box when no key has a control', function () {
        $discovery = new PostMetaDiscovery(new DiscoveryProblems());

        discoverFixture($discovery, Product::class);
        $discovery->apply();

        expect(wp_stub_get_calls('add_action'))->toBeEmpty();
    });

    it('adds the meta box and saves it on the hooks of its post type', function () {
        $GLOBALS['wp_stub_user_can'] = ['edit_posts' => true, 'edit_post' => true];
        $discovery = new PostMetaDiscovery(new DiscoveryProblems());

        discoverFixture($discovery, Event::class);
        $discovery->apply();

        $hooks = array_column(array_column(wp_stub_get_calls('add_action'), 'args'), 'callback', 'hook');

        expect(array_keys($hooks))->toBe(['add_meta_boxes_event', 'save_post_event']);

        $hooks['add_meta_boxes_event']();

        expect(wp_stub_get_calls('add_meta_box')[0]['args']['screen'])->toBe('event');

        $_POST = [
            PostMetaBox::NONCE => wp_create_nonce(PostMetaBox::ID),
            PostMetaBox::FIELD => ['subtitle' => 'Saved', 'internal' => 'not a field'],
        ];

        try {
            $hooks['save_post_event'](12);
        } finally {
            $_POST = [];
        }

        // A key with no control is no field, even when a request names it.
        expect($GLOBALS['wp_stub_post_meta'][12])->toBe(['subtitle' => 'Saved']);
    });

    it('adds one meta box for the fields of every class on a post type', function () {
        $GLOBALS['wp_stub_user_can'] = ['edit_posts' => true, 'edit_post' => true];
        $discovery = new PostMetaDiscovery(new DiscoveryProblems());

        discoverFixture($discovery, Event::class);
        discoverFixture($discovery, EventVenue::class);
        $discovery->apply();

        $actions = array_column(wp_stub_get_calls('add_action'), 'args');

        // Two boxes would share the foehn-post-meta id, and the second would replace the first.
        expect(array_column($actions, 'hook'))->toBe(['add_meta_boxes_event', 'save_post_event']);

        $hooks = array_column($actions, 'callback', 'hook');
        $hooks['add_meta_boxes_event']();

        expect(wp_stub_get_calls('add_meta_box'))->toHaveCount(1);

        $_POST = [
            PostMetaBox::NONCE => wp_create_nonce(PostMetaBox::ID),
            PostMetaBox::FIELD => ['subtitle' => 'Opening night', 'venue' => 'Main hall'],
        ];

        try {
            $hooks['save_post_event'](12);
        } finally {
            $_POST = [];
        }

        expect($GLOBALS['wp_stub_post_meta'][12])->toBe(['subtitle' => 'Opening night', 'venue' => 'Main hall']);
    });
});
