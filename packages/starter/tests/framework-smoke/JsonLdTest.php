<?php

declare(strict_types=1);

use Studiometa\Foehn\Smoke\Support\Client;
use Studiometa\Foehn\Smoke\Support\Site;

/**
 * The JSON-LD of a page without an SEO plugin: the starter's PostJsonLd gives each post a
 * `BlogPosting` node, which Føhn prints in `<head>`.
 */

beforeEach(function () {
    Site::isRunning() || $this->markTestSkipped('ddev is not running — run `ddev start` and try again.');
});

it('prints the graph of a post in <head>, as valid JSON', function () {
    $url = smokeJsonLdPostUrl();
    $body = Client::get($url)->body;
    $scripts = smokeJsonLd($body);

    expect($scripts)->toHaveCount(1);
    expect($scripts[0]['class'])->toBe('foehn-json-ld');
    expect($scripts[0]['offset'])->toBeLessThan((int) strpos($body, '</head>'));
    expect($scripts[0]['json']['@context'])->toBe('https://schema.org');
    expect(smokeJsonLdTypes($scripts[0]['json']))->toBe(['BlogPosting']);
    expect($scripts[0]['json']['@graph'][0]['@id'])->toBe($url . '#article');
    expect($scripts[0]['json']['@graph'][0]['headline'])->toBe('Hello world!');
});

it('prints no graph on a page of another post type', function () {
    expect(smokeJsonLd(Client::get((string) Site::wp(
        'wp post list --post_type=page --name=sample-page --field=url',
    ))->body))->toBe([]);
});

it('prints the nodes that a block adds in the footer', function () {
    $body = smokeJsonLdWithBlock(smokeJsonLdPostUrl());
    $scripts = smokeJsonLd($body);

    expect(array_column($scripts, 'class'))->toBe(['foehn-json-ld', 'foehn-json-ld']);
    expect($scripts[1]['offset'])->toBeGreaterThan((int) strpos($body, '</head>'));
    expect(smokeJsonLdTypes($scripts[1]['json']))->toBe(['FAQPage']);
    expect($scripts[1]['json']['@graph'][0]['mainEntity'][0]['name'])->toBe('Does </script> close the script?');
});
