<?php

declare(strict_types=1);

namespace Demo\Seeders;

use RuntimeException;
use Studiometa\Foehn\Attributes\AsSeeder;
use Studiometa\Foehn\Contracts\SeederInterface;

/**
 * The header, footer and legal menus, assigned to the locations that the theme's
 * #[AsMenu] classes register.
 *
 * A menu is found by name, and its items are written again on every run, so a
 * second run gives the same menu and no duplicate items. Runs after SettingsSeeder,
 * because the About URL depends on the permalink structure.
 */
#[AsSeeder(order: 60, description: 'The header, footer and legal menus')]
final class MenuSeeder implements SeederInterface
{
    public function run(): void
    {
        $aboutUrl = self::pageUrl('about');
        $legalUrl = self::pageUrl('legal');

        $menus = [
            'header' => [
                ['Projects', '/projects/'],
                ['About',    $aboutUrl],
            ],
            'footer' => [
                ['Projects', '/projects/'],
                ['About',    $aboutUrl],
            ],
            'legal' => [['Legal', $legalUrl]],
        ];

        foreach ($menus as $location => $items) {
            $this->writeMenu($location, $items);
        }
    }

    /**
     * The URL of a page PageSeeder wrote.
     */
    private static function pageUrl(string $slug): string
    {
        $page = get_page_by_path($slug);

        if ($page === null) {
            throw new RuntimeException("the {$slug} page does not exist. Run PageSeeder first.");
        }

        return (string) get_permalink($page);
    }

    /**
     * @param list<array{string, string}> $items Label and URL of each item
     */
    private function writeMenu(string $location, array $items): void
    {
        $name = 'Demo ' . $location;
        $term = wp_get_nav_menu_object($name) ?: wp_create_nav_menu($name);

        if (is_wp_error($term)) {
            throw new RuntimeException("could not create the {$name} menu: " . $term->get_error_message());
        }

        $menuId = is_object($term) ? $term->term_id : $term;

        foreach (wp_get_nav_menu_items($menuId) ?: [] as $item) {
            wp_delete_post($item->ID, true);
        }

        foreach ($items as [$label, $url]) {
            wp_update_nav_menu_item($menuId, 0, [
                'menu-item-title' => $label,
                'menu-item-url' => $url,
                'menu-item-status' => 'publish',
            ]);
        }

        $locations = get_theme_mod('nav_menu_locations') ?: [];
        $locations[$location] = $menuId;
        set_theme_mod('nav_menu_locations', $locations);
    }
}
