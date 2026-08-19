<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use ReflectionClass;
use Studiometa\Foehn\Attributes\AsMenu;
use Studiometa\Foehn\Discovery\Concerns\CacheableDiscovery;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Timber\Timber;

/**
 * Discovers classes marked with #[AsMenu] attribute
 * and registers them as WordPress navigation menu locations.
 */
final class MenuDiscovery implements WpDiscovery
{
    use IsWpDiscovery;
    use CacheableDiscovery;

    /**
     * Discover menu attributes on classes.
     *
     * @param DiscoveryLocation $location
     * @param ReflectionClass<object> $class
     */
    public function discover(DiscoveryLocation $location, ReflectionClass $class): void
    {
        $attributes = $class->getAttributes(AsMenu::class);

        if ($attributes === []) {
            return;
        }

        $attribute = $attributes[0]->newInstance();

        $this->addItem($location, [
            'attribute' => $attribute,
            'className' => $class->getName(),
        ]);
    }

    /**
     * Apply discovered menus by registering them with WordPress
     * and adding them to the Timber context.
     */
    public function apply(): void
    {
        $menus = [];

        foreach ($this->getItems() as $item) {
            /** @var AsMenu $attribute */
            $attribute = $item['attribute'];
            $menus[$attribute->location] = $attribute->description;
        }

        if ($menus === []) {
            return;
        }

        // Register menu locations with WordPress
        register_nav_menus($menus);

        // Add menus to Timber context
        $this->addMenusToTimberContext(array_keys($menus));
    }

    /**
     * Add menus to the Timber context under the 'menus' key.
     *
     * @param array<string> $locations Menu location slugs
     */
    private function addMenusToTimberContext(array $locations): void
    {
        add_filter('timber/context', static function (array $context) use ($locations): array {
            if (($context['menus'] ?? null) === null) {
                $context['menus'] = [];
            }

            foreach ($locations as $location) {
                // Only add the menu if it has been assigned in WordPress admin
                if (!has_nav_menu($location)) {
                    continue;
                }

                $context['menus'][$location] = Timber::get_menu_by('location', $location);
            }

            return $context;
        });
    }
}
