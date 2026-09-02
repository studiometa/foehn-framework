<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\PageCacheConfig;
use Studiometa\Foehn\PageCache\CacheKey;
use Studiometa\Foehn\PageCache\QueryKey;
use Studiometa\Foehn\Views\Sections\SectionRequest;

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

describe('section requests', function () {
    it('keys a selection, so a fragment is a file of its own rather than a re-render', function () {
        expect(QueryKey::canonical('foehn_sections=listing,pagination', $this->config))
            ->toBe('foehn_sections=listing,pagination&');
        expect(QueryKey::canonical('foehn_sections=listing&page=2', $this->config))
            ->toBe('foehn_sections=listing&page=2&');
    });

    it('names the file after the selection', function () {
        expect(CacheKey::create('example.test', '/blog/', 'foehn_sections=listing,pagination&')?->relativePath())
            ->toBe('example.test/blog/index__foehn_sections=listing,pagination&.html');
    });

    it('keys it whatever the project config says, and never ignores it', function () {
        // Ignoring is the dangerous half: it would key a section request where the whole
        // page lives, so one visitor gets a fragment and the next gets a page. And a
        // project pattern would be a second grammar next to the parser's.
        $config = new PageCacheConfig(ignoredQueryArgs: ['foehn_sections'], cacheQueryArgs: [
            'foehn_sections' => '^.+$',
        ]);

        expect($config->getIgnoredQueryArgs())->not->toContain('foehn_sections');
        expect($config->getCacheQueryArgs()['foehn_sections'])->toBe(SectionRequest::VALUE_PATTERN);
        expect(QueryKey::canonical('foehn_sections=Results', $config))->toBeNull();
    });

    it('refuses a selection the parser would refuse', function (string $value) {
        expect(QueryKey::canonical('foehn_sections=' . $value, $this->config))->toBeNull();
    })->with([
        'an uppercase name' => ['Listing'],
        'a name that starts with a dash' => ['-listing'],
        'a name that ends with a dash' => ['listing-'],
        'a doubled separator' => ['listing,,pagination'],
        'a traversal' => ['../secret'],
        'more names than MAX_SECTIONS' => ['a,b,c,d,e,f'],
    ]);

    it('accepts exactly what the parser accepts', function (string $value) {
        // The pattern is derived from SectionRequest's own constants, and this is what
        // says so: two spellings of one grammar would drift, and the cache has already
        // paid for that once.
        $names = explode(',', $value);
        $parserAccepts = count($names) <= SectionRequest::MAX_SECTIONS
            && array_all($names, static fn(string $name): bool => SectionRequest::isSafeName($name));

        expect(preg_match('#' . SectionRequest::VALUE_PATTERN . '#', $value) === 1)->toBe($parserAccepts);
    })->with([
        ['listing'],
        ['a'],
        ['archive-results'],
        ['a1-b2-c3'],
        ['listing,pagination'],
        ['a,b,c,d,e'],
        ['a,b,c,d,e,f'],
        ['Listing'],
        ['listing_results'],
        ['-listing'],
        ['listing-'],
        ['listing,,pagination'],
        [''],
    ]);
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

        expect(array_keys(projectCacheQueryArgs($config)))->toBe(['lang', 'page']);
    });

    it('gives a shorthand list the default pattern', function () {
        expect(projectCacheQueryArgs(new PageCacheConfig(cacheQueryArgs: ['page'])))->toBe([
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

        expect(array_keys(projectCacheQueryArgs($config)))->toBe(['ok']);
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

describe('multi-value filters', function () {
    beforeEach(function () {
        $this->filters = new PageCacheConfig(cacheQueryArgs: [
            'genre' => '^[a-z0-9-]+(?:,[a-z0-9-]+)*$',
            'page' => '^[0-9]{1,6}$',
        ]);
    });

    it('keys the comma form, which is the one nginx can read', function () {
        // The format the query filters emit. Before the comma was in the charset this
        // was a bypass, which made the framework's own documented filter URLs the one
        // shape its own page cache refused to store.
        expect(QueryKey::canonical('genre=rock,jazz', $this->filters))->toBe('genre=rock,jazz&');
    });

    it('keys the bracketed form to exactly the same file', function (string $query) {
        // A checkbox group posts `genre[]`, which nginx cannot read at all — there is no
        // `$arg_genre[]`. It defers instead of guessing, and PHP joins the members here.
        // Same file as the comma form, so the two spellings never store the page twice.
        expect(QueryKey::canonical($query, $this->filters))->toBe('genre=rock,jazz&');
    })->with([
        ['genre[]=rock&genre[]=jazz'],
        ['genre%5B%5D=rock&genre%5B%5D=jazz'],
        ['genre%5b%5d=rock&genre%5b%5d=jazz'],
    ]);

    it('joins members that another keyed arg was written between', function () {
        // The members do not have to be adjacent, and the variant is still assembled in
        // the configuration's order rather than the request's.
        expect(QueryKey::canonical('genre[]=rock&page=2&genre[]=jazz', $this->filters))
            ->toBe('genre=rock,jazz&page=2&');
    });

    it('joins in request order and never sorts', function () {
        // Sorting is the obvious thing and the wrong thing: nginx cannot sort, so a
        // sorted key is one only PHP could compute. Two orders are two files holding the
        // same HTML — wasted disk, which is the cheap half of the trade.
        expect(QueryKey::canonical('genre[]=jazz&genre[]=rock', $this->filters))->toBe('genre=jazz,rock&');
    });

    it('refuses a member holding the separator', function () {
        // `?genre[]=rock,jazz` asks for one term whose slug has a comma in it, and
        // `?genre=rock,jazz` asks for two terms. Joining the first would key it where
        // the second lives, and one visitor would get the other's page.
        expect(QueryKey::canonical('genre[]=rock,jazz', $this->filters))->toBeNull();
    });

    it('refuses the two spellings mixed', function () {
        expect(QueryKey::canonical('genre=rock&genre[]=jazz', $this->filters))->toBeNull();
    });

    it('refuses a joined value its pattern does not allow', function (string $query) {
        expect(QueryKey::canonical($query, $this->filters))->toBeNull();
    })->with([
        ['genre[]=rock&genre[]=JAZZ'],
        ['genre=rock,JAZZ'],
        // 64 is the ceiling a filename may carry, joined and all.
        ['genre=' . implode(',', array_fill(0, 20, 'rock'))],
    ]);

    it('treats an empty member as absent, and an empty bare arg as it always did', function () {
        expect(QueryKey::canonical('genre[]=&genre[]=rock', $this->filters))
            ->toBe('genre=rock&')
            ->and(QueryKey::canonical('genre[]=', $this->filters))
            ->toBe('')
            ->and(QueryKey::canonical('genre=', $this->filters))
            ->toBe('');
    });

    it('refuses a bracketed name it was never told about', function () {
        // The name as written is `foo[]`, and an argument this cache cannot name is one
        // it does not serve — the same rule that keeps `utm_source[]` from passing for
        // `utm_source`.
        expect(QueryKey::canonical('foo[]=bar', $this->filters))
            ->toBeNull()
            ->and(QueryKey::canonical('utm_source[]=x', new PageCacheConfig(ignoredQueryArgs: ['utm_source'])))
            ->toBeNull();
    });
});
