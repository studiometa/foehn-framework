<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use Studiometa\Foehn\Attributes\AsTemplateController;

/**
 * Which template controller renders a WordPress template.
 *
 * Several controllers can claim the same template. The lowest priority wins, and on
 * a tie the one discovered last does. A template name is looked up exactly first;
 * failing that, wildcard patterns are tried in priority order and the first that
 * matches wins.
 *
 * Built from discovery items, so `TemplateControllerDiscovery` and `wp foehn inspect`
 * answer the same question the same way.
 */
final readonly class TemplateControllerMap
{
    /**
     * A list rather than an array keyed by template: PHP would turn a key such as
     * `404` into an integer.
     *
     * @param list<array{template: string, controller: array{className: class-string, priority: int}, overridden: list<class-string>}> $claims
     *   Every claimed pattern, in the order wildcards are tried
     */
    private function __construct(
        private array $claims,
    ) {}

    /**
     * @param iterable<mixed> $items Items of TemplateControllerDiscovery
     */
    public static function fromItems(iterable $items): self
    {
        /** @var list<string> $templates */
        $templates = [];
        /** @var list<list<array{className: class-string, priority: int}>> $claimants */
        $claimants = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            /** @var AsTemplateController $attribute */
            $attribute = $item['attribute'];
            /** @var class-string $className */
            $className = $item['className'];

            foreach ($attribute->getTemplates() as $template) {
                $index = array_search($template, $templates, true);

                if ($index === false) {
                    $index = count($templates);
                    $templates[] = $template;
                    $claimants[$index] = [];
                }

                $claimants[$index][] = ['className' => $className, 'priority' => $attribute->priority];
            }
        }

        $claims = [];

        foreach ($templates as $index => $template) {
            $entries = $claimants[$index];
            $winner = 0;

            foreach ($entries as $position => $entry) {
                if ($entry['priority'] > $entries[$winner]['priority']) {
                    continue;
                }

                $winner = $position;
            }

            $overridden = [];

            foreach ($entries as $position => $entry) {
                if ($position === $winner) {
                    continue;
                }

                $overridden[] = $entry['className'];
            }

            $claims[] = ['template' => $template, 'controller' => $entries[$winner], 'overridden' => $overridden];
        }

        // Wildcards are tried in this order. usort() is stable, so patterns of the same
        // priority keep their discovery order.
        usort(
            $claims,
            static fn(array $a, array $b): int => $a['controller']['priority'] <=> $b['controller']['priority'],
        );

        return new self($claims);
    }

    /**
     * The controller that renders a template name, or null when none claims it.
     *
     * @return array{className: class-string, priority: int}|null
     */
    public function find(string $template): ?array
    {
        foreach ($this->claims as $claim) {
            if ($claim['template'] === $template) {
                return $claim['controller'];
            }
        }

        foreach ($this->claims as $claim) {
            if (!str_contains($claim['template'], '*') || !self::matches($template, $claim['template'])) {
                continue;
            }

            return $claim['controller'];
        }

        return null;
    }

    /**
     * Every claimed pattern: the controller that renders it, and the others that claim
     * it too and lose.
     *
     * @return list<array{template: string, controller: array{className: class-string, priority: int}, overridden: list<class-string>}>
     */
    public function claims(): array
    {
        return $this->claims;
    }

    /**
     * Whether a template name matches a pattern with `*` wildcards.
     */
    public static function matches(string $template, string $pattern): bool
    {
        $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/';

        return (bool) preg_match($regex, $template);
    }
}
