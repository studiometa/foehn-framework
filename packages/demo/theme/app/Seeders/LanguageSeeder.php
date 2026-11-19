<?php

declare(strict_types=1);

namespace Demo\Seeders;

use RuntimeException;
use Studiometa\Foehn\Attributes\AsSeeder;
use Studiometa\Foehn\Contracts\SeederInterface;
use Studiometa\Foehn\Seeders\Upsert;

/**
 * English and French with Polylang, and the French home page.
 *
 * Runs last: the content the other seeders wrote has no language yet, and gets
 * English, the default language. The French home page "Accueil" is the translation
 * of Home, so the language switcher links the two front pages. The other pages are
 * in English only, and their switcher links French to the French home page.
 */
#[AsSeeder(order: 70, description: 'English and French with Polylang, and the French home page')]
final class LanguageSeeder implements SeederInterface
{
    /** Code, locale and name of each language, the default one first. */
    private const array LANGUAGES = [
        ['slug' => 'en', 'locale' => 'en_US', 'name' => 'English'],
        ['slug' => 'fr', 'locale' => 'fr_FR', 'name' => 'Français'],
    ];

    public function run(): void
    {
        if (!function_exists('PLL')) {
            throw new RuntimeException('Polylang is not active. Run `wp plugin activate polylang` first.');
        }

        $model = PLL()->model;

        foreach (self::LANGUAGES as $order => $language) {
            if (!$model->languages->get($language['slug'])) {
                $model->languages->add([...$language, 'term_group' => $order, 'no_default_cat' => true]);
            }
        }

        $model->set_language_in_mass();
        // The French home page is /fr/, not /fr/accueil/.
        $model->options->set('redirect_lang', true);

        $home = get_page_by_path('home');

        if ($home === null) {
            throw new RuntimeException('the Home page does not exist. Run PageSeeder first.');
        }

        $accueil = Upsert::post('page', 'accueil', ['post_title' => 'Accueil', 'post_content' => '']);
        // Set again, so Polylang does not read the language from a cache made before the mass update.
        pll_set_post_language($home->ID, 'en');
        pll_set_post_language($accueil, 'fr');
        pll_save_post_translations(['en' => $home->ID, 'fr' => $accueil]);
        // The cached languages hold the front page of each language, which is new.
        $model->languages->clean_cache();

        // The French pages show the same menus. Polylang saves its options at shutdown.
        $theme = (string) get_option('stylesheet');
        $menus = $model->options->get('nav_menus');

        foreach (get_theme_mod('nav_menu_locations') ?: [] as $location => $menu) {
            $menus[$theme][$location] = ['en' => $menu, 'fr' => $menu];
        }

        $model->options->set('nav_menus', $menus);
    }
}
