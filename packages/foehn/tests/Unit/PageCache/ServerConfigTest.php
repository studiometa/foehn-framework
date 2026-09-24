<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\PageCacheConfig;
use Studiometa\Foehn\PageCache\QueryKey;
use Studiometa\Foehn\PageCache\ServerConfig\ApacheSnippet;
use Studiometa\Foehn\PageCache\ServerConfig\NginxSnippet;
use Studiometa\Foehn\PageCache\ServerConfig\SnippetPolicy;
use Studiometa\Foehn\Views\Sections\SectionRequest;

/**
 * Characterization tests. Both snippets are built by string concatenation, which is easy
 * to break silently: a dropped cookie prefix or a mangled query-string guard leaves a
 * config that loads, serves, and answers HIT under rules the site no longer has. So the
 * condition list is pinned rather than described.
 */

beforeEach(function () {
    wp_stub_reset();
    // ABSPATH is what both generators derive the document root from — the directory
    // above it, because WordPress lives in a subdirectory in this layout.
    $this->docroot = dirname((string) constant('WP_CONTENT_DIR'));
    $this->config = new PageCacheConfig(enabled: true, path: constant('WP_CONTENT_DIR') . '/cache/foehn/pages');
});

describe('SnippetPolicy', function () {
    it('turns the cache path into the URL path a server can serve from', function () {
        expect(new SnippetPolicy($this->config)->cacheUrlPath())->toBe('/wp-content/cache/foehn/pages');
    });

    it('has no URL path for a cache outside the document root', function () {
        // A legitimate choice — the drop-in still serves it — but there is no snippet to
        // generate, and saying so beats generating a broken one.
        $config = new PageCacheConfig(enabled: true, path: '/srv/elsewhere/pages');

        expect(new SnippetPolicy($config)->cacheUrlPath())->toBeNull();
        expect(new NginxSnippet($config)->render())->toBeNull();
        expect(new ApacheSnippet($config)->render())->toBeNull();
    });

    it('matches a query string of ignored args in any order', function () {
        $pattern = new SnippetPolicy($this->config)->ignorableQueryPattern();

        expect(preg_match('/' . $pattern . '/', 'utm_source=a&utm_medium=b'))->toBe(1);
        expect(preg_match('/' . $pattern . '/', 'utm_medium=b&utm_source=a'))->toBe(1);
        expect(preg_match('/' . $pattern . '/', 'utm_source'))->toBe(1);
    });

    it('matches an absent query string, so one condition can cover both', function () {
        // nginx has no `and`, so a pattern that also matches the empty string is one
        // condition instead of a conjunction.
        expect(preg_match('/' . new SnippetPolicy($this->config)->ignorableQueryPattern() . '/', ''))->toBe(1);
    });

    it('does not match a query string carrying anything else', function () {
        $pattern = new SnippetPolicy($this->config)->ignorableQueryPattern();

        expect(preg_match('/' . $pattern . '/', 'foo=bar'))->toBe(0);
        expect(preg_match('/' . $pattern . '/', 'utm_source=a&s=hello'))->toBe(0);
        // The boundary that stops `utm_source` matching the start of `utm_sourcex`.
        expect(preg_match('/' . $pattern . '/', 'utm_sourcex=a'))->toBe(0);
    });

    it('lets nginx serve a section request, and never Apache', function () {
        // `foehn_sections` is keyed, so nginx can build the filename for it and
        // `mod_rewrite` cannot — the same split every keyed arg gets. Apache passes it to
        // PHP, which is also what keeps the `noindex` on that path: the drop-in replays
        // the header the response recorded.
        $policy = new SnippetPolicy(new PageCacheConfig(ignoredQueryArgs: ['foehn_sections', 'utm_source']));

        expect(preg_match('/' . $policy->ignorableQueryPattern() . '/', 'foehn_sections=results'))->toBe(0);
        expect(preg_match('/' . $policy->knownQueryPattern() . '/', 'foehn_sections=results'))->toBe(1);
        expect($policy->canonicalQueryStatements())
            ->toContain('set $foehn_q "${foehn_q}foehn_sections=$foehn_val_foehn_sections&";');
    });

    it('ignores nothing but an absent query string when the project ignores no args', function () {
        $pattern = new SnippetPolicy(new PageCacheConfig(ignoredQueryArgs: []))->ignorableQueryPattern();

        expect(preg_match('/' . $pattern . '/', 'utm_source=a'))->toBe(0);
        expect(preg_match('/' . $pattern . '/', ''))->toBe(1);
    });

    it('agrees with the PHP writer about which query strings are ignorable', function (string $query) {
        // The whole design turns on the readers keying the same request the same way, so
        // the generated pattern and Bypass::canonicalQuery() are compared directly.
        $ignorableByPhp = pageCacheBypass($this->config)->canonicalQuery('/?' . $query) === '';
        $ignorableByServer =
            preg_match('/' . new SnippetPolicy($this->config)->ignorableQueryPattern() . '/', $query) === 1;

        expect($ignorableByServer)->toBe($ignorableByPhp);
    })->with([
        [''],
        ['utm_source=a'],
        ['utm_source=a&utm_medium=b'],
        ['utm_medium=b&utm_source=a'],
        ['gclid=x'],
        ['utm_source'],
        ['foo=bar'],
        ['utm_source=a&s=hello'],
        ['utm_sourcex=a'],
        ['s=hello&utm_source=a'],
    ]);

    it('agrees with the PHP writer about which query strings nginx may serve at all', function (string $query) {
        // The keyed args widen what nginx will serve beyond what Apache will, so the
        // pattern nginx gets is compared against the writer's own answer as well. A
        // disagreement here is nginx serving a file PHP never wrote, or refusing one it did.
        $config = new PageCacheConfig(enabled: true, path: $this->config->path, cacheQueryArgs: [
            'page' => '^[0-9]{1,6}$',
            'lang' => '^[a-z]{2}$',
        ]);

        $servableByPhp = pageCacheBypass($config)->canonicalQuery('/?' . $query) !== null;
        $servableByServer = preg_match('/' . new SnippetPolicy($config)->knownQueryPattern() . '/', $query) === 1;

        expect($servableByServer)->toBe($servableByPhp);
    })->with([
        [''],
        ['page=2'],
        ['lang=fr'],
        ['page=2&lang=fr'],
        ['lang=fr&page=2'],
        ['page=2&utm_source=a'],
        ['utm_source=a'],
        ['foo=bar'],
        ['page=2&foo=bar'],
        ['pagex=2'],
        // The bracketed spelling of a keyed name is one nginx can now key; of an ignored
        // name it is not, because PHP matches an ignored name against the raw `utm_source[]`.
        ['page[]=2'],
        ['lang%5B%5D=fr'],
        ['lang%5b%5d=fr&page=2'],
        ['utm_source[]=a'],
        ['pagex[]=2'],
        ['[]=2'],
    ]);

    it('unrolls the keyed args in the configuration order, not the request order', function () {
        // The one property that makes ?page=2&lang=fr and ?lang=fr&page=2 one file: every
        // reader walks this list, and nginx's $arg_name does not care where the arg was.
        $statements = new SnippetPolicy(
            new PageCacheConfig(cacheQueryArgs: ['page', 'lang']),
        )->canonicalQueryStatements();

        expect(strpos($statements, 'lang=$foehn_val_lang&'))
            ->toBeLessThan((int) strpos($statements, 'page=$foehn_val_page&'));
    });

    it('bypasses a keyed value its pattern rejects, rather than serving the unkeyed page', function () {
        // The bug the end-to-end suite caught: with only a positive match, `?page=abc`
        // built no variant, fell back to index.html and served page one. nginx has no
        // `and`, so "present and invalid" is spelled with a sentinel.
        $statements = new SnippetPolicy(new PageCacheConfig(cacheQueryArgs: [
            'page' => '^[0-9]+$',
        ]))->canonicalQueryStatements();

        expect($statements)
            ->toContain('set $foehn_arg_page "empty";')
            ->toContain('if ($foehn_val_page != "") { set $foehn_arg_page "invalid"; }')
            ->toContain('if ($foehn_val_page ~ "^[0-9]+$") { set $foehn_arg_page "valid"; }')
            ->toContain('if ($foehn_arg_page = "invalid") { set $foehn_bypass 0; }');

        // And the order is the logic: a valid value has to be able to overwrite the
        // "invalid" that being present set, and the charset floor to overwrite that.
        expect(strpos($statements, '"invalid"; }'))
            ->toBeLessThan((int) strpos($statements, '"valid"; }'))
            ->and(strpos($statements, '"valid"; }'))
            ->toBeLessThan((int) strrpos($statements, '"invalid"; }'));
    });

    it('holds a keyed value to the charset a filename may use, whatever the project wrote', function () {
        // A project pattern can narrow the charset, never widen it — the value becomes
        // part of a filename.
        expect(new SnippetPolicy(new PageCacheConfig(cacheQueryArgs: [
            'lang' => '^.+$',
        ]))->canonicalQueryStatements())->toContain('if ($foehn_val_lang ~ "[^A-Za-z0-9_.,\-]|^.{65,}$") { set $foehn_arg_lang "invalid"; }');
    });

    it('lets a comma through the floor, so a multi-value filter can be keyed', function () {
        // The comma is the separator between the values of one filter, and nginx reads
        // `?genre=rock,jazz` with `$arg_genre` like any other value. Without it in the
        // floor the snippet called every multi-value filter invalid and bypassed it.
        $statements = new SnippetPolicy(new PageCacheConfig(cacheQueryArgs: [
            'genre' => '^[a-z0-9-]+(?:,[a-z0-9-]+)*$',
        ]))->canonicalQueryStatements();

        expect($statements)
            ->toContain('if ($foehn_val_genre ~ "^[a-z0-9-]+(?:,[a-z0-9-]+)*$") { set $foehn_arg_genre "valid"; }')
            ->and($statements)
            ->toContain('set $foehn_q "${foehn_q}genre=$foehn_val_genre&";');
    });

    it('knows a keyed name in both spellings, and an ignored one only as written', function () {
        // There is no `$arg_genre[]`, so the bracketed spelling used to fail this pattern
        // and fall through to PHP. The statements below can key it now, so it is admitted —
        // for a keyed name only. PHP matches an ignored name against the raw `utm_source[]`,
        // which is not `utm_source`, and an argument this cache cannot name is a bypass.
        $policy = new SnippetPolicy(new PageCacheConfig(cacheQueryArgs: ['genre' => '^[a-z,]+$']));
        $known = static fn(string $query): bool => (bool) preg_match('#' . $policy->knownQueryPattern() . '#', $query);

        expect($known('genre=rock,jazz'))->toBeTrue();
        expect($known('genre[]=rock&genre[]=jazz'))->toBeTrue();
        expect($known('genre%5B%5D=rock&genre%5b%5d=jazz'))->toBeTrue();
        expect($known('genre[]=rock&utm_source=x'))->toBeTrue();
        expect($known('utm_source[]=x'))->toBeFalse();
        expect($known('foo[]=bar'))->toBeFalse();
        expect($known('genre[=rock'))->toBeFalse();
    });

    it('reads the members of a bracketed arg out of $args, one slot at a time', function () {
        // The k-th capture is anchored on the first occurrence and skips forward k-1 times
        // with "finish this value, step over whole pairs, land on the next occurrence".
        // Leftmost-first matching and the lazy skip make it the k-th member in request
        // order — which is the order PHP joins in, and the order it must never sort.
        $statements = new SnippetPolicy(new PageCacheConfig(cacheQueryArgs: ['genre']))->canonicalQueryStatements();
        $brackets = '(?:\[\]|%5[Bb]%5[Dd])';
        $next = '[^&]*&(?:[^&]*&)*?genre' . $brackets . '=';

        expect($statements)
            ->toContain('set $foehn_val_genre $arg_genre;')
            ->toContain('if ($args ~ "(?:^|&)genre' . $brackets . '=([^&]*)") { set $foehn_val_genre "$1"; }')
            ->toContain(
                'if ($args ~ "(?:^|&)genre'
                . $brackets
                . '='
                . $next
                . '([^&]*)") { set $foehn_val_genre "${foehn_val_genre},$1"; }',
            )
            ->toContain(
                'if ($args ~ "(?:^|&)genre'
                . $brackets
                . '='
                . str_repeat($next, SnippetPolicy::MEMBER_SLOTS - 1)
                . '([^&]*)")',
            );

        // One capture per slot, and the first replaces `$arg_genre` rather than appending
        // to it — otherwise the bare spelling's empty value would become a leading comma.
        expect(substr_count($statements, 'set $foehn_val_genre "${foehn_val_genre},$1"; }'))
            ->toBe(SnippetPolicy::MEMBER_SLOTS - 1);

        // The join comes first, then the validation runs on what was joined: `$arg_genre`
        // appears in the statements exactly once, as the value to start from.
        expect(substr_count($statements, '$arg_genre'))->toBe(1);
    });

    it('declines every bracketed shape it cannot join the way PHP does', function () {
        // A wrong key serves one visitor another's page; a decline costs two milliseconds
        // in the drop-in. So each of these is a bypass, not a best effort.
        $statements = new SnippetPolicy(new PageCacheConfig(cacheQueryArgs: ['genre']))->canonicalQueryStatements();
        $brackets = '(?:\[\]|%5[Bb]%5[Dd])';
        $next = '[^&]*&(?:[^&]*&)*?genre' . $brackets . '=';

        expect($statements)
            // More members than slots: nginx would silently drop the rest.
            ->toContain(
                'if ($args ~ "(?:^|&)genre'
                . $brackets
                . '='
                . str_repeat($next, SnippetPolicy::MEMBER_SLOTS)
                . '") { set $foehn_bypass 0; }',
            )
            // An empty member, with or without its `=`: PHP skips it, a fixed sequence of
            // captures cannot.
            ->toContain('if ($args ~ "(?:^|&)genre' . $brackets . '=?(?:&|$)") { set $foehn_bypass 0; }')
            // A member outside the member charset — the comma above all, since a member
            // holding the separator would join to the key of a different request. The
            // class is the one QueryKey uses, not a second spelling of it.
            ->toContain(
                'if ($args ~ "(?:^|&)genre'
                . $brackets
                . '=[^&]*[^'
                . QueryKey::MEMBER_CHARACTER_CLASS
                . '&]") { set $foehn_bypass 0; }',
            )
            // Both spellings in one URL, in either order, with or without an `=` on the
            // bare one — `$arg_genre` skips a bare `genre`, PHP counts it.
            ->toContain(
                'if ($args ~ "(?:^|&)(?:genre(?:=[^&]*)?&(?:[^&]*&)*genre'
                . $brackets
                . '=|genre'
                . $brackets
                . '=[^&]*&(?:[^&]*&)*genre(?:=|&|$))") { set $foehn_bypass 0; }',
            );
    });

    it('declines the shapes PHP refuses, and only those, when its guards are run as regexes', function () {
        // The guards are PCRE on both sides, so running them here is running what nginx
        // runs. A query string trips a decline when any guard matches it; the expectation
        // is QueryKey's own answer, so the two readers are compared rather than restated.
        $config = new PageCacheConfig(cacheQueryArgs: ['genre' => '^[a-z]+(?:,[a-z]+)*$', 'page' => '^[0-9]+$']);
        $policy = new SnippetPolicy($config);
        $statements = $policy->canonicalQueryStatements() . "\n" . $policy->repeatedQueryStatements();

        preg_match_all('/if \(\$args ~ "([^"]+)"\) \{ set \$foehn_bypass 0; \}/', $statements, $matches);
        $guards = $matches[1];
        expect($guards)->not->toBeEmpty();

        $declined = static fn(string $query): bool => array_any(
            $guards,
            static fn(string $guard): bool => preg_match('#' . $guard . '#', $query) === 1,
        );

        // Refused by PHP because both spellings are present — the bare one with a value,
        // empty, or with no `=` at all, in either order.
        foreach ([
            'genre=rock&genre[]=jazz',
            'genre[]=jazz&genre=rock',
            'genre=&genre[]=rock',
            'genre&genre[]=rock',
            'genre[]=rock&genre',
            'genre&genre[]=rock&genre[]=jazz',
            'genre[]=rock&genre&genre[]=jazz',
            'genre[]=rock&page=2&genre',
            // Refused by PHP because a bare name occurs twice, `=` or not.
            'page=1&page=2',
            'page=&page=2',
            'page&page=2',
            'page=2&page',
            'page&page',
        ] as $query) {
            expect(QueryKey::canonical($query, $config))->toBeNull($query);
            expect($declined($query))->toBeTrue($query);
        }

        // Keyed by PHP, so no guard may fire: nginx joins these itself.
        foreach ([
            'genre',
            'genre=rock',
            'genre[]=rock&genre[]=jazz',
            'genre[]=rock&page=2&genre[]=jazz',
            'page=2&genre[]=jazz&genre[]=rock',
            'page&genre[]=rock',
            'page=2',
        ] as $query) {
            expect(QueryKey::canonical($query, $config))->not->toBeNull($query);
            expect($declined($query))->toBeFalse($query);
        }

        // A name is matched whole: `genrex` is another arg, refused by the known-args
        // pattern rather than mistaken for a second `genre` by a guard.
        expect($declined('genre[]=rock&genrex=1'))->toBeFalse();
        expect($declined('genrex=1&genre=rock'))->toBeFalse();
    });

    it('bounds the members it joins at the framework\'s own bound on a comma list', function () {
        // PHP joins any number and lets the 64-character floor refuse the result; nginx has
        // no loop, so each member is one more statement and one more regex pass per request.
        // Five is what a section request can carry, so that one never falls through.
        expect(SnippetPolicy::MEMBER_SLOTS)->toBe(SectionRequest::MAX_SECTIONS)->toBe(5);
    });

    it('escapes a keyed name inside the bracketed captures too', function () {
        // A name with a regex metacharacter in it would otherwise rewrite the pattern.
        $statements = new SnippetPolicy(new PageCacheConfig(cacheQueryArgs: ['a-b']))->canonicalQueryStatements();

        expect($statements)->toContain('(?:^|&)a\-b(?:\[\]|%5[Bb]%5[Dd])=([^&]*)');
    });

    it('spells a hyphenated keyed name the way an nginx variable can be spelled', function () {
        // A taxonomy registered as `product-type` has a query var with a hyphen in it, and
        // an nginx variable name may not: `$foehn_val_a-b` fails `nginx -t`, and `$arg_a-b`
        // is `$arg_a` followed by a literal `-b`. So the variable takes an underscore, and
        // the bare spelling is captured out of `$args` with `$arg_name`'s own rule — while
        // the key keeps the name as written, because that is the filename PHP wrote.
        $config = new PageCacheConfig(enabled: true, path: $this->config->path, cacheQueryArgs: ['a-b']);
        $statements = new SnippetPolicy($config)->canonicalQueryStatements();

        expect($statements)
            ->toContain('set $foehn_val_a_b "";')
            ->toContain('if ($args ~ "(?:^|&)a\-b=([^&]*)") { set $foehn_val_a_b "$1"; }')
            ->toContain('{ set $foehn_val_a_b "${foehn_val_a_b},$1"; }')
            ->toContain('if ($foehn_val_a_b != "") { set $foehn_arg_a_b "invalid"; }')
            ->toContain('if ($foehn_arg_a_b = "valid") { set $foehn_q "${foehn_q}a-b=$foehn_val_a_b&"; }')
            ->not->toContain('$arg_a');

        // No variable anywhere in the rendered snippet ends at a hyphen: `$name-` is where
        // nginx would either refuse the file or read a shorter variable than was meant.
        expect(preg_match('/\$\{?[A-Za-z0-9_]+-/', (string) new NginxSnippet($config)->render()))->toBe(0);
        expect(SnippetPolicy::variable('a-b'))->toBe('a_b');
    });

    it('reads a hyphenated name the way $arg_name reads one it can spell', function () {
        // The capture stands in for `$arg_a-b`, so it has to skip and stop where that
        // would: first occurrence, `=` required, value up to the next `&`, name whole.
        $statements = new SnippetPolicy(new PageCacheConfig(cacheQueryArgs: ['a-b']))->canonicalQueryStatements();

        preg_match('/if \(\$args ~ "([^"]+)"\) \{ set \$foehn_val_a_b "\$1"; \}/', $statements, $matches);
        $capture = static fn(string $query): ?string => preg_match('#' . $matches[1] . '#', $query, $m) === 1
            ? $m[1]
            : null;

        expect($capture('a-b=x'))->toBe('x');
        expect($capture('page=2&a-b=x&lang=fr'))->toBe('x');
        expect($capture('a-b=1&a-b=2'))->toBe('1');
        expect($capture('a-b='))->toBe('');
        expect($capture('a-b'))->toBeNull();
        expect($capture('a-b&a-b=2'))->toBe('2');
        expect($capture('xa-b=1'))->toBeNull();
        expect($capture('a-bx=1'))->toBeNull();
    });

    it('bypasses a keyed arg that appears twice, which the readers read differently', function () {
        $policy = new SnippetPolicy(new PageCacheConfig(cacheQueryArgs: ['page']));

        // The `=` is optional on both occurrences: `$arg_page` skips a bare `page` and
        // reads the `page=2` after it, PHP counts both and refuses.
        expect($policy->repeatedQueryStatements())
            ->toContain('if ($args ~ "(?:^|&)page(?:=[^&]*)?&(?:.*&)?page(?:=|&|$)") { set $foehn_bypass 0; }');
    });

    it('points the maintenance test at ABSPATH rather than at the document root', function () {
        // WordPress writes .maintenance to ABSPATH, which is web/wp/ here. A snippet
        // testing the document root keeps serving cached pages through a core update.
        expect(new SnippetPolicy($this->config)->maintenanceUrlPath())->toBe('/wp/.maintenance');
    });

    it('escapes a config value so it cannot rewrite the generated rules', function () {
        $policy = new SnippetPolicy(new PageCacheConfig(bypassCookies: ['a.b|c']));

        expect($policy->cookiePattern())->toBe('a\.b\|c');
    });

    it('changes its hash when the policy changes, and not otherwise', function () {
        $hash = new SnippetPolicy($this->config)->hash();

        expect(new SnippetPolicy($this->config)->hash())->toBe($hash);
        expect(
            new SnippetPolicy(new PageCacheConfig(
                enabled: true,
                path: $this->config->path,
                bypassCookies: ['wordpress_logged_in_'],
            ))->hash(),
        )
            ->not
            ->toBe($hash);
    });
});

describe('NginxSnippet', function () {
    beforeEach(function () {
        $this->snippet = (string) new NginxSnippet($this->config)->render();
    });

    it('guards on the method, the query string, the cookies and the maintenance file', function () {
        expect($this->snippet)
            ->toContain('if ($request_method != GET)')
            ->toContain('if ($args !~ "^(?:(?:utm_source|')
            ->toContain('if ($http_cookie ~* "(wordpress_logged_in_|comment_author_|wp\-postpass_)")')
            ->toContain('if (-f "$document_root/wp/.maintenance")');
    });

    it('decides a flag at server level and acts on it once', function () {
        // The shape of prod-wp-rocket.conf, which studiometa/wordpress-project has run in
        // ddev and in production for years. Server level is what allows `set` inside `if`:
        // inside a location, a matched `if` continues in an implicit location that
        // inherits no content handler, and building a filename needs `set`.
        expect($this->snippet)
            ->toContain('set $foehn_bypass 1;')
            ->toContain('if ($foehn_bypass = 1) {')
            ->toContain('rewrite ^ "$foehn_url" last;');
    });

    it('interpolates $uri whole, never through a regex capture', function () {
        // $uri is decoded, which is what makes an accented permalink find the file PHP
        // wrote. But $1 from `if ($uri ~ …)` comes back percent-encoded — the end-to-end
        // suite caught that as a permanent miss on every non-ASCII URL — so the path is
        // never derived from a capture, and two candidates cover the trailing slash.
        expect($this->snippet)
            ->toContain('set $foehn_url "/wp-content/cache/foehn/pages/$host${uri}index$foehn_variant.html";')
            ->toContain('set $foehn_url "/wp-content/cache/foehn/pages/$host${uri}/index$foehn_variant.html";')
            ->not->toContain('$foehn_path')
            ->not->toContain('if ($request_uri');
    });

    it('declares no location of its own for the site, so it is an include', function () {
        // Nothing has to be removed from the site's configuration, and a miss falls
        // through to whatever front controller block is already there.
        expect($this->snippet)
            ->not->toContain('location / {')
            ->not->toContain('location ~ "^(?!')
            ->not->toContain('@foehn_miss');
    });

    it('names every cookie prefix the config bypasses on', function () {
        foreach ($this->config->bypassCookies as $prefix) {
            expect($this->snippet)->toContain(str_replace('-', '\-', $prefix));
        }
    });

    it('builds the filename from the keyed args when a project has any', function () {
        $config = new PageCacheConfig(enabled: true, path: $this->config->path, cacheQueryArgs: [
            'page' => '^[0-9]{1,6}$',
        ]);

        expect((string) new NginxSnippet($config)->render())
            ->toContain('if ($foehn_val_page ~ "^[0-9]{1,6}$") { set $foehn_arg_page "valid"; }')
            ->toContain('if ($foehn_arg_page = "valid") { set $foehn_q "${foehn_q}page=$foehn_val_page&"; }')
            ->toContain('set $foehn_variant "__$foehn_q";');
    });

    it('keys a section request even when the project keyed nothing', function () {
        // The default configuration keys one arg, so there is always something to unroll.
        expect($this->snippet)
            ->toContain('if ($foehn_val_foehn_sections ~ "' . SectionRequest::VALUE_PATTERN . '")')
            ->toContain('set $foehn_q "${foehn_q}foehn_sections=$foehn_val_foehn_sections&";');
    });

    it('joins the bracketed spelling of a section request like any other keyed arg', function () {
        // `foehn_sections` is keyed on every configuration, so it gets the same two
        // spellings — and the key nginx builds for `?foehn_sections[]=a&foehn_sections[]=b`
        // is the one PHP builds, because QueryKey does not know a reserved name from a
        // project's own.
        expect($this->snippet)
            ->toContain('set $foehn_val_foehn_sections $arg_foehn_sections;')
            ->toContain('if ($args ~ "(?:^|&)foehn_sections(?:\\[\\]|%5[Bb]%5[Dd])=([^&]*)") {');
    });

    it('keeps a cached fragment out of the index, which nginx cannot replay', function () {
        // The drop-in replays the `X-Robots-Tag` a section response recorded; nginx has no
        // way to read a stored header, so it derives the one that matters from the
        // request. A variable rather than an `add_header` under the `if`: `add_header` is
        // not allowed in a server-level `if`, and nginx omits a header whose value is
        // empty — which is what makes one unconditional `add_header` behave conditionally.
        //
        // Derived from the joined value and not from `$arg_foehn_sections`, which is empty
        // for `?foehn_sections[]=listing` — a request nginx now serves, and one that would
        // otherwise go out as an indexable fragment.
        expect($this->snippet)
            ->toContain('set $foehn_robots "";')
            ->toContain('if ($foehn_val_foehn_sections != "") {')
            ->toContain('set $foehn_robots "noindex, nofollow";')
            ->toContain('add_header X-Robots-Tag $foehn_robots;');
    });

    it('makes the cache directory unreachable from outside', function () {
        // `^~` beats every regex location including the FastCGI one, so a .php file
        // written under the cache root is unreachable as well as unexecutable.
        expect($this->snippet)->toContain('location ^~ /wp-content/cache/foehn/')->toContain('internal;');
    });

    it('says which reader answered, and how long a browser may hold the page', function () {
        expect($this->snippet)
            ->toContain('add_header X-Foehn-Cache HIT;')
            ->toContain('add_header X-Foehn-Cache-Via nginx;')
            ->toContain('add_header Cache-Control "public, max-age=0, must-revalidate";')
            ->toContain('add_header Vary "Cookie, Accept-Encoding";');
    });

    it('carries the policy it was generated from', function () {
        expect($this->snippet)->toContain('# policy: ' . new NginxSnippet($this->config)->hash());
    });

    it('has no device or user-agent rule, because no variant ships in v1', function () {
        expect(strtolower($this->snippet))
            ->not->toContain('user_agent')
            ->not->toContain('mobile')
            ->not->toContain('419');
    });

    it('reflects a browser max-age the project asked for', function () {
        $config = new PageCacheConfig(enabled: true, path: $this->config->path, browserMaxAge: 60);

        expect((string) new NginxSnippet($config)->render())
            ->toContain('add_header Cache-Control "public, max-age=60, must-revalidate";');
    });
});

describe('ApacheSnippet', function () {
    beforeEach(function () {
        $this->snippet = (string) new ApacheSnippet($this->config)->render();
    });

    it('guards on the method, the query string, the cookies and the maintenance file', function () {
        expect($this->snippet)
            ->toContain('RewriteCond %{REQUEST_METHOD} =GET')
            ->toContain('RewriteCond %{QUERY_STRING} ^(?:(?:utm_source|')
            ->toContain('RewriteCond %{HTTP:Cookie} !(wordpress_logged_in_|comment_author_|wp\-postpass_) [NC]')
            ->toContain('RewriteCond %{DOCUMENT_ROOT}/wp/.maintenance !-f');
    });

    it('never widens its query guard to the keyed args', function () {
        // mod_rewrite cannot assemble a canonical filename, so serving `index.html` for
        // `?page=2` would hand a visitor page one. Apache covers the unkeyed cases and
        // lets the rest reach the drop-in.
        $config = new PageCacheConfig(enabled: true, path: $this->config->path, cacheQueryArgs: [
            'page' => '^[0-9]{1,6}$',
        ]);

        // The cache path itself contains "pages", so the guard is read out of the rule
        // rather than searched for across the whole block.
        preg_match('/RewriteCond %\{QUERY_STRING\} (.+)/', (string) new ApacheSnippet($config)->render(), $matches);

        expect($matches[1] ?? '')->not->toBe('')->not->toContain('page');
    });

    it('matches on the decoded path rather than on REQUEST_URI', function () {
        // %{REQUEST_URI} still holds the percent escapes the browser sent, so a rule
        // written against it misses every accented permalink while looking correct on an
        // English site.
        expect($this->snippet)
            ->toContain('RewriteCond %{DOCUMENT_ROOT}/wp-content/cache/foehn/pages/%{HTTP_HOST}/$1/index.html -f')
            ->toContain('RewriteRule ^(.*?)/?$ /wp-content/cache/foehn/pages/%{HTTP_HOST}/$1/index.html [L]');
    });

    it('denies PHP and directory listings under the cache root', function () {
        expect($this->snippet)
            ->toContain('Options -Indexes')
            ->toContain('Require all denied')
            ->toContain('m#^/wp-content/cache/foehn/#');
    });

    it('is delimited so a project can keep its own rules in the same file', function () {
        expect($this->snippet)->toStartWith(ApacheSnippet::BEGIN)->toEndWith(ApacheSnippet::END);
    });

    it('carries the policy it was generated from', function () {
        expect($this->snippet)->toContain('# policy: ' . new ApacheSnippet($this->config)->hash());
    });

    it('has no device or user-agent rule, because no variant ships in v1', function () {
        expect(strtolower($this->snippet))->not->toContain('user_agent')->not->toContain('mobile');
    });

    it('replaces its own block rather than stacking another one', function () {
        $apache = new ApacheSnippet($this->config);
        $once = $apache->insertInto('', $this->snippet);
        $twice = $apache->insertInto($once, $this->snippet);

        expect(substr_count($twice, ApacheSnippet::BEGIN))->toBe(1);
    });

    it("leaves WordPress's permalink block exactly where it was", function () {
        // A generated block that moved or rewrote it would break every URL on the site
        // the first time somebody ran the command on a working install.
        $existing = "# BEGIN WordPress\nRewriteEngine On\n# END WordPress\n";

        $merged = new ApacheSnippet($this->config)->insertInto($existing, $this->snippet);

        expect($merged)->toContain("# BEGIN WordPress\nRewriteEngine On\n# END WordPress");
        expect(strpos($merged, ApacheSnippet::BEGIN))->toBeLessThan(strpos($merged, '# BEGIN WordPress'));
    });

    it('ships the permalink rules for a project that has none', function () {
        expect(ApacheSnippet::wordPressBlock())
            ->toContain('# BEGIN WordPress')
            ->toContain('RewriteRule . /index.php [L]');
    });
});
