<?php

declare(strict_types=1);

namespace Studiometa\Foehn\PageCache\ServerConfig;

use Studiometa\Foehn\Config\PageCacheConfig;
use Studiometa\Foehn\PageCache\QueryKey;
use Studiometa\Foehn\Views\Sections\SectionRequest;

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
    /**
     * The `[]` suffix of a bracketed arg, as a regex, in both spellings a browser sends.
     *
     * A form serialises `genre[]` literally or percent-escapes it to `genre%5B%5D`, and
     * neither reader decodes the query string, so both have to be recognised as written.
     * Mirrors {@see QueryKey}'s own reading of a bracketed name, hex case included.
     */
    private const BRACKETS = '(?:\\[\\]|%5[Bb]%5[Dd])';

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
     * How many members of a bracketed arg nginx will join before it declines.
     *
     * PHP has no such bound: it joins whatever arrives and lets the 64-character floor
     * refuse the result. nginx has no loop, so every member it can read is one more
     * statement in the snippet and one more regex pass over `$args` on every request —
     * and the bound has to be drawn somewhere. Five is the most members any comma list
     * the framework itself emits can hold ({@see SectionRequest::MAX_SECTIONS}), so a
     * section request in either spelling is always within reach of nginx; a checkbox
     * facet with more boxes ticked than that falls through to the drop-in, which serves
     * the same file two milliseconds later.
     */
    public const MEMBER_SLOTS = SectionRequest::MAX_SECTIONS;

    /**
     * The statements per keyed query arg, in the configuration's canonical order.
     *
     * This is the unrolled form of {@see \Studiometa\Foehn\PageCache\QueryKey::canonical()}:
     * the loop PHP runs over the sorted arg list becomes a fixed sequence of statements,
     * and `$arg_name` is independent of where the arg appeared in the query string. That
     * is the whole trick behind `?page=2&lang=fr` and `?lang=fr&page=2` reaching one file
     * without nginx being able to sort anything.
     *
     * Each arg has two spellings and nginx reads them differently. The bare `genre=` is
     * `$arg_genre`. The bracketed `genre[]=rock&genre[]=jazz` — what a checkbox group
     * posts — has no variable at all, because a variable name may not hold brackets, so
     * its members are read out of `$args` one regex capture at a time and joined with
     * commas in request order, which is exactly the join PHP performs. Both spellings end
     * up in one `$foehn_val_genre`, and the validation that follows does not know which
     * one it was given.
     *
     * Every shape nginx cannot join the way PHP would is a **decline** — a bypass to the
     * drop-in, which computes the key in PHP and serves the same file. Declining is the
     * safe direction: a wrong key serves one visitor another's page, a decline costs two
     * milliseconds. See {@see SnippetPolicy::memberStatements()} for the shapes.
     */
    public function canonicalQueryStatements(): string
    {
        $floor = sprintf('[^%s]|^.{%d,}$', QueryKey::VALUE_CHARACTER_CLASS, QueryKey::VALUE_MAX_LENGTH + 1);

        $lines = [];

        foreach ($this->config->getCacheQueryArgs() as $name => $pattern) {
            $lines[] = sprintf('# %s — the bare spelling, or the members of the bracketed one joined.', $name);
            $lines[] = sprintf('set $foehn_val_%s $arg_%s;', $name, $name);
            $lines = [...$lines, ...$this->memberStatements($name)];

            // Six statements where PHP needs two, because nginx has no `and` and therefore
            // no way to say "present *and* invalid" in one condition. A sentinel says it
            // instead, and the order is the logic: empty unless present, valid if the
            // pattern matches, invalid again if the value is not one a filename may hold.
            //
            // The alternative — falling back to the unkeyed file when a value does not
            // validate — is what this replaced, and it served the unpaginated page to
            // anyone who asked for `?page=abc`.
            //
            // Run on the joined value, so the 64-character floor caps the members as a
            // whole — which is the cap PHP applies to them too.
            $lines[] = sprintf('set $foehn_arg_%s "empty";', $name);
            $lines[] = sprintf('if ($foehn_val_%s != "") { set $foehn_arg_%s "invalid"; }', $name, $name);
            $lines[] = sprintf('if ($foehn_val_%s ~ "%s") { set $foehn_arg_%s "valid"; }', $name, $pattern, $name);
            $lines[] = sprintf('if ($foehn_val_%s ~ "%s") { set $foehn_arg_%s "invalid"; }', $name, $floor, $name);
            $lines[] = sprintf(
                'if ($foehn_arg_%s = "valid") { set $foehn_q "${foehn_q}%s=$foehn_val_%s&"; }',
                $name,
                $name,
                $name,
            );
            $lines[] = sprintf('if ($foehn_arg_%s = "invalid") { set $foehn_bypass 0; }', $name);
        }

        return implode("\n", $lines);
    }

    /**
     * The statements that read the bracketed spelling of one keyed arg out of `$args`.
     *
     * One regex per member slot. The k-th capture is anchored on the first occurrence
     * and skips forward k-1 times with `[^&]*&(?:[^&]*&)*?name[]=` — "finish this value,
     * step over whole pairs, land on the next occurrence". Leftmost-first matching pins
     * the anchor to the first occurrence and the lazy skip to the nearest next one, so
     * the capture is the k-th member in request order and nothing else. The pairs are
     * consumed whole and `[^&]*` cannot cross a separator, so there is no ambiguity for
     * the engine to backtrack through.
     *
     * Then the declines, each one a shape PHP keys but nginx cannot key the same way,
     * or one PHP refuses that a join here would have accepted:
     *
     * - **more members than there are slots** — nginx would silently drop the rest;
     * - **an empty member** — PHP skips it, and a fixed sequence of captures cannot;
     * - **a member outside {@see QueryKey::MEMBER_CHARACTER_CLASS}** — PHP refuses it,
     *   and the class is derived rather than restated: `?genre[]=rock,jazz` asks for one
     *   term with a comma in its slug, and a join would key it where `?genre=rock,jazz`
     *   lives. A second spelling of a charset is how dd53e71 quietly refused every
     *   multi-value filename;
     * - **both spellings in one URL** — PHP refuses that too.
     *
     * `%5B%5D` is admitted alongside `[]` in any case of the hex, because the query
     * string is never decoded by either reader and a form encoder may write either.
     *
     * @return list<string>
     */
    private function memberStatements(string $name): array
    {
        $quoted = self::quote($name);
        $bracketed = $quoted . self::BRACKETS;
        $next = sprintf('[^&]*&(?:[^&]*&)*?%s=', $bracketed);
        $member = sprintf('[^%s&]', QueryKey::MEMBER_CHARACTER_CLASS);

        $lines = [];

        for ($slot = 0; $slot < self::MEMBER_SLOTS; $slot++) {
            $lines[] = sprintf(
                'if ($args ~ "(?:^|&)%s=%s([^&]*)") { set $foehn_val_%s "%s"; }',
                $bracketed,
                str_repeat($next, $slot),
                $name,
                $slot === 0 ? '$1' : sprintf('${foehn_val_%s},$1', $name),
            );
        }

        $lines[] = sprintf(
            'if ($args ~ "(?:^|&)%s=%s") { set $foehn_bypass 0; }',
            $bracketed,
            str_repeat($next, self::MEMBER_SLOTS),
        );
        $lines[] = sprintf('if ($args ~ "(?:^|&)%s=?(?:&|$)") { set $foehn_bypass 0; }', $bracketed);
        $lines[] = sprintf('if ($args ~ "(?:^|&)%s=[^&]*%s") { set $foehn_bypass 0; }', $bracketed, $member);
        $lines[] = sprintf(
            'if ($args ~ "(?:^|&)(?:%s=[^&]*&(?:[^&]*&)*%s=|%s=[^&]*&(?:[^&]*&)*%s=)") { set $foehn_bypass 0; }',
            $quoted,
            $bracketed,
            $bracketed,
            $quoted,
        );

        return $lines;
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
     *
     * A keyed name is known in both its spellings, `genre` and `genre[]`, since
     * {@see SnippetPolicy::canonicalQueryStatements()} can key either.
     */
    public function knownQueryPattern(): string
    {
        // A keyed name in either spelling, an ignored one only as written: PHP matches an
        // ignored name against the raw `utm_source[]`, which is not `utm_source`, and an
        // argument this cache cannot name is one it does not serve.
        $names = [
            ...array_map(self::quote(...), $this->config->getIgnoredQueryArgs()),
            ...array_map(
                static fn(string $name): string => self::quote($name) . self::BRACKETS . '?',
                array_keys($this->config->getCacheQueryArgs()),
            ),
        ];

        if ($names === []) {
            return '^$';
        }

        return '^(?:(?:' . implode('|', $names) . ')(?:=[^&]*)?(?:&|$))*$';
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
