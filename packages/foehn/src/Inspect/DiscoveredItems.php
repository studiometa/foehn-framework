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
        $discovery = $this->runner->getDiscoveries()[$discoveryClass] ?? null;

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
