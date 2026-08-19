<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use Studiometa\Foehn\Attributes\AsImageSize;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;

/**
 * Discovers classes marked with #[AsImageSize] attribute
 * and registers them as WordPress custom image sizes.
 */
final class ImageSizeDiscovery implements Discovery
{
    use IsWpDiscovery;

    /**
     * Discover image size attributes on classes.
     *
     * @param DiscoveryLocation $location
     * @param ClassReflector $class
     */
    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        if (!$this->isConcrete($class)) {
            return;
        }

        $attribute = $class->getAttribute(AsImageSize::class);

        if ($attribute === null) {
            return;
        }

        $this->addItem($location, [
            'attribute' => $attribute,
            'className' => $class->getName(),
        ]);
    }

    /**
     * Apply discovered image sizes by registering them with WordPress.
     */
    public function apply(): void
    {
        $items = iterator_to_array($this->getItems());

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
        /** @var AsImageSize $attribute */
        $attribute = $item['attribute'];
        /** @var class-string $className */
        $className = $item['className'];

        $name = $attribute->name ?? self::deriveNameFromClass($className);

        add_image_size($name, $attribute->width, $attribute->height, $attribute->crop);
    }

    /**
     * Derive image size name from class name (PascalCase to snake_case).
     *
     * @param class-string $className Fully qualified class name
     */
    private static function deriveNameFromClass(string $className): string
    {
        $shortName = substr((string) strrchr('\\' . $className, '\\'), 1);

        // Remove common suffixes
        $name = preg_replace('/(?:Image|Size|ImageSize)$/', '', $shortName) ?? $shortName;

        // Convert PascalCase to snake_case
        $name = preg_replace('/([a-z])([A-Z])/', '$1_$2', $name) ?? $name;

        return strtolower($name);
    }
}
