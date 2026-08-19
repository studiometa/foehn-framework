<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use ReflectionMethod;
use Studiometa\Foehn\Attributes\AsDiscovery;
use Studiometa\Foehn\Attributes\AsShortcode;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;

use function Tempest\Container\get;

/**
 * Discovers methods marked with #[AsShortcode] attribute
 * and registers them as WordPress shortcodes.
 */
#[AsDiscovery(phase: DiscoveryPhase::Early)]
final class ShortcodeDiscovery implements Discovery
{
    use IsWpDiscovery;

    /**
     * Discover shortcode attributes on methods.
     *
     * @param DiscoveryLocation $location
     * @param ClassReflector $class
     */
    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        if (!$this->isConcrete($class)) {
            return;
        }

        foreach ($class->getReflection()->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $class->getName()) {
                continue;
            }

            $attributes = $method->getAttributes(AsShortcode::class);

            if ($attributes === []) {
                continue;
            }

            $this->addItem($location, [
                'attribute' => $attributes[0]->newInstance(),
                'className' => $method->getDeclaringClass()->getName(),
                'methodName' => $method->getName(),
            ]);
        }
    }

    /**
     * Apply discovered shortcodes by registering them.
     */
    public function apply(): void
    {
        foreach ($this->getItems() as $item) {
            /** @var AsShortcode $attribute */
            $attribute = $item['attribute'];

            $this->registerShortcode($attribute, $item['className'], $item['methodName']);
        }
    }

    /**
     * Register the shortcode with WordPress.
     */
    private function registerShortcode(AsShortcode $attribute, string $className, string $methodName): void
    {
        $callback = static function ($atts, $content = null, $shortcodeTag = '') use ($className, $methodName) {
            $instance = get($className);

            // Normalize attributes - WP passes '' when no attributes despite stubs saying array
            $atts = is_array($atts) ? $atts : [];

            return $instance->{$methodName}($atts, $content, $shortcodeTag);
        };

        add_shortcode($attribute->tag, $callback);
    }
}
