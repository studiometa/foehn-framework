<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Inspect;

use Studiometa\Foehn\Discovery\DiscoveryRunner;
use Tempest\Discovery\Discovery;

/**
 * The items discovery collected, read back as plain arrays.
 */
final readonly class DiscoveredItems
{
    public function __construct(
        private DiscoveryRunner $runner,
    ) {}

    /**
     * The discovery itself, for what only it can work out from an item.
     *
     * @template T of Discovery
     * @param class-string<T> $discoveryClass
     * @return T|null
     */
    public function discovery(string $discoveryClass): ?Discovery
    {
        $discovery = $this->runner->getDiscoveries()[$discoveryClass] ?? null;

        return $discovery instanceof $discoveryClass ? $discovery : null;
    }

    /**
     * The items one discovery collected, across every location.
     *
     * Read from the runner rather than registered again: under WP-CLI discovery has
     * already run, and a discovery that is absent simply contributes nothing.
     *
     * @param class-string<Discovery> $discoveryClass
     * @return list<array<string, mixed>>
     */
    public function of(string $discoveryClass): array
    {
        $discovery = $this->discovery($discoveryClass);

        if ($discovery === null) {
            return [];
        }

        $items = [];

        foreach ($discovery->getItems() as $item) {
            if (!is_array($item)) {
                continue;
            }

            $items[] = $item;
        }

        return $items;
    }
}
