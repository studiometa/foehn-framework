<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use InvalidArgumentException;
use ReflectionClass;
use Studiometa\Foehn\Attributes\AsContextProvider;
use Studiometa\Foehn\Contracts\ContextProviderInterface;
use Studiometa\Foehn\Discovery\Concerns\CacheableDiscovery;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Studiometa\Foehn\Views\ContextProviderRegistry;

use function Tempest\get;

/**
 * Discovers classes marked with #[AsContextProvider] attribute
 * and registers them with the ContextProviderRegistry.
 */
final class ContextProviderDiscovery implements WpDiscovery
{
    use IsWpDiscovery;
    use CacheableDiscovery;

    /**
     * Discover context provider attributes on classes.
     *
     * @param ReflectionClass<object> $class
     */
    public function discover(ReflectionClass $class): void
    {
        $attributes = $class->getAttributes(AsContextProvider::class);

        if ($attributes === []) {
            return;
        }

        // Verify the class implements ContextProviderInterface
        if (!$class->implementsInterface(ContextProviderInterface::class)) {
            throw new InvalidArgumentException(sprintf(
                'Class %s must implement %s to use #[AsContextProvider]',
                $class->getName(),
                ContextProviderInterface::class,
            ));
        }

        $attribute = $attributes[0]->newInstance();

        $this->addItem([
            'templates' => $attribute->getTemplates(),
            'className' => $class->getName(),
            'priority' => $attribute->priority,
        ]);
    }

    /**
     * Apply discovered context providers by registering them.
     */
    public function apply(): void
    {
        /** @var ContextProviderRegistry $registry */
        $registry = get(ContextProviderRegistry::class);

        foreach ($this->getAllItems() as $item) {
            /** @var ContextProviderInterface $provider */
            $provider = get($item['className']);

            $registry->register($item['templates'], $provider, $item['priority']);
        }
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
            'templates' => $item['templates'],
            'className' => $item['className'],
            'priority' => $item['priority'],
        ];
    }
}
