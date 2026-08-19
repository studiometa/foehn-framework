<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use Studiometa\Foehn\Attributes\AsMenu;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;
use Timber\Timber;

/**
 * Discovers classes marked with #[AsMenu] attribute
 * and registers them as WordPress navigation menu locations.
 */
final class MenuDiscovery implements Discovery
{
    use IsWpDiscovery;

    /**
     * Discover menu attributes on classes.
     *
     * @param DiscoveryLocation $location
     * @param ClassReflector $class
     */
    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        if (!$this->isConcrete($class)) {
            return;
        }

        $attribute = $class->getAttribute(AsMenu::class);

        if ($attribute === null) {
            return;
        }

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
