<?php

declare(strict_types=1);

namespace Demo\Seeders;

use RuntimeException;
use Studiometa\Foehn\Attributes\AsSeeder;
use Studiometa\Foehn\Contracts\SeederInterface;

/**
 * The site options: title, front page, permalinks and the theme settings.
 *
 * Runs after PageSeeder, whose Home page becomes the front page.
 */
#[AsSeeder(order: 50, description: 'Site title, front page, permalinks and theme settings')]
final class SettingsSeeder implements SeederInterface
{
    public function run(): void
    {
        $home = get_page_by_path('home');

        if ($home === null) {
            throw new RuntimeException('the Home page does not exist. Run PageSeeder first.');
        }

        update_option('blogname', 'Føhn');
        update_option('blogdescription', 'Architecture, portrait and still-life photography. Strasbourg.');
        update_option('show_on_front', 'page');
        update_option('page_on_front', $home->ID);
        update_option('page_for_posts', get_page_by_path('journal')?->ID ?? 0);
        update_option('permalink_structure', '/%postname%/');
        update_option('demo_contact_email', 'studio@foehn.test');
        // On, because the public demo has to say that it is one.
        update_option('demo_show_banner', true);
        update_option(
            'demo_banner_text',
            'A demonstration of the Føhn framework. The photographer, the clients and the testimonials are invented.',
        );
        update_option('demo_posts_per_archive', 3);

        flush_rewrite_rules(false);
    }
}
