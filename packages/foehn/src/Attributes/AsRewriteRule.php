<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Attributes;

use Attribute;

/**
 * Register a WordPress rewrite rule, and the class that answers it.
 *
 * A rule without a handler is half a feature, so one class declares the URL and
 * answers it. Implement RewriteHandlerInterface to answer; a rule that only
 * rewrites onto an existing template needs no interface.
 *
 * Usage:
 * ```php
 * #[AsRewriteRule(
 *     regex: '^webhook/stripe/?$',
 *     query: 'index.php?foehn_route=stripe-webhook',
 *     queryVars: ['foehn_route'],
 * )]
 * final readonly class StripeWebhook implements RewriteHandlerInterface
 * {
 *     public function handle(WP $wp): void
 *     {
 *         // …
 *         exit;
 *     }
 * }
 * ```
 *
 * Rules do nothing until WordPress flushes them once. RewriteRuleDiscovery
 * hashes the discovered set and flushes when the hash changes, so adding a rule
 * and reloading works — see `wp foehn rewrite:flush` for the rest.
 *
 * @see \Studiometa\Foehn\Contracts\RewriteHandlerInterface
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsRewriteRule
{
    /**
     * @param string $regex The pattern matched against the path, e.g. `^webhook/stripe/?$`
     * @param string $query What it rewrites to, e.g. `index.php?foehn_route=stripe-webhook`.
     *   `$matches[1]` and its siblings carry the pattern's capture groups
     * @param list<string> $queryVars Query variables to add through the `query_vars`
     *   filter. WordPress discards any variable it does not know
     * @param string $after `'top'` to match before WordPress's own rules, `'bottom'`
     *   to match after them. A webhook wants `'top'`, which is the default
     */
    public function __construct(
        public string $regex,
        public string $query,
        public array $queryVars = [],
        public string $after = 'top',
    ) {}
}
