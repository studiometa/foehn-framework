<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use ReflectionClass;
use ReflectionMethod;
use Studiometa\Foehn\Attributes\AsAction;
use Studiometa\Foehn\Attributes\AsFilter;
use Studiometa\Foehn\Discovery\Concerns\CacheableDiscovery;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Tempest\Container\Container;

/**
 * Discovers methods marked with #[AsAction] or #[AsFilter] attributes
 * and registers them as WordPress hooks.
 */
final class HookDiscovery implements WpDiscovery
{
    use IsWpDiscovery;
    use CacheableDiscovery;

    public function __construct(
        private readonly Container $container,
    ) {}

    /**
     * Discover hook attributes on class methods.
     *
     * @param DiscoveryLocation $location
     * @param ReflectionClass<object> $class
     */
    public function discover(DiscoveryLocation $location, ReflectionClass $class): void
    {
        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            // Skip methods inherited from parent classes outside the scanned namespace
            if ($method->getDeclaringClass()->getName() !== $class->getName()) {
                continue;
            }

            $this->discoverActions($location, $method);
            $this->discoverFilters($location, $method);
        }
    }

    /**
     * Discover #[AsAction] attributes on a method.
     */
    private function discoverActions(DiscoveryLocation $location, ReflectionMethod $method): void
    {
        $attributes = $method->getAttributes(AsAction::class);

        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();
            $this->addItem($location, [
                'type' => 'action',
                'hook' => $instance->hook,
                'className' => $method->getDeclaringClass()->getName(),
                'methodName' => $method->getName(),
                'priority' => $instance->priority,
                'acceptedArgs' => $instance->acceptedArgs,
            ]);
        }
    }

    /**
     * Discover #[AsFilter] attributes on a method.
     */
    private function discoverFilters(DiscoveryLocation $location, ReflectionMethod $method): void
    {
        $attributes = $method->getAttributes(AsFilter::class);

        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();
            $this->addItem($location, [
                'type' => 'filter',
                'hook' => $instance->hook,
                'className' => $method->getDeclaringClass()->getName(),
                'methodName' => $method->getName(),
                'priority' => $instance->priority,
                'acceptedArgs' => $instance->acceptedArgs,
            ]);
        }
    }

    /**
     * Apply discovered hooks by registering them with WordPress.
     */
    public function apply(): void
    {
        foreach ($this->getItems() as $item) {
            $this->registerHook($item);
        }
    }

    /**
     * Register a single hook with WordPress.
     *
     * @param array<string, mixed> $item
     */
    private function registerHook(array $item): void
    {
        $instance = $this->container->get($item['className']);

        // Create the callback
        $callback = [$instance, $item['methodName']];

        // Register with WordPress
        if ($item['type'] === 'action') {
            add_action($item['hook'], $callback, $item['priority'], $item['acceptedArgs']);

            return;
        }

        add_filter($item['hook'], $callback, $item['priority'], $item['acceptedArgs']);
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
            'type' => $item['type'],
            'hook' => $item['hook'],
            'className' => $item['className'],
            'methodName' => $item['methodName'],
            'priority' => $item['priority'],
            'acceptedArgs' => $item['acceptedArgs'],
        ];
    }
}
