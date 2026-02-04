<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Discovery;

use ReflectionClass;
use ReflectionMethod;
use Studiometa\WPTempest\Attributes\AsAction;
use Studiometa\WPTempest\Attributes\AsFilter;
use Studiometa\WPTempest\Discovery\Concerns\CacheableDiscovery;
use Studiometa\WPTempest\Discovery\Concerns\IsWpDiscovery;

/**
 * Discovers methods marked with #[AsAction] or #[AsFilter] attributes
 * and registers them as WordPress hooks.
 */
final class HookDiscovery implements WpDiscovery
{
    use IsWpDiscovery;
    use CacheableDiscovery;

    /**
     * Discover hook attributes on class methods.
     *
     * @param ReflectionClass<object> $class
     */
    public function discover(ReflectionClass $class): void
    {
        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            // Skip methods inherited from parent classes outside the scanned namespace
            if ($method->getDeclaringClass()->getName() !== $class->getName()) {
                continue;
            }

            $this->discoverActions($method);
            $this->discoverFilters($method);
        }
    }

    /**
     * Discover #[AsAction] attributes on a method.
     */
    private function discoverActions(ReflectionMethod $method): void
    {
        $attributes = $method->getAttributes(AsAction::class);

        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();
            $this->addItem([
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
    private function discoverFilters(ReflectionMethod $method): void
    {
        $attributes = $method->getAttributes(AsFilter::class);

        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();
            $this->addItem([
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
        // Get the class instance from the container (enables DI)
        $instance = \Tempest\get($item['className']);

        // Create the callback
        $callback = [$instance, $item['methodName']];

        // Register with WordPress
        if ($item['type'] === 'action') {
            add_action($item['hook'], $callback, $item['priority'], $item['acceptedArgs']);
        } else {
            add_filter($item['hook'], $callback, $item['priority'], $item['acceptedArgs']);
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
            'type' => $item['type'],
            'hook' => $item['hook'],
            'className' => $item['className'],
            'methodName' => $item['methodName'],
            'priority' => $item['priority'],
            'acceptedArgs' => $item['acceptedArgs'],
        ];
    }
}
