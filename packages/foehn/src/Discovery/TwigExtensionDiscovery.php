<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use Studiometa\Foehn\Attributes\AsDiscovery;
use Studiometa\Foehn\Attributes\AsTwigExtension;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Tempest\Container\Container;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;
use Twig\Environment;
use Twig\Extension\AbstractExtension;

/**
 * Discovers classes marked with #[AsTwigExtension] attribute
 * and registers them with Timber's Twig environment.
 */
#[AsDiscovery(phase: DiscoveryPhase::Early)]
final class TwigExtensionDiscovery implements Discovery
{
    use IsWpDiscovery;

    public function __construct(
        private readonly Container $container,
    ) {}

    /**
     * Discover Twig extension classes.
     *
     * @param DiscoveryLocation $location
     * @param ClassReflector $class
     */
    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        if (!$this->isConcrete($class)) {
            return;
        }

        $attribute = $class->getAttribute(AsTwigExtension::class);

        if ($attribute === null) {
            return;
        }

        // Ensure the class extends AbstractExtension
        if (!$class->getReflection()->isSubclassOf(AbstractExtension::class)) {
            return;
        }

        $this->addItem($location, [
            'attribute' => $attribute,
            'className' => $class->getName(),
        ]);
    }

    /**
     * Apply discovered Twig extensions by registering them with Timber.
     */
    public function apply(): void
    {
        // Collect all items and sort by priority
        /** @var list<array<string, mixed>> $items */
        $items = iterator_to_array($this->getItems());

        if ($items === []) {
            return;
        }

        usort($items, static fn(array $a, array $b): int => $a['attribute']->priority <=> $b['attribute']->priority);

        $container = $this->container;

        add_filter('timber/twig', static function (Environment $twig) use ($items, $container): Environment {
            foreach ($items as $item) {
                /** @var AbstractExtension $extension */
                $extension = $container->get($item['className']);
                $twig->addExtension($extension);
            }

            return $twig;
        });
    }
}
