<?php

declare(strict_types=1);

use Studiometa\Foehn\Smoke\Support\Site;

/**
 * Keyed query args, through real nginx.
 *
 * This file exists for one kind of assertion: `?page=2&lang=fr` and `?lang=fr&page=2` are
 * one page, so they must be one file, and both spellings must be served by nginx rather
 * than quietly falling through to PHP — and since #168, so must `?genre[]=rock&genre[]=jazz`
 * against the file `?genre=rock,jazz` wrote. Nothing in a unit test can show that — the
 * property is a claim about two independent implementations of the same algorithm, one of
 * them a generated config file, and only a real server can settle it.
 *
 * The policy differs from the starter's own, so the include is regenerated here the way a
 * deploy would regenerate it, and put back afterwards.
 */

beforeAll(function () {
    if (!Site::isRunning()) {
        return;
    }

    Site::enableCache([
        'cacheQueryArgs' =>
            "['page' => '^[0-9]{1,6}\$', 'lang' => '^[a-z]{2}\$', " . "'genre' => '^[a-z0-9-]+(?:,[a-z0-9-]+)*\$']",
    ]);
    Site::wp("wp rewrite structure '/%postname%/' --hard");
    Site::useNginx(true);

    $GLOBALS['foehn_smoke_nginx_include'] = Site::generateNginxInclude();
});

afterAll(function () {
    if (!Site::isRunning()) {
        return;
    }

    Site::restoreNginxInclude((string) ($GLOBALS['foehn_smoke_nginx_include'] ?? ''));
    Site::disableCache();
    Site::clearCache();
});

beforeEach(function () {
    if (!Site::isRunning()) {
        $this->markTestSkipped('ddev is not running — run `ddev start` and try again.');
    }

    Site::clearCache();
});

describe('keyed query args', function () {
    it('serves a keyed arg from nginx rather than falling through to PHP', function () {
        [$first, $second] = smokeGetTwice('/?page=2');

        expectCache($first, 'MISS', 'php');
        expectCache($second, 'HIT', 'nginx');
        expectSameBody($first, $second, 'nginx served bytes that are not the ones PHP stored');
    });

    it('names the file after the args, in the configuration order', function () {
        smokeWarm('/?lang=fr&page=2');

        // Configuration order, not request order: lang before page, whichever way round
        // the request wrote them.
        expect(Site::cacheFile('/', 'index__lang=fr&page=2&.html'))->toBeFile();
    });

    it('serves both spellings of one URL from one file', function () {
        // The assertion this whole file is for.
        smokeWarm('/?page=2&lang=fr');

        $reversed = smokeGet('/?lang=fr&page=2');

        expectCache($reversed, 'HIT', 'nginx');
        expect(Site::cachedPages())->toHaveCount(1);
    });

    it('serves a multi-value filter from nginx', function () {
        // The comma form is what the query filters emit and what `$arg_genre` can read,
        // so it takes the fast path like any other keyed value.
        [$first, $second] = smokeGetTwice('/?genre=rock,jazz');

        expectCache($first, 'MISS', 'php');
        expectCache($second, 'HIT', 'nginx');
        expect(Site::cacheFile('/', 'index__genre=rock,jazz&.html'))->toBeFile();
    });

    it('serves the bracketed form from nginx, out of the same file', function (string $query) {
        // The assertion #168 is for. There is no `$arg_genre[]`, so the snippet reads the
        // members out of `$args` one capture at a time and joins them the way PHP does —
        // and only a real nginx can show that the two joins agree to the byte. A HIT from
        // `php` here would mean nginx declined; a MISS would mean it computed a name PHP
        // did not, which is the disagreement this whole file is watching for.
        smokeWarm('/?genre=rock,jazz');

        $bracketed = smokeGet('/' . $query);

        expectCache($bracketed, 'HIT', 'nginx');
        expect(Site::cachedPages())->toHaveCount(1);
    })->with([
        'literal brackets' => ['?genre[]=rock&genre[]=jazz'],
        // A form encoder may percent-escape the brackets; neither reader decodes.
        'escaped brackets' => ['?genre%5B%5D=rock&genre%5B%5D=jazz'],
        'lowercase hex' => ['?genre%5b%5d=rock&genre%5b%5d=jazz'],
        'a tracking arg between the members' => ['?genre[]=rock&utm_source=x&genre[]=jazz'],
    ]);

    it('joins the members in request order and slots the result into the configuration order', function () {
        // Two things at once: the members keep the order they arrived in — nginx cannot
        // sort, and PHP does not — while the arg itself lands where the sorted
        // configuration puts it, whatever was written between the members.
        smokeWarm('/?genre=jazz,rock&lang=fr&page=2');

        $bracketed = smokeGet('/?page=2&genre[]=jazz&lang=fr&genre[]=rock');

        expectCache($bracketed, 'HIT', 'nginx');
        expect(Site::cacheFile('/', 'index__genre=jazz,rock&lang=fr&page=2&.html'))->toBeFile();
        expect(Site::cachedPages())->toHaveCount(1);
    });

    it('decides every other bracketed spelling the way the writer does', function (
        string $warm,
        string $query,
        string $state,
        ?string $via,
    ) {
        // The comma form is warm, so a HIT can only come from the file it wrote. `nginx`
        // means the snippet joined the members itself; `php` means it declined a shape it
        // cannot join the way PHP does and the drop-in served the same file — today's
        // path, two milliseconds slower and never wrong. A BYPASS is a shape PHP refuses
        // as well, so nginx refusing it is the two readers agreeing.
        smokeWarm('/' . $warm);

        expectCache(smokeGet('/' . $query), $state, $via);
    })->with([
        'five members, every slot nginx has' => [
            '?genre=a,b,c,d,e',
            '?genre[]=a&genre[]=b&genre[]=c&genre[]=d&genre[]=e',
            'HIT',
            'nginx',
        ],
        // One more than the slots: nginx would have to drop a member, so it declines.
        'six members' => [
            '?genre=a,b,c,d,e,f',
            '?genre[]=a&genre[]=b&genre[]=c&genre[]=d&genre[]=e&genre[]=f',
            'HIT',
            'php',
        ],
        // PHP skips an empty member; a fixed sequence of captures cannot.
        'an empty member' => ['?genre=rock', '?genre[]=&genre[]=rock', 'HIT', 'php'],
        'a member with no value at all' => ['?genre=rock', '?genre[]&genre[]=rock', 'HIT', 'php'],
        // `?genre[]=rock,jazz` asks for one term with a comma in its slug; joining it would
        // key it where the two-term page lives. PHP refuses it, so must nginx.
        'a member holding the separator' => ['?genre=rock,jazz', '?genre[]=rock,jazz', 'BYPASS', null],
        'both spellings in one URL' => ['?genre=rock,jazz', '?genre=rock&genre[]=jazz', 'BYPASS', null],
        // The bare spelling with no `=`: `$arg_genre` skips it, PHP counts it as an
        // occurrence — so nginx must not join the member on its own.
        'both spellings, the bare one with no value' => ['?genre=rock', '?genre&genre[]=rock', 'BYPASS', null],
        'both spellings, the bare one last and with no value' => ['?genre=rock', '?genre[]=rock&genre', 'BYPASS', null],
        'a member the pattern rejects' => ['?genre=rock,jazz', '?genre[]=rock&genre[]=JAZZ', 'BYPASS', null],
        'a member outside the charset' => ['?genre=rock,jazz', '?genre[]=rock&genre[]=%C3%A9t%C3%A9', 'BYPASS', null],
        'a bracketed name nobody configured' => ['?genre=rock', '?foo[]=bar', 'BYPASS', null],
        'a bracketed tracking arg' => ['?genre=rock', '?genre[]=rock&utm_source[]=x', 'BYPASS', null],
    ]);

    it('keeps a keyed arg apart from the page without it', function () {
        smokeWarm('/');
        smokeWarm('/?page=2');

        expect(Site::cachedPages())
            ->toContain(Site::host() . '/index.html')
            ->toContain(Site::host() . '/index__page=2&.html');
    });

    it('decides every other query string the way the writer does', function (string $query, string $state) {
        // Both files a HIT could come from are warm, so a MISS here means nginx computed a
        // name PHP did not — which is the disagreement this file is watching for.
        smokeWarm('/');
        smokeWarm('/?page=2');

        expectCache(smokeGet('/' . $query), $state);
    })->with([
        'a keyed arg with no value keys as no query' => ['?page=', 'HIT'],
        'a keyed arg beside a tracking one' => ['?page=2&utm_source=x', 'HIT'],
        // nginx reads the first `page=`, PHP the last. Neither guesses.
        'a repeated keyed arg' => ['?page=1&page=2', 'BYPASS'],
        'a repeated keyed arg whose first value is empty' => ['?page=&page=2', 'BYPASS'],
        // `$arg_page` skips an occurrence with no `=` and reads the next; PHP counts both.
        'a repeated keyed arg whose first occurrence has no value' => ['?page&page=2', 'BYPASS'],
        'a repeated keyed arg whose last occurrence has no value' => ['?page=2&page', 'BYPASS'],
        'a value its pattern rejects' => ['?page=abc', 'BYPASS'],
        'a value that would leave the cache directory' => ['?page=../../etc/passwd', 'BYPASS'],
        'an arg nobody configured' => ['?foo=bar', 'BYPASS'],
        'a keyed arg beside an unknown one' => ['?page=2&foo=bar', 'BYPASS'],
    ]);
});
