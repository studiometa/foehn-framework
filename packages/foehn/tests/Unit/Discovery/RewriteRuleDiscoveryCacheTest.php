<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsRewriteRule;
use Studiometa\Foehn\Discovery\RewriteRuleDiscovery;
use Tempest\Container\GenericContainer;
use Tests\Fixtures\RewriteRuleFixture;

beforeEach(function () {
    wp_stub_reset();

    RewriteRuleFixture::$handled = 0;

    $this->container = new GenericContainer();
    $this->location = testDiscoveryLocation();
    $this->discovery = new RewriteRuleDiscovery($this->container);
});

describe('RewriteRuleDiscovery caching', function () {
    it('keeps the item under its location', function () {
        discoverFixture($this->discovery, RewriteRuleFixture::class, $this->location);

        expect($this->discovery->getItems()->getForLocation($this->location))->toHaveCount(1);
    });

    it('restores the item unchanged through a cache file', function () {
        discoverFixture($this->discovery, RewriteRuleFixture::class, $this->location);

        $restored = restoreThroughCacheFile($this->discovery, new RewriteRuleDiscovery($this->container));

        expect(iterator_to_array($restored->getItems()))->toEqual(iterator_to_array($this->discovery->getItems()));
    });

    it('registers and dispatches from the cache without reflecting the class', function () {
        discoverFixture($this->discovery, RewriteRuleFixture::class, $this->location);

        $restored = restoreThroughCacheFile($this->discovery, new RewriteRuleDiscovery($this->container));
        $restored->apply();

        expect(wp_stub_get_calls('add_rewrite_rule'))->toHaveCount(1);

        // The match is on the item, not re-parsed: what the cache holds has to
        // be enough to answer a request.
        $wp = new WP();
        $wp->query_vars = ['foehn_route' => 'stripe-webhook'];

        firstCallbackFor('add_action', 'parse_request')($wp);

        expect(RewriteRuleFixture::$handled)->toBe(1);
    });

    it('restores the attribute as an instance, not an array', function () {
        discoverFixture($this->discovery, RewriteRuleFixture::class, $this->location);

        $item = restoreThroughCacheFile($this->discovery, new RewriteRuleDiscovery($this->container))
            ->getItems()
            ->getForLocation($this->location)[0];

        expect($item['attribute'])->toBeInstanceOf(AsRewriteRule::class);
        expect($item['attribute']->queryVars)->toBe(['foehn_route']);
    });
});
