<?php

declare(strict_types=1);

namespace Studiometa\Foehn\PageCache\ServerConfig;

use Studiometa\Foehn\Config\PageCacheConfig;
use Studiometa\Foehn\PageCache\QueryKey;

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
        $ignored = $this->config->getIgnoredQueryArgs();

        if ($ignored === []) {
            // Only an absent query string is ignorable, so every one of them is a bypass.
            return '^$';
        }

        $names = implode('|', array_map(self::quote(...), $ignored));

        return '^(?:(?:' . $names . ')(?:=[^&]*)?(?:&|$))*$';
    }

    /**
     * One `if`/`set` pair per keyed query arg, in the configuration's canonical order.
     *
     * This is the unrolled form of {@see \Studiometa\Foehn\PageCache\QueryKey::canonical()}:
     * the loop PHP runs over the sorted arg list becomes a fixed sequence of statements,
     * and `$arg_name` is independent of where the arg appeared in the query string. That
     * is the whole trick behind `?page=2&lang=fr` and `?lang=fr&page=2` reaching one file
     * without nginx being able to sort anything.
     */
    public function canonicalQueryStatements(): string
    {
        $floor = sprintf('[^%s]|^.{%d,}$', QueryKey::VALUE_CHARACTER_CLASS, QueryKey::VALUE_MAX_LENGTH + 1);

        $lines = [];

        foreach ($this->config->getCacheQueryArgs() as $name => $pattern) {
            // Six statements where PHP needs two, because nginx has no `and` and therefore
            // no way to say "present *and* invalid" in one condition. A sentinel says it
            // instead, and the order is the logic: empty unless present, valid if the
            // pattern matches, invalid again if the value is not one a filename may hold.
            //
            // The alternative — falling back to the unkeyed file when a value does not
            // validate — is what this replaced, and it served the unpaginated page to
            // anyone who asked for `?page=abc`.
            $lines[] = sprintf('set $foehn_arg_%s "empty";', $name);
            $lines[] = sprintf('if ($arg_%s != "") { set $foehn_arg_%s "invalid"; }', $name, $name);
            $lines[] = sprintf('if ($arg_%s ~ "%s") { set $foehn_arg_%s "valid"; }', $name, $pattern, $name);
            $lines[] = sprintf('if ($arg_%s ~ "%s") { set $foehn_arg_%s "invalid"; }', $name, $floor, $name);
            $lines[] = sprintf(
                'if ($foehn_arg_%s = "valid") { set $foehn_q "${foehn_q}%s=$arg_%s&"; }',
                $name,
                $name,
                $name,
            );
            $lines[] = sprintf('if ($foehn_arg_%s = "invalid") { set $foehn_bypass 0; }', $name);
        }

        return implode("\n", $lines);
    }

    /**
     * One bypass per keyed query arg that appears twice.
     *
     * nginx's `$arg_page` is the first `page=` in the query string, PHP's `$_GET['page']`
     * the last. `?page=1&page=2` has no answer both readers would give, so it gets none.
     */
    public function repeatedQueryStatements(): string
    {
        $lines = [];

        foreach (array_keys($this->config->getCacheQueryArgs()) as $name) {
            $quoted = self::quote($name);

            $lines[] = sprintf('if ($args ~ "(?:^|&)%s=[^&]*&(?:.*&)?%s=") { set $foehn_bypass 0; }', $quoted, $quoted);
        }

        return implode("\n", $lines);
    }

    /**
     * A regex matching a query string made only of args this cache has been told about.
     *
     * Ignored **and** keyed: an ignored arg leaves the key alone and a keyed one changes
     * the filename, but both are args nginx knows how to serve. Anything else is a
     * bypass. Apache gets {@see SnippetPolicy::ignorableQueryPattern()} instead, because
     * it cannot build a keyed filename and must not serve the unkeyed one in its place.
     */
    public function knownQueryPattern(): string
    {
        $names = [...$this->config->getIgnoredQueryArgs(), ...array_keys($this->config->getCacheQueryArgs())];

        if ($names === []) {
            return '^$';
        }

        return '^(?:(?:' . implode('|', array_map(self::quote(...), $names)) . ')(?:=[^&]*)?(?:&|$))*$';
    }

    /**
     * The `.maintenance` file, as a path under the document root.
     *
     * WordPress writes it to `ABSPATH`, which in this layout is `web/wp/` rather than the
     * document root — so a snippet testing `$document_root/.maintenance` would keep
     * serving cached pages all through a core update, while PHP correctly refused to.
     */
    public function maintenanceUrlPath(): string
    {
        $root = self::documentRoot();

        if ($root === null || !defined('ABSPATH')) {
            return '/.maintenance';
        }

        $abspath = rtrim((string) constant('ABSPATH'), '/');

        if (!str_starts_with($abspath, $root)) {
            return '/.maintenance';
        }

        return substr($abspath, strlen($root)) . '/.maintenance';
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
                $this->config->getIgnoredQueryArgs(),
                $this->config->getCacheQueryArgs(),
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
