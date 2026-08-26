<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\PageCacheConfig;
use Studiometa\Foehn\PageCache\CacheKey;
use Studiometa\Foehn\PageCache\QueryKey;

/**
 * The query string half of the key.
 *
 * Every test here is really the same test: PHP and the generated nginx snippet have to
 * pick one filename for one page. `ServerConfigTest` checks the snippet against these
 * answers; this file pins the answers.
 */

beforeEach(function () {
    wp_stub_reset();

    $this->config = new PageCacheConfig(cacheQueryArgs: [
        'page' => '^[0-9]{1,6}$',
        'lang' => '^[a-z]{2}$',
    ], ignoredQueryArgs: ['utm_source', 'gclid']);
});

describe('reserved controls', function () {
    it('always bypasses section requests, even when project config tries to cache them', function () {
        $config = new PageCacheConfig(ignoredQueryArgs: ['sections'], cacheQueryArgs: ['sections']);

        expect(QueryKey::canonical('sections=results', $config))->toBeNull();
        expect(QueryKey::canonical('utm_source=test&sections=results', $config))->toBeNull();
    });
});

describe('canonical order', function () {
    it('gives one answer whatever order the args arrive in', function (string $query) {
        // The property the whole feature was asked for. ?page=2&lang=fr and
        // ?lang=fr&page=2 are one page, so they are one file — and no reader sorts
        // anything: they all walk the configured names in the configured order.
        expect(QueryKey::canonical($query, $this->config))->toBe('lang=fr&page=2&');
    })->with([
        ['page=2&lang=fr'],
        ['lang=fr&page=2'],
        ['page=2&utm_source=newsletter&lang=fr'],
        ['utm_source=newsletter&lang=fr&gclid=x&page=2'],
    ]);

    it('names the file after the args, in that same order', function () {
        $first = CacheKey::create('example.test', '/blog/?page=2&lang=fr', 'lang=fr&page=2&');
        $second = CacheKey::create('example.test', '/blog/?lang=fr&page=2', 'lang=fr&page=2&');

        expect($first?->relativePath())
            ->toBe('example.test/blog/index__lang=fr&page=2&.html')
            ->and($second?->relativePath())
            ->toBe($first?->relativePath());
    });

    it('keeps the trailing separator, because nginx cannot trim a variable', function () {
        expect(QueryKey::canonical('page=2', $this->config))->toBe('page=2&');
    });
});

describe('args that leave the key alone', function () {
    it('keys an ignored arg as no query at all', function (string $query) {
        expect(QueryKey::canonical($query, $this->config))->toBe('');
    })->with([
        [''],
        ['utm_source=newsletter'],
        ['gclid=x&utm_source=y'],
        ['utm_source'],
        // An empty value counts as absent, in every reader. nginx's $arg_page is empty
        // here too and matches no pattern, so it builds no variant either.
        ['page='],
    ]);
});

describe('args that bypass', function () {
    it('refuses a query string it was not told about', function (string $query) {
        expect(QueryKey::canonical($query, $this->config))->toBeNull();
    })->with([
        ['foo=bar'],
        ['page=2&foo=bar'],
        ['s=hello'],
        // The boundary that stops `page` matching the front of `pagex`.
        ['pagex=2'],
    ]);

    it('refuses a keyed arg that appears twice', function (string $query) {
        // nginx reads the first `page=`, PHP the last. There is no answer both would
        // give, so neither gives one — including when the first one is empty, which
        // would otherwise have nginx serve the unpaginated page.
        expect(QueryKey::canonical($query, $this->config))->toBeNull();
    })->with([
        ['page=1&page=2'],
        ['page=&page=2'],
        ['page=2&lang=fr&page=3'],
    ]);

    it('refuses a value its pattern does not allow', function (string $query) {
        expect(QueryKey::canonical($query, $this->config))->toBeNull();
    })->with([
        ['page=abc'],
        ['page=1234567'],
        ['lang=FR'],
        ['lang=french'],
    ]);

    it('refuses a value that would leave the cache directory', function (string $query) {
        // Rejected twice over: by the arg's own pattern, and by the charset every value
        // must pass whatever pattern a project writes.
        expect(QueryKey::canonical($query, $this->config))->toBeNull();
    })->with([
        ['page=../../etc/passwd'],
        ['page=%2e%2e%2f'],
        ['lang=a/b'],
    ]);

    it('refuses a value a project pattern would have allowed but a filename may not', function () {
        // A config file can narrow what reaches a filename, never widen it.
        $config = new PageCacheConfig(cacheQueryArgs: ['lang' => '^.+$']);

        expect(QueryKey::canonical('lang=fr', $config))
            ->toBe('lang=fr&')
            ->and(QueryKey::canonical('lang=a/b', $config))
            ->toBeNull()
            ->and(QueryKey::canonical('lang=' . str_repeat('a', 65), $config))
            ->toBeNull();
    });
});

describe('configuration', function () {
    it('sorts the keyed args, because that order names every stored file', function () {
        $config = new PageCacheConfig(cacheQueryArgs: ['page' => '^\d+$', 'lang' => '^[a-z]{2}$']);

        expect(array_keys($config->getCacheQueryArgs()))->toBe(['lang', 'page']);
    });

    it('gives a shorthand list the default pattern', function () {
        expect(new PageCacheConfig(cacheQueryArgs: ['page'])->getCacheQueryArgs())->toBe([
            'page' => PageCacheConfig::DEFAULT_QUERY_ARG_PATTERN,
        ]);
    });

    it('drops an entry it could not honour, so it becomes an arg nobody configured', function () {
        $config = new PageCacheConfig(cacheQueryArgs: [
            'ok' => '^[a-z]+$',
            'no spaces' => '^[a-z]+$',
            'broken' => '^([a-z$',
            'hashed' => '^a#b$',
        ]);

        expect(array_keys($config->getCacheQueryArgs()))->toBe(['ok']);
        // And an unconfigured arg is a bypass, never a silent unkeyed hit.
        expect(QueryKey::canonical('broken=x', $config))->toBeNull();
    });

    it('lets the keyed meaning win when a name is in both lists', function () {
        // A contradiction in a config file. Dropping the arg would serve one page's HTML
        // for another, so the specific meaning wins and the writer and the snippets are
        // told the same thing.
        $config = new PageCacheConfig(cacheQueryArgs: ['ref' => '^[a-z]+$'], ignoredQueryArgs: ['ref', 'gclid']);

        expect($config->getIgnoredQueryArgs())
            ->toBe(['gclid'])
            ->and(QueryKey::canonical('ref=abc', $config))
            ->toBe('ref=abc&');
    });
});
