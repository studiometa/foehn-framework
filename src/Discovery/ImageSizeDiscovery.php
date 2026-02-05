<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use ReflectionClass;
use Studiometa\Foehn\Attributes\AsImageSize;
use Studiometa\Foehn\Discovery\Concerns\CacheableDiscovery;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;

/**
 * Discovers classes marked with #[AsImageSize] attribute
 * and registers them as WordPress custom image sizes.
 */
final class ImageSizeDiscovery implements WpDiscovery
{
    use IsWpDiscovery;
    use CacheableDiscovery;

    /**
     * Discover image size attributes on classes.
     *
     * @param ReflectionClass<object> $class
     */
    public function discover(ReflectionClass $class): void
    {
        $attributes = $class->getAttributes(AsImageSize::class);

        if ($attributes === []) {
            return;
        }

        $attribute = $attributes[0]->newInstance();
        $name = $attribute->name ?? $this->deriveNameFromClass($class->getShortName());

        $this->addItem([
            'name' => $name,
            'width' => $attribute->width,
            'height' => $attribute->height,
            'crop' => $attribute->crop,
            'className' => $class->getName(),
        ]);
    }

    /**
     * Apply discovered image sizes by registering them with WordPress.
     */
    public function apply(): void
    {
        $items = iterator_to_array($this->getAllItems());

        if ($items === []) {
            return;
        }

        // Auto-enable post-thumbnails theme support
        add_theme_support('post-thumbnails');

        foreach ($items as $item) {
            $this->registerImageSize($item);
        }
    }

    /**
     * Register a single image size with WordPress.
     *
     * @param array<string, mixed> $item
     */
    private function registerImageSize(array $item): void
    {
        add_image_size($item['name'], $item['width'], $item['height'], $item['crop']);
    }

    /**
     * Derive image size name from class name (PascalCase to snake_case).
     */
    private function deriveNameFromClass(string $className): string
    {
        // Remove common suffixes
        $name = preg_replace('/(?:Image|Size|ImageSize)$/', '', $className) ?? $className;

        // Convert PascalCase to snake_case
        $name = preg_replace('/([a-z])([A-Z])/', '$1_$2', $name) ?? $name;

        return strtolower($name);
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
            'name' => $item['name'],
            'width' => $item['width'],
            'height' => $item['height'],
            'crop' => $item['crop'],
            'className' => $item['className'],
        ];
    }
}
