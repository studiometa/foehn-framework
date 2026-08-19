<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use InvalidArgumentException;
use Studiometa\Foehn\Attributes\AsDiscovery;
use Studiometa\Foehn\Attributes\AsRewriteRule;
use Studiometa\Foehn\Contracts\RewriteHandlerInterface;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Tempest\Container\Container;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;
use WP;

/**
 * Discovers classes marked with #[AsRewriteRule] and registers the rules with
 * WordPress, dispatching the requests they match.
 *
 * `add_rewrite_rule()` belongs on `init`, so this is a Main phase discovery.
 */
#[AsDiscovery(phase: DiscoveryPhase::Main)]
final class RewriteRuleDiscovery implements Discovery
{
    use IsWpDiscovery;

    /**
     * Where the hash of the registered rule set is kept.
     *
     * Rules registered in code do nothing until WordPress flushes them once, and
     * flush_rewrite_rules() rewrites an option — calling it per request is a
     * well-known way to ruin a site. Comparing a hash costs nothing, and the
     * rules come out of the discovery cache anyway.
     */
    public const string HASH_OPTION = 'foehn_rewrite_rules_hash';

    public function __construct(
        private readonly Container $container,
    ) {}

    /**
     * Discover rewrite rules on a class.
     */
    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        if (!$this->isConcrete($class)) {
            return;
        }

        $attribute = $class->getAttribute(AsRewriteRule::class);

        if ($attribute === null) {
            return;
        }

        if (!in_array($attribute->after, ['top', 'bottom'], true)) {
            throw new InvalidArgumentException(sprintf(
                '#[AsRewriteRule] on %s declares after: \'%s\'. add_rewrite_rule() accepts top or bottom.',
                $class->getName(),
                $attribute->after,
            ));
        }

        $this->addItem($location, [
            'attribute' => $attribute,
            'className' => $class->getName(),
            'handles' => $class->implements(RewriteHandlerInterface::class),
            // The query variables the rule sets to a fixed value, which is what
            // identifies a matched request. Parsed here rather than on every
            // request, and cached with the item.
            'match' => self::matchableVars($attribute->query),
        ]);
    }

    /**
     * Apply discovered rewrite rules.
     */
    public function apply(): void
    {
        $items = iterator_to_array($this->getItems());

        if ($items === []) {
            return;
        }

        $queryVars = [];
        $handlers = [];

        foreach ($items as $item) {
            /** @var AsRewriteRule $attribute */
            $attribute = $item['attribute'];

            add_rewrite_rule($attribute->regex, $attribute->query, $attribute->after);

            $queryVars = [...$queryVars, ...$attribute->queryVars];

            if ($item['handles']) {
                $handlers[] = $item;
            }
        }

        $this->registerQueryVars(array_values(array_unique($queryVars)));
        $this->registerDispatcher($handlers);
        $this->flushWhenChanged($items);
    }

    /**
     * Teach WordPress the variables the rules set.
     *
     * WordPress discards any query variable it does not know, so a rule that
     * rewrites to `index.php?foehn_route=x` reaches a request with no
     * `foehn_route` in it until the filter runs.
     *
     * @param list<string> $queryVars
     */
    private function registerQueryVars(array $queryVars): void
    {
        if ($queryVars === []) {
            return;
        }

        add_filter('query_vars', static fn(array $vars): array => [...$vars, ...$queryVars]);
    }

    /**
     * Dispatch a matched request to the class that declared its rule.
     *
     * On `parse_request`, before the main query runs — which is what lets a
     * webhook answer and exit without WordPress rendering a page first.
     *
     * @param list<array<string, mixed>> $items
     */
    private function registerDispatcher(array $items): void
    {
        if ($items === []) {
            return;
        }

        $container = $this->container;

        add_action('parse_request', static function (WP $wp) use ($items, $container): void {
            foreach ($items as $item) {
                if (!self::matches($wp, $item['match'])) {
                    continue;
                }

                /** @var RewriteHandlerInterface $handler */
                $handler = $container->get($item['className']);
                $handler->handle($wp);

                return;
            }
        });
    }

    /**
     * Whether this request is the one a rule rewrote to.
     *
     * @param array<string, string> $match
     */
    private static function matches(WP $wp, array $match): bool
    {
        if ($match === []) {
            return false;
        }

        foreach ($match as $name => $value) {
            if (($wp->query_vars[$name] ?? null) !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * The query variables a rule's target sets to a value known in advance.
     *
     * `index.php?foehn_route=stripe-webhook&id=$matches[1]` identifies the route
     * by `foehn_route`; `id` carries whatever the pattern captured, and cannot
     * be compared against anything.
     *
     * @return array<string, string>
     */
    private static function matchableVars(string $query): array
    {
        $position = strpos($query, '?');

        if ($position === false) {
            return [];
        }

        $parsed = [];

        parse_str(substr($query, $position + 1), $parsed);

        $match = [];

        foreach ($parsed as $name => $value) {
            if (!is_string($value) || str_contains($value, '$matches[')) {
                continue;
            }

            $match[$name] = $value;
        }

        return $match;
    }

    /**
     * Flush the rules exactly when the set of them changed.
     *
     * A soft flush: the rules live in the `rewrite_rules` option, and .htaccess
     * only ever routes everything to index.php, so rewriting it would be work
     * for nothing on a host that lets you.
     *
     * @param list<array<string, mixed>> $items
     */
    private function flushWhenChanged(array $items): void
    {
        $hash = self::hash($items);

        if (get_option(self::HASH_OPTION) === $hash) {
            return;
        }

        flush_rewrite_rules(false);
        update_option(self::HASH_OPTION, $hash);
    }

    /**
     * A fingerprint of the registered rules, and of nothing else.
     *
     * The class name is deliberately not in it: moving a rule between classes
     * changes what answers the URL, not what WordPress has to match.
     *
     * @param list<array<string, mixed>> $items
     */
    public static function hash(array $items): string
    {
        $rules = array_map(static fn(array $item): string => sprintf(
            '%s|%s|%s',
            $item['attribute']->regex,
            $item['attribute']->query,
            $item['attribute']->after,
        ), $items);

        sort($rules);

        return md5(implode("\n", $rules));
    }
}
