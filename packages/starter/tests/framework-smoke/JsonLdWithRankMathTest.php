<?php

declare(strict_types=1);

use Studiometa\Foehn\Smoke\Support\Client;
use Studiometa\Foehn\Smoke\Support\Site;

/**
 * The JSON-LD of a page with Rank Math: the nodes of the starter's PostJsonLd go in the
 * graph of Rank Math, and the nodes of blocks in a second script in the footer.
 *
 * The plugin is installed with Composer, active for this file only and uninstalled
 * afterwards (see smokeActivatePlugin()).
 */

beforeAll(function () {
    if (!Site::isRunning()) {
        return;
    }

    smokeActivatePlugin('seo-by-rank-math');
    // Rank Math loads its front end only once the registration is done or skipped.
    Site::wp('wp option update rank_math_registration_skip 1');
});

afterAll(function () {
    if (!Site::isRunning()) {
        return;
    }

    smokeUninstallPlugin('seo-by-rank-math');
    Site::wp('wp option delete rank_math_registration_skip');
});

beforeEach(function () {
    Site::isRunning() || $this->markTestSkipped('ddev is not running — run `ddev start` and try again.');
});

it('adds the nodes of the post to the graph of Rank Math, and prints no graph of its own', function () {
    $url = smokeJsonLdPostUrl();
    $scripts = smokeJsonLd(Client::get($url)->body);

    expect(array_column($scripts, 'class'))->toBe(['rank-math-schema']);

    $types = smokeJsonLdTypes($scripts[0]['json']);

    expect($types)->toContain('WebPage', 'BlogPosting');

    $ids = array_column($scripts[0]['json']['@graph'], '@id');

    expect($ids)->toContain($url . '#article', $url . '#webpage');
});

it('prints its own graph in <head> while Rank Math does not load its front end', function () {
    // The Schema module is on, but Rank Math loads no front end before its registration
    // is done or skipped, so it prints no graph.
    Site::wp('wp option delete rank_math_registration_skip');

    try {
        $body = Client::get(smokeJsonLdPostUrl() . '?smoke-json-ld-unregistered=1')->body;
    } finally {
        Site::wp('wp option update rank_math_registration_skip 1');
    }

    $scripts = smokeJsonLd($body);

    expect(array_column($scripts, 'class'))->toBe(['foehn-json-ld']);
    expect($scripts[0]['offset'])->toBeLessThan((int) strpos($body, '</head>'));
    expect(smokeJsonLdTypes($scripts[0]['json']))->toContain('BlogPosting');
});

it('prints the nodes that a block adds in a second script, in the footer', function () {
    $body = smokeJsonLdWithBlock(smokeJsonLdPostUrl());
    $scripts = smokeJsonLd($body);

    expect(array_column($scripts, 'class'))->toBe(['rank-math-schema', 'foehn-json-ld']);
    expect($scripts[1]['offset'])->toBeGreaterThan((int) strpos($body, '</head>'));
    expect(smokeJsonLdTypes($scripts[1]['json']))->toBe(['FAQPage']);
});
