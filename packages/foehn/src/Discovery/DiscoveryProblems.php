<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Tempest\Container\Singleton;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;

/**
 * The classes discovery found and could not register, with why and how to fix it.
 *
 * A class that carries an attribute but breaks its contract — #[AsPostType] on a
 * class that does not extend Timber\Post, #[AsCliCommand] without the interface —
 * is skipped, and one bad class does not stop every other class from registering.
 * Skipping it without a word would leave a post type that simply is not there, so
 * the discovery that rejects it reports it here instead.
 *
 * It is a discovery itself only so that its records travel with the others: they
 * are items, so the discovery cache stores them per location and a warm request
 * restores them with the classes they are about. It registers nothing.
 *
 * One instance per container: every discovery that reports is handed the same one.
 */
#[Singleton]
final class DiscoveryProblems implements Discovery
{
    use IsWpDiscovery;

    /**
     * Nothing to find: other discoveries report what they reject.
     */
    public function discover(DiscoveryLocation $location, ClassReflector $class): void {}

    /**
     * Nothing to register.
     */
    public function apply(): void {}

    /**
     * Record a class that was not registered.
     *
     * @param string $className The class that was skipped
     * @param string $attribute What declared it, as written in the source: `#[AsPostType]`
     * @param string $reason What is wrong, as a sentence
     * @param string|null $fix The one change that fixes it, when the cause is known
     */
    public function report(
        DiscoveryLocation $location,
        string $className,
        string $attribute,
        string $reason,
        ?string $fix = null,
    ): void {
        $this->addItem($location, [
            'class' => $className,
            'attribute' => $attribute,
            'reason' => $reason,
            'fix' => $fix,
        ]);
    }

    /**
     * Every recorded problem, across every location.
     *
     * @return list<array{class: string, attribute: string, reason: string, fix: string|null}>
     */
    public function all(): array
    {
        $problems = [];

        foreach ($this->getItems() as $item) {
            /** @var array{class: string, attribute: string, reason: string, fix: string|null} $item */
            $problems[] = $item;
        }

        return $problems;
    }

    /**
     * One problem on one line: the reason, then the fix when there is one.
     *
     * @param array{class: string, attribute: string, reason: string, fix: string|null} $problem
     */
    public static function format(array $problem): string
    {
        return $problem['fix'] === null
            ? $problem['reason']
            : sprintf('%s Fix: %s', $problem['reason'], $problem['fix']);
    }
}
