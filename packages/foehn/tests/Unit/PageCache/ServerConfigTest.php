<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\PageCacheConfig;
use Studiometa\Foehn\PageCache\ServerConfig\ApacheSnippet;
use Studiometa\Foehn\PageCache\ServerConfig\NginxSnippet;
use Studiometa\Foehn\PageCache\ServerConfig\SnippetPolicy;

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
        // nginx has no `and`, and a conjunction would have to be built with `set` —
        // which is the thing that silently breaks try_files.
        expect(preg_match('/' . new SnippetPolicy($this->config)->ignorableQueryPattern() . '/', ''))->toBe(1);
    });

    it('does not match a query string carrying anything else', function () {
        $pattern = new SnippetPolicy($this->config)->ignorableQueryPattern();

        expect(preg_match('/' . $pattern . '/', 'foo=bar'))->toBe(0);
        expect(preg_match('/' . $pattern . '/', 'utm_source=a&s=hello'))->toBe(0);
        // The boundary that stops `utm_source` matching the start of `utm_sourcex`.
        expect(preg_match('/' . $pattern . '/', 'utm_sourcex=a'))->toBe(0);
    });

    it('ignores nothing but an absent query string when the project ignores no args', function () {
        $pattern = new SnippetPolicy(new PageCacheConfig(ignoredQueryArgs: []))->ignorableQueryPattern();

        expect(preg_match('/' . $pattern . '/', 'utm_source=a'))->toBe(0);
        expect(preg_match('/' . $pattern . '/', ''))->toBe(1);
    });

    it('agrees with the PHP writer about which query strings are ignorable', function (string $query) {
        // The whole design turns on the readers keying the same request the same way, so
        // the generated pattern and Bypass::significantQuery() are compared directly.
        $ignorableByPhp = pageCacheBypass($this->config)->significantQuery('/?' . $query) === '';
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
            ->toContain('if (-f "$document_root/.maintenance")');
    });

    it('puts nothing but `return` inside an `if`', function () {
        // The rule that keeps this snippet working. A `set` inside a matching `if` sends
        // the request into an implicit location that does not inherit try_files, so every
        // request carrying ?utm_source= falls through to PHP while appearing to work.
        preg_match_all('/if \([^)]*\) \{(.*?)\}/s', $this->snippet, $matches);

        expect($matches[1])->not->toBeEmpty();

        foreach ($matches[1] as $body) {
            expect(trim($body))->toMatch('/^return \d+;$/');
        }
    });

    it('names every cookie prefix the config bypasses on', function () {
        foreach ($this->config->bypassCookies as $prefix) {
            expect($this->snippet)->toContain(str_replace('-', '\-', $prefix));
        }
    });

    it('branches through error_page 418, because nginx has no else', function () {
        expect($this->snippet)
            ->toContain('error_page 418 = @foehn_miss;')
            ->toContain('recursive_error_pages on;')
            ->toContain('location @foehn_miss {')
            ->toContain('try_files $uri $uri/ /index.php$is_args$args;');
    });

    it('tries both permalink shapes, which nginx cannot normalise itself', function () {
        expect($this->snippet)
            ->toContain('try_files "/wp-content/cache/foehn/pages/$host${uri}index.html"')
            ->toContain('"/wp-content/cache/foehn/pages/$host${uri}/index.html"');
    });

    it('composes with a stock location / instead of replacing it', function () {
        // A regex location wins over a prefix match without colliding with it, which is
        // what lets this be an include rather than a takeover of the site's config.
        expect($this->snippet)->toContain('location ~ "^(?!.*\.php$)"');
        expect($this->snippet)->not->toContain('location / {');
    });

    it('keeps the FastCGI location reachable whatever order the include lands in', function () {
        // The .php guard is in the pattern, so an include placed too early cannot
        // swallow /index.php and take the site down.
        expect($this->snippet)->toContain('(?!.*\.php$)');
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
            ->toContain('RewriteCond %{DOCUMENT_ROOT}/.maintenance !-f');
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
