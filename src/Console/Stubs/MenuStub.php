<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Stubs;

use Studiometa\Foehn\Attributes\AsMenu;
use Tempest\Discovery\SkipDiscovery;
use Timber\Menu;
use Timber\Timber;

/**
 * DummyMenu - Navigation menu location.
 *
 * This class registers a navigation menu location and provides
 * helper methods to retrieve the menu for use in templates.
 */
#[SkipDiscovery]
#[AsMenu(location: 'dummy-menu', description: 'Dummy Menu')]
final class MenuStub
{
    /**
     * Get the menu for this location.
     *
     * @param array<string, mixed> $args Optional arguments passed to Timber::get_menu()
     */
    public static function get(array $args = []): ?Menu
    {
        return Timber::get_menu('dummy-menu', $args);
    }

    /**
     * Check if this menu has items assigned.
     */
    public static function hasItems(): bool
    {
        $menu = self::get();

        return $menu !== null && $menu->items !== null && count($menu->items) > 0;
    }
}
