<?php

declare(strict_types=1);

/**
 * Polylang, set up and read for `PolylangTest`, through `wp eval-file`.
 *
 * - `setup`: English (default) and French, `hello-world` in English and its French
 *   translation `bonjour`. Prints the id of `hello-world`.
 * - `switcher <query>`: `languages()` on the page of the query vars, as JSON.
 * - `setting <lang> <translation>`: a translatable setting, `Hello` in English and
 *   the translation in French, read in a language.
 * - `teardown`: removes the French post and the languages.
 *
 * Prints one line, because `Site::wp()` hands back the last line.
 */

use Studiometa\Foehn\Settings\Setting;
use Studiometa\Foehn\Settings\Settings;

/** @var list<string> $args */
$model = PLL()->model;

switch ($args[0] ?? '') {
    case 'setup':
        foreach ([
            ['English',  'en', 'en_US', 'us'],
            ['Français', 'fr', 'fr_FR', 'fr'],
        ] as [$name, $slug, $locale, $flag]) {
            if ($model->languages->get($slug)) {
                continue;
            }

            $language = [
                'name' => $name,
                'slug' => $slug,
                'locale' => $locale,
                'rtl' => false,
                'term_group' => 0,
                'flag' => $flag,
            ];
            $model->languages->add($language);
        }

        $english = get_page_by_path('hello-world', OBJECT, 'post');
        pll_set_post_language($english->ID, 'en');
        $french = get_page_by_path('bonjour', OBJECT, 'post')?->ID ?? wp_insert_post([
            'post_title' => 'Bonjour',
            'post_name' => 'bonjour',
            'post_status' => 'publish',
            'post_type' => 'post',
        ]);
        pll_set_post_language($french, 'fr');
        pll_save_post_translations(['en' => $english->ID, 'fr' => $french]);

        echo $english->ID;
        break;

    case 'switcher':
        parse_str($args[1] ?? '', $vars);
        wp($vars);

        echo json_encode(\Studiometa\Foehn\languages());
        break;

    case 'setting':
        Settings::register('smoke-texts', ['smoke_tagline' => Setting::string(translatable: true)], stdClass::class);
        update_option('smoke_tagline', 'Hello');
        update_option('smoke_tagline_fr', $args[2] ?? '');
        // WP-CLI runs Polylang as in the admin: the language of a front-end request is set by hand.
        PLL()->curlang = $model->languages->get($args[1] ?? 'en');

        echo Settings::get('smoke_tagline');

        delete_option('smoke_tagline');
        delete_option('smoke_tagline_fr');
        break;

    case 'teardown':
        $french = get_page_by_path('bonjour', OBJECT, 'post');

        if ($french !== null) {
            wp_delete_post($french->ID, true);
        }

        foreach ($model->languages->get_list() as $language) {
            $model->languages->delete($language->term_id);
        }

        echo 'done';
        break;
}
