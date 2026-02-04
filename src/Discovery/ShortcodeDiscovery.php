<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Discovery;

use Studiometa\WPTempest\Attributes\AsShortcode;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Discovery\IsDiscovery;
use Tempest\Reflection\ClassReflector;
use Tempest\Reflection\MethodReflector;

use function Tempest\get;

/**
 * Discovers methods marked with #[AsShortcode] attribute
 * and registers them as WordPress shortcodes.
 */
final class ShortcodeDiscovery implements Discovery
{
    use IsDiscovery;

    /**
     * Discover shortcode attributes on methods.
     */
    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        foreach ($class->getPublicMethods() as $method) {
            $attribute = $method->getAttribute(AsShortcode::class);

            if ($attribute === null) {
                continue;
            }

            $this->discoveryItems->add($location, [
                'attribute' => $attribute,
                'method' => $method,
            ]);
        }
    }

    /**
     * Apply discovered shortcodes by registering them.
     */
    public function apply(): void
    {
        foreach ($this->discoveryItems as $item) {
            $this->registerShortcode($item['attribute'], $item['method']);
        }
    }

    /**
     * Register a single shortcode.
     *
     * @param AsShortcode $attribute
     * @param MethodReflector $method
     */
    private function registerShortcode(AsShortcode $attribute, MethodReflector $method): void
    {
        $className = $method->getDeclaringClass()->getName();
        $methodName = $method->getName();

        add_shortcode($attribute->tag, static function ($atts, $content = null, $tag = '') use (
            $className,
            $methodName,
        ) {
            $instance = get($className);

            // Normalize attributes - WP passes '' when no attributes despite stubs saying array
            $atts = is_array($atts) ? $atts : [];

            return $instance->{$methodName}($atts, $content, $tag);
        });
    }
}
