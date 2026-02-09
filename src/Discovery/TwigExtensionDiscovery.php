<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use ReflectionClass;
use Studiometa\Foehn\Attributes\AsTwigExtension;
use Studiometa\Foehn\Discovery\Concerns\CacheableDiscovery;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Twig\Environment;
use Twig\Extension\AbstractExtension;

/**
 * Discovers classes marked with #[AsTwigExtension] attribute
 * and registers them with Timber's Twig environment.
 */
final class TwigExtensionDiscovery implements WpDiscovery
{
    use IsWpDiscovery;
    use CacheableDiscovery;

    /**
     * Discover Twig extension classes.
     *
     * @param DiscoveryLocation $location
     * @param ReflectionClass<object> $class
     */
    public function discover(DiscoveryLocation $location, ReflectionClass $class): void
    {
        $attributes = $class->getAttributes(AsTwigExtension::class);

        if ($attributes === []) {
            return;
        }

        // Ensure the class extends AbstractExtension
        if (!$class->isSubclassOf(AbstractExtension::class)) {
            return;
        }

        $attribute = $attributes[0]->newInstance();

        $this->addItem($location, [
            'className' => $class->getName(),
            'priority' => $attribute->priority,
        ]);
    }

    /**
     * Apply discovered Twig extensions by registering them with Timber.
     */
    public function apply(): void
    {
        // Collect all items and sort by priority
        $items = iterator_to_array($this->getItems());

        if ($items === []) {
            return;
        }

        usort($items, static fn(array $a, array $b): int => $a['priority'] <=> $b['priority']);

        add_filter('timber/twig', static function (Environment $twig) use ($items): Environment {
            foreach ($items as $item) {
                /** @var AbstractExtension $extension */
                $extension = \Tempest\get($item['className']);
                $twig->addExtension($extension);
            }

            return $twig;
        });
    }

    /**
     * Convert a discovered item to a cacheable format.
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    protected function itemToCacheable(array $item): array
    {
        return [
            'className' => $item['className'],
            'priority' => $item['priority'],
        ];
    }
}
