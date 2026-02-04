<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Discovery;

use Studiometa\WPTempest\Attributes\AsAction;
use Studiometa\WPTempest\Attributes\AsFilter;
use Studiometa\WPTempest\Discovery\Concerns\CacheableDiscovery;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Discovery\IsDiscovery;
use Tempest\Reflection\ClassReflector;
use Tempest\Reflection\MethodReflector;

/**
 * Discovers methods marked with #[AsAction] or #[AsFilter] attributes
 * and registers them as WordPress hooks.
 */
final class HookDiscovery implements Discovery
{
    use IsDiscovery;
    use CacheableDiscovery;

    /**
     * Discover hook attributes on class methods.
     */
    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        foreach ($class->getPublicMethods() as $method) {
            $this->discoverActions($location, $method);
            $this->discoverFilters($location, $method);
        }
    }

    /**
     * Discover #[AsAction] attributes on a method.
     */
    private function discoverActions(DiscoveryLocation $location, MethodReflector $method): void
    {
        $attributes = $method->getAttributes(AsAction::class);

        foreach ($attributes as $attribute) {
            $this->discoveryItems->add($location, [
                'type' => 'action',
                'attribute' => $attribute,
                'method' => $method,
            ]);
        }
    }

    /**
     * Discover #[AsFilter] attributes on a method.
     */
    private function discoverFilters(DiscoveryLocation $location, MethodReflector $method): void
    {
        $attributes = $method->getAttributes(AsFilter::class);

        foreach ($attributes as $attribute) {
            $this->discoveryItems->add($location, [
                'type' => 'filter',
                'attribute' => $attribute,
                'method' => $method,
            ]);
        }
    }

    /**
     * Apply discovered hooks by registering them with WordPress.
     */
    public function apply(): void
    {
        foreach ($this->getAllItems() as $item) {
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
        // Handle cached format
        if (isset($item['className'])) {
            $this->registerHookFromCache($item);

            return;
        }

        // Handle discovery format
        $type = $item['type'];
        /** @var AsAction|AsFilter $attribute */
        $attribute = $item['attribute'];
        /** @var MethodReflector $method */
        $method = $item['method'];

        $className = $method->getDeclaringClass()->getName();
        $methodName = $method->getName();

        $this->doRegisterHook(
            $type,
            $attribute->hook,
            $className,
            $methodName,
            $attribute->priority,
            $attribute->acceptedArgs,
        );
    }

    /**
     * Register a hook from cached data.
     *
     * @param array<string, mixed> $item
     */
    private function registerHookFromCache(array $item): void
    {
        $this->doRegisterHook(
            $item['type'],
            $item['hook'],
            $item['className'],
            $item['methodName'],
            $item['priority'],
            $item['acceptedArgs'],
        );
    }

    /**
     * Actually register the hook with WordPress.
     */
    private function doRegisterHook(
        string $type,
        string $hook,
        string $className,
        string $methodName,
        int $priority,
        int $acceptedArgs,
    ): void {
        // Get the class instance from the container (enables DI)
        $instance = \Tempest\get($className);

        // Create the callback
        $callback = [$instance, $methodName];

        // Register with WordPress
        if ($type === 'action') {
            add_action($hook, $callback, $priority, $acceptedArgs);
        } else {
            add_filter($hook, $callback, $priority, $acceptedArgs);
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
        /** @var AsAction|AsFilter $attribute */
        $attribute = $item['attribute'];
        /** @var MethodReflector $method */
        $method = $item['method'];

        return [
            'type' => $item['type'],
            'hook' => $attribute->hook,
            'className' => $method->getDeclaringClass()->getName(),
            'methodName' => $method->getName(),
            'priority' => $attribute->priority,
            'acceptedArgs' => $attribute->acceptedArgs,
        ];
    }
}
