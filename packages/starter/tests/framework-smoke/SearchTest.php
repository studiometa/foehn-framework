<?php

declare(strict_types=1);

use Studiometa\Foehn\Smoke\Support\Site;

/**
 * studiometa/foehn-search in a real WordPress, with Polylang: a typo still finds the
 * post, a category filter and its counts work on the search page, each language
 * searches its own index, and a save reaches the index.
 *
 * The starter does not ship the package: CI adds it for this suite. Without it, or
 * without ddev, every test is skipped. Polylang is installed from wordpress.org for
 * this file and removed afterwards.
 */

const SEARCH = 'wp eval-file tests/framework-smoke/fixtures/search.php';

/**
 * The main search query of a language, as the fixture prints it.
 *
 * @return array{titles: list<string>, counts: array<string, int>}
 */
function smokeSearch(string $language, string $s, string $category = ''): array
{
    $output = (string) Site::wp(SEARCH . ' search ' . $language . ' ' . escapeshellarg($s) . ' ' . $category);

    return json_decode($output, true) ?? ['titles' => [], 'counts' => [], 'output' => $output];
}

beforeAll(function () {
    if (!Site::isRunning()) {
        return;
    }

    Site::wp('wp plugin install polylang --activate');

    if (Site::wp(SEARCH . ' installed') === '1') {
        Site::wp(SEARCH . ' setup');
    }
});

afterAll(function () {
    if (!Site::isRunning()) {
        return;
    }

    if (Site::wp(SEARCH . ' installed') === '1') {
        Site::wp(SEARCH . ' teardown');
    }

    Site::wp('wp plugin uninstall polylang --deactivate');
    Site::wp('wp rewrite flush');
});

beforeEach(function () {
    Site::isRunning() || $this->markTestSkipped('ddev is not running — run `ddev start` and try again.');
    Site::wp(SEARCH . ' installed') === '1'
        || $this->markTestSkipped('studiometa/foehn-search is not installed in the starter.');
});

it('finds a post with a typo in the query', function () {
    expect(smokeSearch('en', 'sourdouhg')['titles'])->toContain('Smoke sourdough starter', 'Smoke sourdough crackers');
});

it('filters the results by category and counts each category with its own filter lifted', function () {
    $result = smokeSearch('en', 'sourdough', 'smoke-bread');

    expect($result['titles'])->toBe(['Smoke sourdough starter']);
    expect($result['counts'])->toMatchArray(['smoke-bread' => 1, 'smoke-snacks' => 1]);
});

it('searches the index of each language', function () {
    expect(smokeSearch('fr', 'levain')['titles'])->toBe(['Smoke levain naturel']);
    expect(smokeSearch('en', 'levain')['titles'])->toBe([]);
});

it('gives an empty search no result', function () {
    expect(smokeSearch('en', '')['titles'])->toBe([]);
});

it('indexes a post again when it is saved', function () {
    Site::wp(SEARCH . " rename smoke-search-crackers 'Smoke rye crackers'");

    expect(smokeSearch('en', 'rye')['titles'])->toBe(['Smoke rye crackers']);
});
