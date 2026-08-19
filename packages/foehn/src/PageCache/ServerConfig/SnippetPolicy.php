<?php

declare(strict_types=1);

namespace Studiometa\Foehn\PageCache\ServerConfig;

use Studiometa\Foehn\Config\PageCacheConfig;

/**
 * The parts of a page-cache policy a web server can express.
 *
 * Shared by the nginx and Apache generators so the two snippets cannot state different
 * rules from each other, or from the PHP that wrote the file they read. Config drift
 * between the readers is the risk this whole design is arranged around, and generating
 * both from one place is the mitigation.
 */
final readonly class SnippetPolicy
{
    public function __construct(
        public PageCacheConfig $config,
    ) {}

    /**
     * The cache root as a URL path under the document root, or null when it is not one.
     *
     * A project that pointed `path` outside the web root has a cache no web server can
     * reach by filename. That is a legitimate choice — the drop-in still serves it — but
     * there is no snippet to generate for it, and saying so beats generating a broken one.
     */
    public function cacheUrlPath(): ?string
    {
        $root = self::documentRoot();
        $path = $this->config->getPath();

        if ($root === null || !str_starts_with($path, $root . '/')) {
            return null;
        }

        return substr($path, strlen($root));
    }

    /**
     * The document root, which for a WordPress in a subdirectory is above `ABSPATH`.
     */
    public static function documentRoot(): ?string
    {
        if (!defined('ABSPATH')) {
            return null;
        }

        return dirname(rtrim((string) constant('ABSPATH'), '/'));
    }

    /**
     * A regex alternation of the cookie prefixes that mean "not an anonymous visitor".
     */
    public function cookiePattern(): string
    {
        return implode('|', array_map(self::quote(...), $this->config->bypassCookies));
    }

    /**
     * A regex matching a query string the cache may ignore in its entirety.
     *
     * Matches the empty query string too, which is what makes it usable as a single
     * positive condition: "this request's query string does not change which page it
     * is". Both generators then need one test rather than a conjunction, and nginx has
     * no `and`.
     *
     * Order-independent, because a query string's args arrive in whatever order a link
     * was written in and PHP's own check does not care either. The trailing `(?:&|$)` is
     * what stops `utm_source` matching the front of `utm_sourcex`, which would serve the
     * no-query page for a URL that meant something else.
     */
    public function ignorableQueryPattern(): string
    {
        if ($this->config->ignoredQueryArgs === []) {
            // Only an absent query string is ignorable, so every one of them is a bypass.
            return '^$';
        }

        $names = implode('|', array_map(self::quote(...), $this->config->ignoredQueryArgs));

        return '^(?:(?:' . $names . ')(?:=[^&]*)?(?:&|$))*$';
    }

    /**
     * A short hash of the policy, so `cache:status` can spot a snippet left behind.
     */
    public function hash(): string
    {
        return substr(
            sha1((string) json_encode([
                $this->cacheUrlPath(),
                $this->config->bypassCookies,
                $this->config->ignoredQueryArgs,
                $this->config->cacheNotFound,
                $this->config->browserMaxAge,
            ])),
            0,
            12,
        );
    }

    /**
     * Escape the characters a regex would otherwise read as syntax.
     *
     * A config value is a cookie prefix or a query arg name, not a pattern, and
     * `wp-postpass_` already contains a `-`. Left unescaped, a config value would be
     * able to rewrite the generated rules.
     */
    private static function quote(string $value): string
    {
        return (string) preg_replace('/([.\\\\+*?\[\]^$(){}=!<>|:\-#\/])/', '\\\\$1', $value);
    }
}
