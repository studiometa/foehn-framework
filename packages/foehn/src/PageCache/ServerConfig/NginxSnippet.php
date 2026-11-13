<?php

declare(strict_types=1);

namespace Studiometa\Foehn\PageCache\ServerConfig;

use InvalidArgumentException;
use Studiometa\Foehn\Config\PageCacheConfig;
use Studiometa\Foehn\PageCache\BucketStore;
use Studiometa\Foehn\Views\Sections\SectionRequest;

/**
 * The nginx configuration that serves a stored page without starting PHP at all.
 *
 * Generated, never hand-written, because it bakes in policy only the loaded config
 * knows — which cookies bypass, which query args are ignored — and because `if` inside
 * `location` is sharp enough that a half-edited copy is a real hazard.
 *
 * Three things about the shape are worth knowing before editing this class.
 *
 * **The rules are server-level, not wrapped in a `location`.** A flag is decided by a
 * sequence of `set`/`if` in the server rewrite phase, and one `rewrite … last` sends the
 * request at the stored file. This is the shape of the `prod-wp-rocket.conf` that
 * `studiometa/wordpress-project` has run in ddev and in production for years, and it is
 * what makes the include compose: it declares no `location /`, so nothing has to be
 * removed from the site's own configuration and misses fall through to whatever front
 * controller block is already there.
 *
 * **`set` inside `if` is why it has to be server-level.** Building a canonical filename
 * from the keyed query args needs `set`, and inside a `location` a matched `if` continues
 * in an implicit location that inherits no content handler — a `try_files` there is
 * silently skipped, so every request carrying `?utm_source=` falls through to PHP while
 * still looking like it worked. That was a real bug in this file's first version. At
 * server level, `set` under `if` is the documented behaviour of the rewrite module rather
 * than the trap it is in a location.
 *
 * **A stored 404 is never served from here.** This snippet only ever builds
 * `index.html` and `index__variant.html`, and a 404 is stored as
 * `index__variant--404.html` — so nginx simply does not find it and the request goes to
 * PHP, which answers with the right status. nginx cannot set a status on a static
 * response without `error_page`, and this arrangement means it never has to. The same
 * goes for the headers a page recorded: the drop-in replays them, this does not, and
 * both send the freshness headers below. The one exception is the `X-Robots-Tag` a
 * section response carries, which is derived from the request rather than replayed —
 * a cached fragment that lost its `noindex` would be indexed as if it were a page.
 *
 * **The stored files are unreachable from outside.** They are served through an internal
 * redirect into a `location ^~` marked `internal`, so no visitor can request a cache file
 * by name, and a `.php` written under that directory by any cache is unreachable as well
 * as unexecutable.
 *
 * What it cannot do: enforce a TTL. nginx has no way to look at a file's age, which is why
 * {@see \Studiometa\Foehn\PageCache\Sweeper} is in v1 rather than deferred.
 */
final readonly class NginxSnippet
{
    private SnippetPolicy $policy;

    public function __construct(PageCacheConfig $config)
    {
        $this->policy = new SnippetPolicy($config);
    }

    /**
     * The snippet, or null when the configured cache path is not under the document root.
     *
     * @throws InvalidArgumentException In bucket storage, when the bucket cannot be reached
     *     from the `S3_UPLOADS_*` variables.
     */
    public function render(): ?string
    {
        if ($this->policy->storesInBucket()) {
            return $this->renderBucket();
        }

        $cache = $this->policy->cacheUrlPath();

        if ($cache === null) {
            return null;
        }

        $cacheControl = $this->policy->config->getCacheControl();
        $responseHeaders = $this->responseHeaders();
        $cacheRoot = dirname($cache);
        $header = $this->header();
        $variant = $this->variant();
        $rules = $this->rules();

        return <<<NGINX
            {$header}

            {$variant}

            # `\$uri` interpolated whole, never through a regex capture. `\$uri` itself is
            # decoded, which is what makes an accented permalink resolve to the file PHP
            # wrote — but `\$1` from `if (\$uri ~ …)` comes back percent-encoded, so deriving
            # the path that way looked correct and quietly missed every non-ASCII URL. Two
            # candidates instead of one capture: WordPress may or may not put a trailing
            # slash on a permalink, and /foo and /foo/ are one page and one file.
            set \$foehn_url "{$cache}/\$host\${uri}index\$foehn_variant.html";

            if (!-f "\$document_root\$foehn_url") {
                set \$foehn_url "{$cache}/\$host\${uri}/index\$foehn_variant.html";
            }
            if (!-f "\$document_root\$foehn_url") {
                set \$foehn_bypass 0;
            }
            {$rules}

            if (\$foehn_bypass = 1) {
                rewrite ^ "\$foehn_url" last;
            }

            # Nothing under the cache directory is reachable from outside. `^~` beats every
            # regex location, including the FastCGI one, so a .php file written there by
            # any cache is unreachable as well as unexecutable, and `internal` means only
            # the rewrite above can get in.
            #
            # The headers live here rather than at server level so that a miss carries no
            # header from nginx at all: PHP emits its own MISS or BYPASS with a reason, and
            # two writers of one header name produce a response with both.
            #
            # A location with its own `add_header` drops every `add_header` of the server
            # level, so a hit has these headers and no other. The `responseHeaders` of the
            # configuration come last.
            location ^~ {$cacheRoot}/ {
                internal;
                etag on;
                add_header X-Foehn-Cache HIT;
                add_header X-Foehn-Cache-Via nginx;
                add_header Cache-Control "{$cacheControl}";
                add_header Vary "Cookie, Accept-Encoding";
                # Empty for a page, so nginx sends nothing. See the `set` above.
                add_header X-Robots-Tag \$foehn_robots;{$responseHeaders}
            }
            NGINX;
    }

    /**
     * The bucket variant: the same rules, then a proxy to the object instead of a file.
     *
     * nginx cannot test whether an object exists, so there is no `-f`: every request the
     * rules accept goes to the bucket, and a missing object — 403 from a bucket that
     * refuses anonymous listing, 404 from one that allows it — goes to PHP through
     * `error_page`, as `/_image/` does. PHP renders the page and stores it.
     *
     * A bucket that fails or cannot be reached also goes to PHP. PHP renders a page
     * without the bucket, so a storage outage makes the site slower, not down.
     *
     * @throws InvalidArgumentException When the bucket cannot be reached from the
     *     `S3_UPLOADS_*` variables.
     */
    private function renderBucket(): string
    {
        $origin = BucketOrigin::fromEnvironment();

        if ($origin === null) {
            throw new InvalidArgumentException(
                'Bucket storage needs S3_UPLOADS_BUCKET, and S3_UPLOADS_ENDPOINT or S3_UPLOADS_PROXY_HOST, '
                . 'with values nginx can read.',
            );
        }

        $cacheControl = $this->policy->config->getCacheControl();
        $responseHeaders = $this->responseHeaders();
        $header = $this->header();
        $variant = $this->variant();
        $rules = $this->rules();
        $object = $origin->prefix . '/' . BucketStore::PREFIX;

        return <<<NGINX
            {$header}
            #
            # Bucket storage: hits are proxied from {$origin->scheme}://{$origin->host}{$origin->prefix}.

            {$variant}

            # The object key PHP wrote, from `\$uri` whole for the reason the disk variant
            # gives. /foo and /foo/ are one page and one object.
            set \$foehn_key "\$host\${uri}index\$foehn_variant.html";
            if (\$uri !~ "/\$") {
                set \$foehn_key "\$host\${uri}/index\$foehn_variant.html";
            }
            {$rules}

            if (\$foehn_bypass = 1) {
                rewrite ^ /_foehn_page_cache last;
            }

            # Only the rewrite above can get in. The headers live here for the reasons the
            # disk variant gives.
            location = /_foehn_page_cache {
                internal;

                # A variable, so nginx resolves the host per request and still starts when
                # the storage provider does not answer. Set before the rewrite, which stops
                # the rewrite module for this location.
                set \$foehn_page_upstream "{$origin->scheme}://{$origin->host}";

                # Without a `?`, so the query string goes with it: the miss below hands it
                # to PHP. The bucket ignores the arguments the rules let through.
                rewrite ^ "{$object}/\$foehn_key" break;

                proxy_pass \$foehn_page_upstream;
                proxy_set_header Host {$origin->host};
                proxy_set_header Authorization "";
                proxy_set_header Cookie "";
                proxy_ssl_server_name on;

                resolver {$origin->resolver} valid=300s;
                resolver_timeout 5s;

                proxy_hide_header x-amz-id-2;
                proxy_hide_header x-amz-request-id;
                proxy_hide_header x-amz-version-id;
                proxy_hide_header x-amz-server-side-encryption;
                proxy_hide_header x-amz-storage-class;
                proxy_hide_header Cache-Control;
                proxy_hide_header Set-Cookie;

                # A page not stored yet is a miss, not an error. A bucket that fails or
                # does not answer is a miss too: PHP can render the page without it.
                proxy_intercept_errors on;
                error_page 403 404 500 502 503 504 = @foehn_page_miss;

                add_header X-Foehn-Cache HIT;
                add_header X-Foehn-Cache-Via nginx;
                add_header Cache-Control "{$cacheControl}";
                add_header Vary "Cookie, Accept-Encoding";
                # Empty for a page, so nginx sends nothing. See the `set` above.
                add_header X-Robots-Tag \$foehn_robots;{$responseHeaders}
            }

            # PHP renders the page. WordPress reads the original request URI, and the query
            # string survives a named location.
            location @foehn_page_miss {
                rewrite ^ /index.php last;
            }
            NGINX;
    }

    /**
     * The comment block both variants start with.
     */
    private function header(): string
    {
        $hash = $this->policy->hash();

        return <<<NGINX
            # Foehn page cache — generated by `wp foehn cache:config --server=nginx`.
            # DO NOT EDIT. Regenerate after changing app/page-cache*.config.php.
            # policy: {$hash}
            #
            # Include this inside your `server { }` block. It composes with a stock
            # WordPress `location /` — nothing has to be removed.
            NGINX;
    }

    /**
     * The statements that build `$foehn_variant` from the keyed query args.
     */
    private function variant(): string
    {
        // Never empty, whatever a project configured: `foehn_sections` is keyed on every
        // configuration, so there is always at least one arg to unroll.
        $canonical = $this->policy->canonicalQueryStatements();

        return <<<NGINX
            set \$foehn_bypass 1;

            # The keyed query args, in the configuration's canonical order. Each one is read
            # in both its spellings: `genre=rock,jazz` through `\$arg_genre`, and the
            # `genre[]=rock&genre[]=jazz` a checkbox group posts by capturing the members
            # out of `\$args` one slot at a time and joining them the way PHP does. A shape
            # nginx cannot join the way PHP would — an empty member, more members than
            # slots, a member with a comma in it, both spellings at once — is a bypass to
            # the drop-in, which serves the same file a little more slowly.
            set \$foehn_q "";
            {$canonical}
            set \$foehn_variant "";
            if (\$foehn_q != "") {
                set \$foehn_variant "__\$foehn_q";
            }
            NGINX;
    }

    /**
     * The bypass rules both variants share, and the `X-Robots-Tag` of a section.
     */
    private function rules(): string
    {
        $cookies = $this->policy->cookiePattern();
        $args = $this->policy->knownQueryPattern();
        $maintenance = $this->policy->maintenanceUrlPath();
        $section = SectionRequest::PARAMETER;
        $repeated = $this->policy->repeatedQueryStatements();

        return <<<NGINX
            if (\$request_method != GET) {
                set \$foehn_bypass 0;
            }
            # Every arg must be one this cache was told about — ignored or keyed. Order
            # does not enter into it; anything unknown is PHP's decision to make.
            if (\$args !~ "{$args}") {
                set \$foehn_bypass 0;
            }
            {$repeated}
            if (\$http_cookie ~* "({$cookies})") {
                set \$foehn_bypass 0;
            }
            if (-f "\$document_root{$maintenance}") {
                set \$foehn_bypass 0;
            }

            # A section response is a fragment of a page and must never be indexed on its
            # own. The drop-in replays the headers a response recorded; nginx has no way to
            # read them, so the one header that matters is derived from the request here —
            # from the joined value rather than from `\$arg_{$section}`, so that a section
            # request in the bracketed spelling is served with it too.
            #
            # A variable rather than an `add_header` under this `if`: `add_header` is not
            # allowed in a server-level `if` at all, and nginx omits a header whose value
            # is the empty string — which is what makes one unconditional `add_header` in
            # the location below behave conditionally.
            set \$foehn_robots "";
            if (\$foehn_val_{$section} != "") {
                set \$foehn_robots "noindex, nofollow";
            }
            NGINX;
    }

    /**
     * The `add_header` lines of the configured `responseHeaders`, each on its own line.
     *
     * Empty when there are none. The values are safe between double quotes:
     * {@see PageCacheConfig::getResponseHeaders()} refuses the characters that would end
     * the string or start a variable.
     */
    private function responseHeaders(): string
    {
        $lines = '';

        foreach ($this->policy->config->getResponseHeaders() as $name => $value) {
            $lines .= sprintf("\n    add_header %s \"%s\";", $name, $value);
        }

        return $lines;
    }

    /**
     * The `# policy:` line a generated snippet carries, for comparing against a file.
     */
    public function hash(): string
    {
        return $this->policy->hash();
    }
}
