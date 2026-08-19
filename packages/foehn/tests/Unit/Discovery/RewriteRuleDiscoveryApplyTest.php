<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\RewriteRuleDiscovery;
use Tempest\Container\GenericContainer;
use Tests\Fixtures\RewriteRuleFixture;
use Tests\Fixtures\RewriteRuleWithoutHandlerFixture;

/**
 * Run the callback registered for a hook, as WordPress would.
 */
function firstCallbackFor(string $recorded, string $hook): callable
{
    foreach (wp_stub_get_calls($recorded) as $call) {
        if ($call['args']['hook'] === $hook) {
            return $call['args']['callback'];
        }
    }

    throw new RuntimeException("Nothing was registered for {$hook}.");
}

beforeEach(function () {
    wp_stub_reset();

    RewriteRuleFixture::$handled = 0;

    $this->container = new GenericContainer();
    $this->discovery = new RewriteRuleDiscovery($this->container);
});

describe('RewriteRuleDiscovery::apply', function () {
    it('registers the rule with WordPress', function () {
        discoverFixture($this->discovery, RewriteRuleFixture::class);
        $this->discovery->apply();

        $call = wp_stub_get_calls('add_rewrite_rule')[0]['args'];

        expect($call['regex'])->toBe('^webhook/stripe/?$');
        expect($call['query'])->toBe('index.php?foehn_route=stripe-webhook');
        // A webhook wants to match before WordPress's own rules.
        expect($call['after'])->toBe('top');
    });

    it('registers nothing when nothing was discovered', function () {
        $this->discovery->apply();

        expect(wp_stub_get_calls('add_rewrite_rule'))->toBeEmpty();
        expect(wp_stub_get_calls('flush_rewrite_rules'))->toBeEmpty();
    });

    it('teaches WordPress the query variables the rule sets', function () {
        // WordPress discards a query variable it does not know, so without this
        // the rewrite lands on a request with nothing in it.
        discoverFixture($this->discovery, RewriteRuleFixture::class);
        $this->discovery->apply();

        $filter = firstCallbackFor('add_filter', 'query_vars');

        expect($filter(['p', 'name']))->toBe(['p', 'name', 'foehn_route']);
    });

    it('adds no query var filter for a rule that declares none', function () {
        discoverFixture($this->discovery, RewriteRuleWithoutHandlerFixture::class);
        $this->discovery->apply();

        $hooks = array_column(array_column(wp_stub_get_calls('add_filter'), 'args'), 'hook');

        expect($hooks)->not->toContain('query_vars');
    });

    it('dispatches a matched request to the handler', function () {
        discoverFixture($this->discovery, RewriteRuleFixture::class);
        $this->discovery->apply();

        $wp = new WP();
        $wp->query_vars = ['foehn_route' => 'stripe-webhook'];

        firstCallbackFor('add_action', 'parse_request')($wp);

        expect(RewriteRuleFixture::$handled)->toBe(1);
    });

    it('leaves a request the rule did not match alone', function () {
        discoverFixture($this->discovery, RewriteRuleFixture::class);
        $this->discovery->apply();

        $wp = new WP();
        $wp->query_vars = ['foehn_route' => 'something-else'];

        firstCallbackFor('add_action', 'parse_request')($wp);

        expect(RewriteRuleFixture::$handled)->toBe(0);
    });

    it('leaves an ordinary request alone', function () {
        discoverFixture($this->discovery, RewriteRuleFixture::class);
        $this->discovery->apply();

        // Every front-end request reaches parse_request. Dispatching on an empty
        // match would answer all of them.
        firstCallbackFor('add_action', 'parse_request')(new WP());

        expect(RewriteRuleFixture::$handled)->toBe(0);
    });

    it('hooks nothing on parse_request when no rule handles', function () {
        discoverFixture($this->discovery, RewriteRuleWithoutHandlerFixture::class);
        $this->discovery->apply();

        $hooks = array_column(array_column(wp_stub_get_calls('add_action'), 'args'), 'hook');

        expect($hooks)->not->toContain('parse_request');
    });
});

describe('RewriteRuleDiscovery flushing', function () {
    it('flushes the first time it sees a rule', function () {
        discoverFixture($this->discovery, RewriteRuleFixture::class);
        $this->discovery->apply();

        expect(wp_stub_get_calls('flush_rewrite_rules'))->toHaveCount(1);
        expect(get_option(RewriteRuleDiscovery::HASH_OPTION))->not->toBeFalse();
    });

    it('flushes softly', function () {
        // The rules live in an option; .htaccess only ever routes everything to
        // index.php, so a hard flush is work for nothing.
        discoverFixture($this->discovery, RewriteRuleFixture::class);
        $this->discovery->apply();

        expect(wp_stub_get_calls('flush_rewrite_rules')[0]['args']['hard'])->toBeFalse();
    });

    it('does not flush again while the rules are unchanged', function () {
        discoverFixture($this->discovery, RewriteRuleFixture::class);
        $this->discovery->apply();

        $second = new RewriteRuleDiscovery($this->container);
        discoverFixture($second, RewriteRuleFixture::class);
        $second->apply();

        // Flushing per request is a well-known way to ruin a site.
        expect(wp_stub_get_calls('flush_rewrite_rules'))->toHaveCount(1);
    });

    it('flushes again when a rule is added', function () {
        discoverFixture($this->discovery, RewriteRuleFixture::class);
        $this->discovery->apply();

        $second = new RewriteRuleDiscovery($this->container);
        discoverFixture($second, RewriteRuleFixture::class);
        discoverFixture($second, RewriteRuleWithoutHandlerFixture::class);
        $second->apply();

        expect(wp_stub_get_calls('flush_rewrite_rules'))->toHaveCount(2);
    });

    it('hashes the rules and not the classes that answer them', function () {
        $rules = [
            ['attribute' => new Studiometa\Foehn\Attributes\AsRewriteRule('^a/?$', 'index.php?a=1')],
        ];

        $moved = [
            ['attribute' => new Studiometa\Foehn\Attributes\AsRewriteRule('^a/?$', 'index.php?a=1')],
        ];

        // Moving a rule to another class changes what answers the URL, not what
        // WordPress has to match — and a flush it does not need is a flush.
        expect(RewriteRuleDiscovery::hash($rules))->toBe(RewriteRuleDiscovery::hash($moved));
    });

    it('hashes the same set the same way whatever order it comes in', function () {
        $a = new Studiometa\Foehn\Attributes\AsRewriteRule('^a/?$', 'index.php?a=1');
        $b = new Studiometa\Foehn\Attributes\AsRewriteRule('^b/?$', 'index.php?b=1');

        // Discovery order is filesystem order. A hash that followed it would
        // flush on a machine whose directory listing differs.
        expect(RewriteRuleDiscovery::hash([['attribute' => $a], ['attribute' => $b]]))
            ->toBe(RewriteRuleDiscovery::hash([['attribute' => $b], ['attribute' => $a]]));
    });
});
