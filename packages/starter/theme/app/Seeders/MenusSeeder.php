<?php

declare(strict_types=1);

namespace App\Seeders;

use RuntimeException;
use Studiometa\Foehn\Attributes\AsSeeder;
use Studiometa\Foehn\Contracts\SeederInterface;

/**
 * The header, footer and legal menus, each with a Home link, assigned to the
 * locations that the classes in `app/Menus/` register.
 *
 * A menu is found by name and created only when it is missing, so a second run
 * creates nothing and keeps the items that editors added.
 */
#[AsSeeder(order: 10, description: 'The header, footer and legal menus')]
final class MenusSeeder implements SeederInterface
{
    /** Menu name of each location. */
    private const MENUS = ['header' => 'Header', 'footer' => 'Footer', 'legal' => 'Legal'];

    public function run(): void
    {
        $locations = get_theme_mod('nav_menu_locations') ?: [];

        foreach (self::MENUS as $location => $name) {
            $locations[$location] = $this->menu($name);
        }

        set_theme_mod('nav_menu_locations', $locations);
    }

    private function menu(string $name): int
    {
        $menu = wp_get_nav_menu_object($name);

        if ($menu !== false) {
            return $menu->term_id;
        }

        $id = wp_create_nav_menu($name);

        if (is_wp_error($id)) {
            throw new RuntimeException("could not create the {$name} menu: " . $id->get_error_message());
        }

        wp_update_nav_menu_item($id, 0, [
            'menu-item-title' => 'Home',
            'menu-item-url' => '/',
            'menu-item-status' => 'publish',
        ]);

        return $id;
    }
}
