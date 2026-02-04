<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Discovery;

use InvalidArgumentException;
use ReflectionClass;
use Studiometa\WPTempest\Attributes\AsViewComposer;
use Studiometa\WPTempest\Contracts\ViewComposerInterface;
use Studiometa\WPTempest\Discovery\Concerns\CacheableDiscovery;
use Studiometa\WPTempest\Discovery\Concerns\IsWpDiscovery;
use Studiometa\WPTempest\Views\ViewComposerRegistry;

use function Tempest\get;

/**
 * Discovers classes marked with #[AsViewComposer] attribute
 * and registers them with the ViewComposerRegistry.
 */
final class ViewComposerDiscovery implements WpDiscovery
{
    use IsWpDiscovery;
    use CacheableDiscovery;

    /**
     * Discover view composer attributes on classes.
     *
     * @param ReflectionClass<object> $class
     */
    public function discover(ReflectionClass $class): void
    {
        $attributes = $class->getAttributes(AsViewComposer::class);

        if ($attributes === []) {
            return;
        }

        // Verify the class implements ViewComposerInterface
        if (!$class->implementsInterface(ViewComposerInterface::class)) {
            throw new InvalidArgumentException(sprintf(
                'Class %s must implement %s to use #[AsViewComposer]',
                $class->getName(),
                ViewComposerInterface::class,
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
     * Apply discovered view composers by registering them.
     */
    public function apply(): void
    {
        /** @var ViewComposerRegistry $registry */
        $registry = get(ViewComposerRegistry::class);

        foreach ($this->getAllItems() as $item) {
            /** @var ViewComposerInterface $composer */
            $composer = get($item['className']);

            $registry->register($item['templates'], $composer, $item['priority']);
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
