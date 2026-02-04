<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Discovery;

use InvalidArgumentException;
use Studiometa\WPTempest\Attributes\AsViewComposer;
use Studiometa\WPTempest\Contracts\ViewComposerInterface;
use Studiometa\WPTempest\Discovery\Concerns\CacheableDiscovery;
use Studiometa\WPTempest\Views\ViewComposerRegistry;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Discovery\IsDiscovery;
use Tempest\Reflection\ClassReflector;

use function Tempest\get;

/**
 * Discovers classes marked with #[AsViewComposer] attribute
 * and registers them with the ViewComposerRegistry.
 */
final class ViewComposerDiscovery implements Discovery
{
    use IsDiscovery;
    use CacheableDiscovery;

    /**
     * Discover view composer attributes on classes.
     */
    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        $attribute = $class->getAttribute(AsViewComposer::class);

        if ($attribute === null) {
            return;
        }

        // Verify the class implements ViewComposerInterface
        if (!$class->getReflection()->implementsInterface(ViewComposerInterface::class)) {
            throw new InvalidArgumentException(sprintf(
                'Class %s must implement %s to use #[AsViewComposer]',
                $class->getName(),
                ViewComposerInterface::class,
            ));
        }

        $this->discoveryItems->add($location, [
            'attribute' => $attribute,
            'className' => $class->getName(),
        ]);
    }

    /**
     * Apply discovered view composers by registering them.
     */
    public function apply(): void
    {
        /** @var ViewComposerRegistry $registry */
        $registry = get(ViewComposerRegistry::class);

        foreach ($this->getAllItems() as $item) {
            // Handle cached format
            if (isset($item['templates'])) {
                $this->registerComposerFromCache($registry, $item);
            } else {
                $this->registerComposer($registry, $item);
            }
        }
    }

    /**
     * Register a single view composer.
     *
     * @param ViewComposerRegistry $registry
     * @param array<string, mixed> $item
     */
    private function registerComposer(ViewComposerRegistry $registry, array $item): void
    {
        $attribute = $item['attribute'];
        $className = $item['className'];

        /** @var ViewComposerInterface $composer */
        $composer = get($className);

        $registry->register($attribute->getTemplates(), $composer, $attribute->priority);
    }

    /**
     * Register a view composer from cached data.
     *
     * @param ViewComposerRegistry $registry
     * @param array<string, mixed> $item
     */
    private function registerComposerFromCache(ViewComposerRegistry $registry, array $item): void
    {
        /** @var ViewComposerInterface $composer */
        $composer = get($item['className']);

        $registry->register($item['templates'], $composer, $item['priority']);
    }

    /**
     * Convert a discovered item to a cacheable format.
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    protected function itemToCacheable(array $item): array
    {
        /** @var AsViewComposer $attribute */
        $attribute = $item['attribute'];

        return [
            'templates' => $attribute->getTemplates(),
            'className' => $item['className'],
            'priority' => $attribute->priority,
        ];
    }
}
