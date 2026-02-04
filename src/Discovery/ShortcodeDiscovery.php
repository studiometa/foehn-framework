<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Discovery;

use Studiometa\WPTempest\Attributes\AsShortcode;
use Studiometa\WPTempest\Discovery\Concerns\CacheableDiscovery;
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
    use CacheableDiscovery;

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
        foreach ($this->getAllItems() as $item) {
            // Handle cached format
            if (isset($item['className'])) {
                $this->registerShortcodeFromCache($item);
            } else {
                $this->registerShortcode($item['attribute'], $item['method']);
            }
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

        $this->doRegisterShortcode($attribute->tag, $className, $methodName);
    }

    /**
     * Register shortcode from cached data.
     *
     * @param array<string, mixed> $item
     */
    private function registerShortcodeFromCache(array $item): void
    {
        $this->doRegisterShortcode($item['tag'], $item['className'], $item['methodName']);
    }

    /**
     * Actually register the shortcode with WordPress.
     */
    private function doRegisterShortcode(string $tag, string $className, string $methodName): void
    {
        add_shortcode($tag, static function ($atts, $content = null, $shortcodeTag = '') use ($className, $methodName) {
            $instance = get($className);

            // Normalize attributes - WP passes '' when no attributes despite stubs saying array
            $atts = is_array($atts) ? $atts : [];

            return $instance->{$methodName}($atts, $content, $shortcodeTag);
        });
    }

    /**
     * Convert a discovered item to a cacheable format.
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    protected function itemToCacheable(array $item): array
    {
        /** @var AsShortcode $attribute */
        $attribute = $item['attribute'];
        /** @var MethodReflector $method */
        $method = $item['method'];

        return [
            'tag' => $attribute->tag,
            'className' => $method->getDeclaringClass()->getName(),
            'methodName' => $method->getName(),
        ];
    }
}
