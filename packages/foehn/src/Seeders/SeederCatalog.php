<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Seeders;

use Studiometa\Foehn\Attributes\AsSeeder;
use Studiometa\Foehn\Contracts\SeederInterface;
use Studiometa\Foehn\Discovery\DiscoveryRunner;
use Studiometa\Foehn\Discovery\SeederDiscovery;

/**
 * The seeders discovery found, in the order `wp foehn seed` runs them.
 */
final readonly class SeederCatalog
{
    public function __construct(
        private DiscoveryRunner $runner,
    ) {}

    /**
     * Every seeder, by `order` and then by class name.
     *
     * @return list<array{className: class-string<SeederInterface>, attribute: AsSeeder}>
     */
    public function all(): array
    {
        $discovery = $this->runner->getDiscoveries()[SeederDiscovery::class] ?? null;

        if ($discovery === null) {
            return [];
        }

        $seeders = [];

        foreach ($discovery->getItems() as $item) {
            $attribute = is_array($item) ? $item['attribute'] ?? null : null;

            if (!$attribute instanceof AsSeeder) {
                continue;
            }

            /** @var class-string<SeederInterface> $className */
            $className = $item['className'];

            $seeders[] = ['className' => $className, 'attribute' => $attribute];
        }

        usort(
            $seeders,
            static fn(array $a, array $b): int => (
                [$a['attribute']->order, $a['className']] <=> [
                    $b['attribute']->order,
                    $b['className'],
                ]
            ),
        );

        return $seeders;
    }

    /**
     * The seeders that a `--class` value names: the full class name, or the short name.
     *
     * A short name can match more than one class. The caller reports that.
     *
     * @return list<array{className: class-string<SeederInterface>, attribute: AsSeeder}>
     */
    public function find(string $name): array
    {
        $name = ltrim($name, '\\');

        return array_values(array_filter(
            $this->all(),
            static fn(array $seeder): bool => (
                $seeder['className'] === $name
                || array_last(explode('\\', $seeder['className'])) === $name
            ),
        ));
    }
}
