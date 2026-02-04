<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Discovery;

use Studiometa\WPTempest\Attributes\AsAction;
use Studiometa\WPTempest\Attributes\AsFilter;
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
        foreach ($this->discoveryItems as $item) {
            $this->registerHook($item);
        }
    }

    /**
     * Register a single hook with WordPress.
     *
     * @param array{type: string, attribute: AsAction|AsFilter, method: MethodReflector} $item
     */
    private function registerHook(array $item): void
    {
        $type = $item['type'];
        $attribute = $item['attribute'];
        $method = $item['method'];

        $className = $method->getDeclaringClass()->getName();
        $methodName = $method->getName();

        // Get the class instance from the container (enables DI)
        $instance = \Tempest\get($className);

        // Create the callback
        $callback = [$instance, $methodName];

        // Register with WordPress
        if ($type === 'action') {
            add_action($attribute->hook, $callback, $attribute->priority, $attribute->acceptedArgs);
        } else {
            add_filter($attribute->hook, $callback, $attribute->priority, $attribute->acceptedArgs);
        }
    }
}
