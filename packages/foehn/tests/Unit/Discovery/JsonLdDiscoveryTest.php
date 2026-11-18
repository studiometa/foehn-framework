<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\DiscoveryProblems;
use Studiometa\Foehn\Discovery\JsonLdDiscovery;
use Studiometa\Foehn\JsonLd\JsonLd;
use Tempest\Reflection\ClassReflector;
use Tests\Fixtures\InvalidJsonLdFixture;
use Tests\Fixtures\JsonLdFixture;
use Tests\Fixtures\NoAttributeFixture;

/**
 * A discovery that found JsonLdFixture, applied.
 */
function appliedJsonLdDiscovery(): JsonLdDiscovery
{
    $discovery = new JsonLdDiscovery(new DiscoveryProblems());
    $discovery->discover(testDiscoveryLocation(), new ClassReflector(JsonLdFixture::class));
    $discovery->apply();

    return $discovery;
}

/**
 * Make the current request the single page of a post.
 */
function jsonLdSingle(string $postType, string $slug = 'concert'): void
{
    $post = new WP_Post();
    $post->post_type = $postType;
    $post->post_name = $slug;

    $GLOBALS['wp_stub_template'] = 'single';
    $GLOBALS['wp_stub_queried_object'] = $post;
}

/**
 * The hooks a WordPress function was called with, in order.
 *
 * @return list<string>
 */
function jsonLdHooks(string $function): array
{
    return array_map(static fn(array $call): string => $call['args']['hook'], wp_stub_get_calls($function));
}

/**
 * What a callback prints.
 */
function jsonLdOutput(callable $print): string
{
    ob_start();
    $print();

    return (string) ob_get_clean();
}

/**
 * The graph of the first Føhn script in some HTML.
 *
 * @return list<array<string, mixed>>|null
 */
function jsonLdGraph(string $html): ?array
{
    if (!preg_match('#<script type="application/ld\+json" class="foehn-json-ld">(.*?)</script>#s', $html, $matches)) {
        return null;
    }

    $json = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);

    expect($json['@context'])->toBe('https://schema.org');

    return $json['@graph'];
}

beforeEach(function () {
    wp_stub_reset();
    bootTestContainer();
    JsonLd::flush();
    unset($GLOBALS['wp_stub_queried_object']);
});

afterEach(function () {
    tearDownTestContainer();
    unset($GLOBALS['wp_stub_queried_object']);
});

describe('JsonLdDiscovery', function () {
    it('discovers #[AsJsonLd] classes', function () {
        $discovery = new JsonLdDiscovery(new DiscoveryProblems());
        $discovery->discover(testDiscoveryLocation(), new ClassReflector(JsonLdFixture::class));
        $discovery->discover(testDiscoveryLocation(), new ClassReflector(NoAttributeFixture::class));

        $items = iterator_to_array($discovery->getItems());

        expect($items)->toHaveCount(1);
        expect($items[0]['className'])->toBe(JsonLdFixture::class);
        expect($items[0]['attribute']->getPostTypes())->toBe(['event', 'place']);
    });

    it('reports a class that does not implement JsonLdProviderInterface, and skips it', function () {
        expect(rejectedProblems(
            fn(DiscoveryProblems $problems) => new JsonLdDiscovery($problems),
            InvalidJsonLdFixture::class,
        ))->toBe([[
            'class' => InvalidJsonLdFixture::class,
            'attribute' => '#[AsJsonLd]',
            'reason' =>
                InvalidJsonLdFixture::class
                    . ' has #[AsJsonLd] but does not implement Studiometa\\Foehn\\Contracts\\JsonLdProviderInterface, so it adds no JSON-LD.',
            'fix' =>
                'Declare '
                    . InvalidJsonLdFixture::class
                    . ' with `implements \\Studiometa\\Foehn\\Contracts\\JsonLdProviderInterface`.',
        ]]);
    });

    it('registers the page graph, the Rank Math merge and the footer graph', function () {
        appliedJsonLdDiscovery();

        expect(jsonLdHooks('add_filter'))->toBe(['rank_math/json_ld']);
        expect(jsonLdHooks('add_action'))->toBe(['wp_head', 'wp_footer']);
    });

    it('registers only the footer graph when no class gives page nodes', function () {
        new JsonLdDiscovery(new DiscoveryProblems())->apply();

        expect(wp_stub_get_calls('add_filter'))->toBe([]);
        expect(jsonLdHooks('add_action'))->toBe(['wp_footer']);
    });
});

describe('JsonLdDiscovery page nodes', function () {
    it('gives the nodes of the providers of the post type, without their @context', function () {
        jsonLdSingle('event');

        expect(appliedJsonLdDiscovery()->pageNodes())->toBe([
            [
                '@type' => 'Event',
                '@id' => 'http://example.com/concert/#event',
                'name' => 'Concert </script>',
            ],
            ['@type' => 'Place', '@id' => 'http://example.com/#place'],
        ]);
    });

    it('gives no node for another post type', function () {
        jsonLdSingle('post');

        expect(appliedJsonLdDiscovery()->pageNodes())->toBe([]);
    });

    it('gives no node outside a single page', function () {
        jsonLdSingle('event');
        $GLOBALS['wp_stub_template'] = 'archive';

        expect(appliedJsonLdDiscovery()->pageNodes())->toBe([]);
    });
});

describe('JsonLdDiscovery without an SEO plugin', function () {
    it('prints one script in <head> with the graph of the page', function () {
        jsonLdSingle('event');

        $html = jsonLdOutput(appliedJsonLdDiscovery()->printPageGraph(...));

        expect(substr_count($html, '<script'))->toBe(1);
        expect(array_column(jsonLdGraph($html) ?? [], '@type'))->toBe(['Event', 'Place']);
    });

    it('prints nothing for a page without nodes', function () {
        jsonLdSingle('post');

        expect(jsonLdOutput(appliedJsonLdDiscovery()->printPageGraph(...)))->toBe('');
    });

    it('prints its own script when the Schema module of Rank Math is off', function () {
        jsonLdSingle('event');
        $GLOBALS['rank_math_stub_modules'] = ['sitemap'];

        expect(jsonLdGraph(jsonLdOutput(appliedJsonLdDiscovery()->printPageGraph(...))))->toHaveCount(2);
    });
});

describe('JsonLdDiscovery with Rank Math', function () {
    beforeEach(function () {
        $GLOBALS['rank_math_stub_modules'] = ['rich-snippet'];
    });

    it('adds the nodes of the page to the graph of Rank Math', function () {
        jsonLdSingle('event');
        appliedJsonLdDiscovery();

        $data = apply_filters('rank_math/json_ld', [
            'WebPage' => ['@type' => 'WebPage', '@id' => 'http://example.com/concert/#webpage'],
        ]);

        // Rank Math prints the values of the array as its @graph.
        expect(array_column(array_values($data), '@type'))->toBe(['WebPage', 'Event', 'Place']);
        expect($data['WebPage']['@id'])->toBe('http://example.com/concert/#webpage');
    });

    it('leaves the graph of Rank Math as it is for a page without nodes', function () {
        jsonLdSingle('post');
        appliedJsonLdDiscovery();

        $graph = ['WebPage' => ['@type' => 'WebPage']];

        expect(apply_filters('rank_math/json_ld', $graph))->toBe($graph);
    });

    it('does not print a second graph in <head>', function () {
        jsonLdSingle('event');

        expect(jsonLdOutput(appliedJsonLdDiscovery()->printPageGraph(...)))->toBe('');
    });
});

describe('JsonLdDiscovery block nodes', function () {
    it('prints the nodes that blocks added in the footer, once', function () {
        $discovery = appliedJsonLdDiscovery();
        JsonLd::add(['@type' => 'FAQPage', '@id' => 'http://example.com/#faq']);

        expect(array_column(jsonLdGraph(jsonLdOutput($discovery->printBlockGraph(...))) ?? [], '@type'))->toBe([
            'FAQPage',
        ]);
        expect(jsonLdOutput($discovery->printBlockGraph(...)))->toBe('');
    });

    it('prints the footer graph with Rank Math too', function () {
        $GLOBALS['rank_math_stub_modules'] = ['rich-snippet'];
        JsonLd::add(['@type' => 'FAQPage']);

        expect(jsonLdGraph(jsonLdOutput(appliedJsonLdDiscovery()->printBlockGraph(...))))->toHaveCount(1);
    });
});
