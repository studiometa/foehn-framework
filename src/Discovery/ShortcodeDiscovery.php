<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use ReflectionClass;
use ReflectionMethod;
use Studiometa\Foehn\Attributes\AsShortcode;
use Studiometa\Foehn\Discovery\Concerns\CacheableDiscovery;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;

use function Tempest\get;

/**
 * Discovers methods marked with #[AsShortcode] attribute
 * and registers them as WordPress shortcodes.
 */
final class ShortcodeDiscovery implements WpDiscovery
{
    use IsWpDiscovery;
    use CacheableDiscovery;

    /**
     * Discover shortcode attributes on methods.
     *
     * @param DiscoveryLocation $location
     * @param ReflectionClass<object> $class
     */
    public function discover(DiscoveryLocation $location, ReflectionClass $class): void
    {
        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $class->getName()) {
                continue;
            }

            $attributes = $method->getAttributes(AsShortcode::class);

            if ($attributes === []) {
                continue;
            }

            $attribute = $attributes[0]->newInstance();

            $this->addItem($location, [
                'tag' => $attribute->tag,
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
            $this->doRegisterShortcode($item['tag'], $item['className'], $item['methodName']);
        }
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
        return [
            'tag' => $item['tag'],
            'className' => $item['className'],
            'methodName' => $item['methodName'],
        ];
    }
}
