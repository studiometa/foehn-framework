<?php

declare(strict_types=1);

use Studiometa\Foehn\Contracts\Arrayable;
use Studiometa\Foehn\JsonLd\JsonLd;

beforeEach(function () {
    JsonLd::flush();
});

describe('JsonLd', function () {
    it('collects the nodes that blocks add, in order', function () {
        JsonLd::add(['@type' => 'FAQPage', '@id' => 'http://example.com/#faq']);
        JsonLd::add(['@type' => 'Recipe', '@id' => 'http://example.com/#recipe']);

        expect(array_column(JsonLd::flush(), '@type'))->toBe(['FAQPage', 'Recipe']);
    });

    it('empties the list when it gives the nodes', function () {
        JsonLd::add(['@type' => 'FAQPage']);
        JsonLd::flush();

        expect(JsonLd::flush())->toBe([]);
    });

    it('turns a JsonSerializable object into an array', function () {
        $node = new class implements JsonSerializable {
            public function jsonSerialize(): array
            {
                return ['@type' => 'Event'];
            }
        };

        expect(JsonLd::normalize($node))->toBe(['@type' => 'Event']);
    });

    it('turns an object with toArray() into an array', function () {
        $node = new class implements Arrayable {
            public function toArray(): array
            {
                return ['@type' => 'Recipe'];
            }
        };

        expect(JsonLd::normalize($node))->toBe(['@type' => 'Recipe']);
    });

    it('removes the @context of a node, which the graph holds once', function () {
        expect(JsonLd::normalize(['@context' => 'https://schema.org', '@type' => 'Event']))->toBe(['@type' => 'Event']);
    });

    it('refuses an object it cannot turn into an array', function () {
        JsonLd::add(new stdClass());
    })->throws(InvalidArgumentException::class, 'got stdClass');

    it('refuses a JsonSerializable object that does not serialize to an array', function () {
        JsonLd::normalize(new class implements JsonSerializable {
            public function jsonSerialize(): string
            {
                return 'Event';
            }
        });
    })->throws(InvalidArgumentException::class);

    it('prints no script for an empty graph', function () {
        expect(JsonLd::script([]))->toBe('');
    });

    it('prints one script with the context and the graph', function () {
        $script = JsonLd::script([['@type' => 'Event', 'url' => 'http://example.com/é']]);

        expect($script)->toBe(
            '<script type="application/ld+json" class="foehn-json-ld">'
            . '{"@context":"https://schema.org","@graph":[{"@type":"Event","url":"http://example.com/é"}]}'
            . "</script>\n",
        );
    });

    it('escapes a closing script tag in a value', function () {
        $script = JsonLd::script([['@type' => 'Event', 'name' => '</script><script>alert(1)']]);

        expect(substr_count($script, '</script>'))->toBe(1);
        expect($script)->toContain('\\u003C/script\\u003E');

        preg_match('#<script[^>]*>(.*)</script>#s', $script, $matches);
        expect(json_decode($matches[1], true)['@graph'][0]['name'])->toBe('</script><script>alert(1)');
    });
});
