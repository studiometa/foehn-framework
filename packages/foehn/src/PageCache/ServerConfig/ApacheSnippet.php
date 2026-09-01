<?php

declare(strict_types=1);

namespace Studiometa\Foehn\PageCache\ServerConfig;

use Studiometa\Foehn\Config\PageCacheConfig;

/**
 * The `.htaccess` block that serves a stored page without starting PHP.
 *
 * Marker-delimited, so a project's own rules can live in the same file and a regenerated
 * block replaces only itself.
 *
 * The rewrite matches on `$1` from the pattern rather than on `%{REQUEST_URI}`, because
 * `$1` is the **decoded** path — the same string nginx's `$uri` and PHP's
 * `rawurldecode()` produce. `%{REQUEST_URI}` still holds the percent escapes a browser
 * sent, so a rule written against it would miss every accented permalink while appearing
 * to work on an English site.
 *
 * The starter ships no `.htaccess` at all and `DISALLOW_FILE_MODS` stops WordPress
 * writing one, so the generated file carries WordPress's own permalink block too —
 * otherwise installing this would break every URL on the site.
 *
 * Like nginx, `mod_rewrite` cannot check a file's age, so the TTL belongs to the sweep.
 */
final readonly class ApacheSnippet
{
    public const BEGIN = '# BEGIN Foehn Page Cache — generated, do not edit';
    public const END = '# END Foehn Page Cache';

    private SnippetPolicy $policy;

    public function __construct(PageCacheConfig $config)
    {
        $this->policy = new SnippetPolicy($config);
    }

    /**
     * The block, or null when the configured cache path is not under the document root.
     */
    public function render(): ?string
    {
        $cache = $this->policy->cacheUrlPath();

        if ($cache === null) {
            return null;
        }

        $cookies = $this->policy->cookiePattern();
        // Ignorable args only, never the keyed ones. mod_rewrite cannot assemble a
        // canonical filename from %{QUERY_STRING} — that would take a rule per
        // permutation — and serving the unkeyed `index.html` for `?page=2` would hand a
        // visitor page one. So a request carrying a keyed arg falls through to PHP, where
        // the drop-in serves the right file a few milliseconds later.
        //
        // That is also why this block needs no counterpart to the nginx `X-Robots-Tag`
        // rule. `foehn_sections` is a keyed arg, so a section request is one Apache never
        // answers; the drop-in does, and it replays the `noindex` the response recorded.
        $args = $this->policy->ignorableQueryPattern();
        $maintenance = $this->policy->maintenanceUrlPath();
        $hash = $this->policy->hash();
        $cacheRoot = ltrim(dirname($cache), '/');
        $begin = self::BEGIN;
        $end = self::END;

        return <<<APACHE
            {$begin}
            # policy: {$hash}
            <IfModule mod_rewrite.c>
                RewriteEngine On
                RewriteBase /

                RewriteCond %{REQUEST_METHOD} =GET
                # A query string made only of ignored args is the same page as no query at
                # all, in whatever order the args arrive. The pattern matches an absent
                # query string too, so this is one condition rather than a conjunction.
                RewriteCond %{QUERY_STRING} {$args}
                RewriteCond %{HTTP:Cookie} !({$cookies}) [NC]
                # WordPress writes .maintenance to ABSPATH, which is not the document root
                # in this layout — testing the wrong one keeps the cache serving all
                # through a core update.
                RewriteCond %{DOCUMENT_ROOT}{$maintenance} !-f
                # \$1 rather than %{REQUEST_URI}: \$1 is the decoded path, which is the
                # string nginx and PHP both key on.
                RewriteCond %{DOCUMENT_ROOT}{$cache}/%{HTTP_HOST}/\$1/index.html -f
                RewriteRule ^(.*?)/?\$ {$cache}/%{HTTP_HOST}/\$1/index.html [L]
            </IfModule>

            <IfModule mod_headers.c>
                <FilesMatch "\\.html\$">
                    Header set X-Foehn-Cache "HIT"
                    Header set X-Foehn-Cache-Via "apache"
                    Header set Vary "Cookie, Accept-Encoding"
                </FilesMatch>
            </IfModule>

            # Nothing under the cache directory is ever executable, and directory listings
            # would publish the shape of every cached URL.
            Options -Indexes

            <FilesMatch "^.*\\.php\$">
                <If "%{REQUEST_URI} =~ m#^/{$cacheRoot}/#">
                    Require all denied
                </If>
            </FilesMatch>
            {$end}
            APACHE;
    }

    /**
     * The `# policy:` line a generated block carries, for comparing against a file.
     */
    public function hash(): string
    {
        return $this->policy->hash();
    }

    /**
     * Replace the block in an existing `.htaccess`, or append it.
     *
     * WordPress's own permalink block is left exactly where it is. A generated block that
     * moved or rewrote it would break every URL on the site the first time somebody ran
     * the command on a working install.
     */
    public function insertInto(string $htaccess, string $block): string
    {
        $pattern = '/' . preg_quote(self::BEGIN, '/') . '.*?' . preg_quote(self::END, '/') . '/s';

        if (preg_match($pattern, $htaccess) === 1) {
            return (string) preg_replace($pattern, $block, $htaccess, 1);
        }

        // Before WordPress's block, so a cache hit never pays for the permalink rewrite.
        $wordpress = strpos($htaccess, '# BEGIN WordPress');

        if ($wordpress !== false) {
            return substr($htaccess, 0, $wordpress) . $block . "\n\n" . substr($htaccess, $wordpress);
        }

        return rtrim($htaccess) === '' ? $block . "\n" : rtrim($htaccess) . "\n\n" . $block . "\n";
    }

    /**
     * WordPress's own permalink rules, for a project whose `.htaccess` has none.
     */
    public static function wordPressBlock(): string
    {
        return <<<'APACHE'
            # BEGIN WordPress
            <IfModule mod_rewrite.c>
                RewriteEngine On
                RewriteBase /
                RewriteRule ^index\.php$ - [L]
                RewriteCond %{REQUEST_FILENAME} !-f
                RewriteCond %{REQUEST_FILENAME} !-d
                RewriteRule . /index.php [L]
            </IfModule>
            # END WordPress
            APACHE;
    }
}
