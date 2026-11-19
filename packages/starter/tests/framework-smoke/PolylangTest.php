<?php

declare(strict_types=1);

use Studiometa\Foehn\Smoke\Support\Site;

/**
 * Føhn with Polylang active: the language switcher, the purge of the translations of a
 * post, and a translatable setting.
 *
 * Polylang comes from Composer, not from the starter, which does not ship it. Install it
 * in the starter before the suite, as CI does:
 * `composer require --working-dir=packages/starter wpackagist-plugin/polylang:^3.9`.
 * This file activates it and deactivates it afterwards. The URLs use the default mode of
 * Polylang: the language in a folder, none for the default language.
 */

const POLYLANG = 'wp eval-file tests/framework-smoke/fixtures/polylang.php';

beforeAll(function () {
    if (!Site::isRunning()) {
        return;
    }

    Site::wp('wp plugin activate polylang') ?? throw new RuntimeException(
        'Polylang is not installed in the starter. Run `composer require --working-dir=packages/starter wpackagist-plugin/polylang:^3.9` first.',
    );
    Site::wp(POLYLANG . ' setup');
    Site::wp('wp rewrite flush');
    Site::enableCache();
});

afterAll(function () {
    if (!Site::isRunning()) {
        return;
    }

    Site::wp(POLYLANG . ' teardown');
    Site::wp('wp plugin deactivate polylang');
    Site::wp('wp rewrite flush');
    Site::disableCache();
    Site::clearCache();
});

beforeEach(function () {
    Site::isRunning() || $this->markTestSkipped('ddev is not running — run `ddev start` and try again.');

    Site::clearCache();
});

it('gives each language with the URL of the translation of the page', function () {
    $switcher = json_decode((string) Site::wp(POLYLANG . ' switcher name=hello-world'), true);

    expect(array_column($switcher ?? [], 'code'))->toBe(['en', 'fr']);
    expect($switcher[0]['url'])->toEndWith('/hello-world/');
    expect($switcher[1]['url'])->toEndWith('/fr/bonjour/');
    expect(array_column($switcher, 'translated'))->toBe([true, true]);
});

it('links a language without a translation to its home page', function () {
    $switcher = json_decode((string) Site::wp(POLYLANG . ' switcher pagename=sample-page'), true);

    expect($switcher[1]['url'] ?? null)->toEndWith('/fr/');
    expect($switcher[1]['translated'] ?? null)->toBeFalse();
});

it('purges the translations of a saved post', function () {
    smokeWarm('/fr/bonjour/');
    expect(is_file(Site::cacheFile('/fr/bonjour/')))->toBeTrue('nothing was stored to purge');

    $id = (string) Site::wp(POLYLANG . ' setup');
    Site::wp('wp post update ' . $id . ' --post_title="Hello world!"');

    expect(is_file(Site::cacheFile('/fr/bonjour/')))->toBeFalse('the French translation is still stored');
});

it('purges the home page of the language of an unpublished post', function () {
    smokeWarm('/fr/');
    expect(is_file(Site::cacheFile('/fr/')))->toBeTrue('nothing was stored to purge');

    $id = (string) Site::wp('wp post list --post_type=post --name=bonjour --field=ID');
    Site::wp('wp post update ' . $id . ' --post_status=draft');

    try {
        expect(is_file(Site::cacheFile('/fr/')))->toBeFalse('the French home page is still stored');
    } finally {
        Site::wp('wp post update ' . $id . ' --post_status=publish');
    }
});

it('reads a translatable setting in each language, and the default one for an empty translation', function () {
    expect(Site::wp(POLYLANG . ' setting en Bonjour'))->toBe('Hello');
    expect(Site::wp(POLYLANG . ' setting fr Bonjour'))->toBe('Bonjour');
    expect(Site::wp(POLYLANG . " setting fr ''"))->toBe('Hello');
});
