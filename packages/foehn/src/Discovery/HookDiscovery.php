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

            $this->discoverHooks($location, $method, AsAction::class);
            $this->discoverHooks($location, $method, AsFilter::class);
        }
    }

    /**
     * Discover every #[AsAction] or #[AsFilter] attribute on a method.
     *
     * Both attributes carry the same three values and differ only in which
     * WordPress function registers them, so the attribute class is the item's
     * own discriminator — apply() reads it back rather than a stored flag.
     *
     * @param class-string<AsAction|AsFilter> $attributeClass
     */
    private function discoverHooks(DiscoveryLocation $location, ReflectionMethod $method, string $attributeClass): void
    {
        foreach ($method->getAttributes($attributeClass) as $attribute) {
            $this->addItem($location, [
                'attribute' => $attribute->newInstance(),
                'className' => $method->getDeclaringClass()->getName(),
                'methodName' => $method->getName(),
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
        /** @var AsAction|AsFilter $attribute */
        $attribute = $item['attribute'];

        $instance = $this->container->get($item['className']);

        // Create the callback
        $callback = [$instance, $item['methodName']];

        // Register with WordPress
        if ($attribute instanceof AsAction) {
            add_action($attribute->hook, $callback, $attribute->priority, $attribute->acceptedArgs);

            return;
        }

        add_filter($attribute->hook, $callback, $attribute->priority, $attribute->acceptedArgs);
    }
}
