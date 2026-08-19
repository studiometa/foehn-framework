<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use InvalidArgumentException;
use Studiometa\Foehn\Attributes\AsContextProvider;
use Studiometa\Foehn\Contracts\ContextProviderInterface;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Studiometa\Foehn\Views\ContextProviderRegistry;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;

use function Tempest\Container\get;

/**
 * Discovers classes marked with #[AsContextProvider] attribute
 * and registers them with the ContextProviderRegistry.
 */
final class ContextProviderDiscovery implements Discovery
{
    use IsWpDiscovery;

    /**
     * Discover context provider attributes on classes.
     *
     * @param DiscoveryLocation $location
     * @param ClassReflector $class
     */
    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        if (!$this->isConcrete($class)) {
            return;
        }

        $attribute = $class->getAttribute(AsContextProvider::class);

        if ($attribute === null) {
            return;
        }

        // Verify the class implements ContextProviderInterface
        if (!$class->implements(ContextProviderInterface::class)) {
            throw new InvalidArgumentException(sprintf(
                'Class %s must implement %s to use #[AsContextProvider]',
                $class->getName(),
                ContextProviderInterface::class,
            ));
        }

        $this->addItem($location, [
            'attribute' => $attribute,
            'className' => $class->getName(),
        ]);
    }

    /**
     * Apply discovered context providers by registering them.
     */
    public function apply(): void
    {
        /** @var ContextProviderRegistry $registry */
        $registry = get(ContextProviderRegistry::class);

        foreach ($this->getItems() as $item) {
            /** @var AsContextProvider $attribute */
            $attribute = $item['attribute'];
            /** @var ContextProviderInterface $provider */
            $provider = get($item['className']);

            $registry->register($attribute->getTemplates(), $provider, $attribute->priority);
        }
    }
}
