<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\PageCacheConfig;
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
        expect($policy->canonicalQueryStatements())->toContain('set $foehn_q "${foehn_q}foehn_sections=$arg_foehn_sections&";');
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
    ]);

    it('unrolls the keyed args in the configuration order, not the request order', function () {
        // The one property that makes ?page=2&lang=fr and ?lang=fr&page=2 one file: every
        // reader walks this list, and nginx's $arg_name does not care where the arg was.
        $statements = new SnippetPolicy(
            new PageCacheConfig(cacheQueryArgs: ['page', 'lang']),
        )->canonicalQueryStatements();

        expect(strpos($statements, 'lang=$arg_lang&'))->toBeLessThan((int) strpos($statements, 'page=$arg_page&'));
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
            ->toContain('if ($arg_page != "") { set $foehn_arg_page "invalid"; }')
            ->toContain('if ($arg_page ~ "^[0-9]+$") { set $foehn_arg_page "valid"; }')
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
        ]))->canonicalQueryStatements())->toContain('if ($arg_lang ~ "[^A-Za-z0-9_.,\-]|^.{65,}$") { set $foehn_arg_lang "invalid"; }');
    });

    it('lets a comma through the floor, so a multi-value filter can be keyed', function () {
        // The comma is the separator between the values of one filter, and nginx reads
        // `?genre=rock,jazz` with `$arg_genre` like any other value. Without it in the
        // floor the snippet called every multi-value filter invalid and bypassed it.
        $statements = new SnippetPolicy(new PageCacheConfig(cacheQueryArgs: [
            'genre' => '^[a-z0-9-]+(?:,[a-z0-9-]+)*$',
        ]))->canonicalQueryStatements();

        expect($statements)
            ->toContain('if ($arg_genre ~ "^[a-z0-9-]+(?:,[a-z0-9-]+)*$") { set $foehn_arg_genre "valid"; }')
            ->and($statements)
            ->toContain('set $foehn_q "${foehn_q}genre=$arg_genre&";');
    });

    it('never keys a bracketed name, so the readers cannot disagree about one', function () {
        // nginx has no `$arg_genre[]` — a variable name may not hold brackets. So the
        // bracketed form must fail `knownQueryPattern()` and be passed to PHP, which
        // joins the members and serves the same file from the drop-in. What must never
        // happen is nginx reading `$arg_genre`, finding it empty and serving the
        // unfiltered page to somebody who asked for a filtered one.
        $policy = new SnippetPolicy(new PageCacheConfig(cacheQueryArgs: ['genre' => '^[a-z,]+$']));

        expect((bool) preg_match('#' . $policy->knownQueryPattern() . '#', 'genre=rock,jazz'))
            ->toBeTrue()
            ->and((bool) preg_match('#' . $policy->knownQueryPattern() . '#', 'genre[]=rock&genre[]=jazz'))
            ->toBeFalse();
    });

    it('bypasses a keyed arg that appears twice, which the readers read differently', function () {
        $policy = new SnippetPolicy(new PageCacheConfig(cacheQueryArgs: ['page']));

        expect($policy->repeatedQueryStatements())->toContain('if ($args ~ "(?:^|&)page=[^&]*&(?:.*&)?page=")');
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
            ->toContain('if ($arg_page ~ "^[0-9]{1,6}$") { set $foehn_arg_page "valid"; }')
            ->toContain('if ($foehn_arg_page = "valid") { set $foehn_q "${foehn_q}page=$arg_page&"; }')
            ->toContain('set $foehn_variant "__$foehn_q";');
    });

    it('keys a section request even when the project keyed nothing', function () {
        // The default configuration keys one arg, so there is always something to unroll.
        expect($this->snippet)
            ->toContain('if ($arg_foehn_sections ~ "' . SectionRequest::VALUE_PATTERN . '")')
            ->toContain('set $foehn_q "${foehn_q}foehn_sections=$arg_foehn_sections&";');
    });

    it('keeps a cached fragment out of the index, which nginx cannot replay', function () {
        // The drop-in replays the `X-Robots-Tag` a section response recorded; nginx has no
        // way to read a stored header, so it derives the one that matters from the
        // request. A variable rather than an `add_header` under the `if`: `add_header` is
        // not allowed in a server-level `if`, and nginx omits a header whose value is
        // empty — which is what makes one unconditional `add_header` behave conditionally.
        expect($this->snippet)
            ->toContain('set $foehn_robots "";')
            ->toContain('if ($arg_foehn_sections != "") {')
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
