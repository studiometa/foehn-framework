<?php

declare(strict_types=1);

/**
 * Build the demo site from scratch: photographs, projects, pages, menus, settings.
 *
 *     wp eval-file database/seed.php
 *
 * Idempotent — it looks a post up by slug before creating it, so running it twice
 * updates rather than duplicates. `database/restore.sh` runs this when there is no
 * SQL dump to import, and the dump itself is produced from a site seeded this way.
 *
 * Photograph credits come from `database/media/credits.json`, which is written by
 * the fetch script against the Unsplash API. Every attachment carries the
 * photographer's name and profile URL as post meta, because the licence asks for
 * attribution and a template can only print what the database holds.
 */

$root = dirname(__DIR__);
$mediaDir = $root . '/database/media';
$credits = json_decode((string) file_get_contents($mediaDir . '/credits.json'), true, 512, JSON_THROW_ON_ERROR);

/**
 * Import one photograph, or find the one already imported, and stamp its credit.
 *
 * @param array<string, mixed> $entry
 */
$importPhotograph = static function (array $entry) use ($mediaDir): int {
    $existing = get_posts([
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'meta_key' => 'unsplash_id',
        'meta_value' => $entry['unsplash_id'],
        'posts_per_page' => 1,
        'fields' => 'ids',
    ]);

    $id = $existing[0] ?? null;

    if ($id === null) {
        // media_handle_sideload moves the file it is given, so it gets a copy.
        $temporary = wp_tempnam($entry['file']);
        copy($mediaDir . '/' . $entry['file'], $temporary);

        $id = media_handle_sideload(
            [
                'name' => $entry['file'],
                'tmp_name' => $temporary,
            ],
            0,
            $entry['description'] ?: null,
        );

        if (is_wp_error($id)) {
            @unlink($temporary);

            WP_CLI::error("could not import {$entry['file']}: " . $id->get_error_message());
        }
    }

    update_post_meta($id, 'unsplash_id', $entry['unsplash_id']);
    update_post_meta($id, 'credit_photographer', $entry['photographer']);
    update_post_meta($id, 'credit_url', $entry['photographer_url']);
    update_post_meta($id, 'credit_source', $entry['page']);
    update_post_meta($id, '_wp_attachment_image_alt', $entry['description'] ?: $entry['photographer']);

    return (int) $id;
};

/**
 * Create or update a post by slug.
 *
 * @param array<string, mixed> $data
 * @param array<string, mixed> $meta
 */
$upsert = static function (string $type, string $slug, array $data, array $meta = []): int {
    $existing = get_posts([
        'post_type' => $type,
        'post_status' => 'any',
        'name' => $slug,
        'posts_per_page' => 1,
        'fields' => 'ids',
    ]);

    $payload = array_merge([
        'post_type' => $type,
        'post_name' => $slug,
        'post_status' => 'publish',
    ], $data);

    if ($existing !== []) {
        $payload['ID'] = $existing[0];
    }

    $id = wp_insert_post($payload, true);

    if (is_wp_error($id)) {
        WP_CLI::error("could not write {$type}/{$slug}: " . $id->get_error_message());
    }

    foreach ($meta as $key => $value) {
        update_post_meta($id, $key, $value);
    }

    return (int) $id;
};

// ──────────────────────────────────────────────
// Photographs
// ──────────────────────────────────────────────

require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

/** @var array<string, list<int>> $byProject */
$byProject = [];

foreach ($credits as $entry) {
    $byProject[$entry['project']][] = $importPhotograph($entry);
}

WP_CLI::log(sprintf('%d photographs imported', array_sum(array_map('count', $byProject))));

// ──────────────────────────────────────────────
// Projects
// ──────────────────────────────────────────────

$projects = [
    'corridors' => [
        'title' => 'Corridors',
        'excerpt' => 'Six months spent in the circulation spaces of office buildings, photographing what nobody looks at on the way through.',
        'content' => "<p>The series began with a delay: two hours waiting in a lobby, and nothing to look at but a corridor. Office buildings treat their circulation as plumbing — no intention, no display. That is exactly what makes them photogenic. The light is blunt, the lines are clean, and nothing has been arranged to be seen.</p>\n<p>Shot on film, square negatives cropped to 4:3 at the print stage.</p>",
        'client' => 'Self-initiated',
        'year' => 2024,
        'location' => 'Strasbourg, Basel',
        'camera' => 'Hasselblad 500C/M — Ilford HP5+',
        'category' => 'Architecture',
    ],
    'silhouettes' => [
        'title' => 'Silhouettes',
        'excerpt' => 'Bodies against the light, reduced to an outline. What is left of a portrait once you take the face away.',
        'content' => "<p>Is a portrait without a face still a portrait? The series started as a constraint on a commission — a client who wanted nobody identifiable — and carried on long after the work was delivered.</p>\n<p>Every frame is a single exposure. No outline has been retouched.</p>",
        'client' => 'Contrejour Review',
        'year' => 2023,
        'location' => 'Studio, Strasbourg',
        'camera' => 'Nikon FM2 — Kodak Tri-X 400',
        'category' => 'Portrait',
    ],
    'winter' => [
        'title' => 'Winter',
        'excerpt' => 'The Vosges under snow, where the landscape loses its mid-tones and keeps only two values.',
        'content' => "<p>Snow does to a landscape what black and white does to a photograph: it removes everything that is not structure. A single tree in a snowed field stops being a tree and becomes a stroke.</p>\n<p>Made over three winters, always in the same place.</p>",
        'client' => 'Self-initiated',
        'year' => 2022,
        'location' => 'Vosges',
        'camera' => 'Mamiya 7 — Fuji Acros 100',
        'category' => 'Landscape',
    ],
    'objects' => [
        'title' => 'Objects',
        'excerpt' => 'Contemporary still life: whatever is lying on the table, lit as though it were a subject.',
        'content' => '<p>A publishing commission that asked for “neutral” images to sit alongside a text. The brief said: nothing that tells a story. It turned out to be impossible to honour.</p>',
        'client' => 'Grille Editions',
        'year' => 2024,
        'location' => 'Studio, Strasbourg',
        'camera' => 'Digital — 100 mm macro',
        'category' => 'Still life',
    ],
    'osaka' => [
        'title' => 'Osaka',
        'excerpt' => 'Three weeks walking a city whose signs I could not read.',
        'content' => "<p>Not understanding a city's language is the best way to see its shape. With the text gone you are left looking at surfaces, at floors, at the way light falls between two buildings.</p>",
        'client' => 'Self-initiated',
        'year' => 2023,
        'location' => 'Osaka, Japan',
        'camera' => 'Leica M6 — Kodak Tri-X 400',
        'category' => 'Documentary',
    ],
    'editions' => [
        'title' => 'Editions',
        'excerpt' => 'Commissioned work for independent publishers: books, journals, stationery.',
        'content' => '<p>Photographing printed paper means solving one simple, tedious problem: the white of the paper is never the white of the backdrop. The series documents a method as much as a set of objects.</p>',
        'client' => 'Water Journal, Grille Editions',
        'year' => 2025,
        'location' => 'Studio, Strasbourg',
        'camera' => 'Digital — copy stand',
        'category' => 'Still life',
    ],
];

$order = 0;

foreach ($projects as $slug => $project) {
    $gallery = $byProject[$slug] ?? [];

    $id = $upsert(
        'project',
        $slug,
        [
            'post_title' => $project['title'],
            'post_excerpt' => $project['excerpt'],
            'post_content' => $project['content'],
            'menu_order' => ++$order,
        ],
        [
            'client' => $project['client'],
            'year' => $project['year'],
            'location' => $project['location'],
            'camera' => $project['camera'],
            'gallery' => $gallery,
        ],
    );

    if ($gallery !== []) {
        set_post_thumbnail($id, $gallery[0]);
    }

    wp_set_object_terms($id, $project['category'], 'project_category');
}

WP_CLI::log(sprintf('%d projects', count($projects)));

// ──────────────────────────────────────────────
// Pages
// ──────────────────────────────────────────────

$studio = $byProject['_studio'] ?? [];

$home = $upsert('page', 'home', [
    'post_title' => 'Home',
    'post_content' => '',
]);

$about = $upsert('page', 'about', [
    'post_title' => 'About',
    'post_excerpt' => 'Independent photographer, Strasbourg. Architecture, portraiture and still life, mostly on black and white film.',
    'post_content' => "<p>I have been photographing since 2016, first for architects, now mostly for publishers and institutions. My personal work and my commissioned work look much the same: same equipment, same light, same refusal of colour.</p>\n<p>Black and white is not nostalgia. It is a way of removing a variable — the colour of a wall, the colour of a sky — so that only structure and light are left. On a building site or in a studio it comes down to the same question: where is the line, and where is the light coming from?</p>\n<h2>Services</h2>\n<p>Architectural reportage, editorial portraiture, still life and artwork reproduction. Prints available on request, in numbered limited editions.</p>\n<h2>Selected clients</h2>\n<p>Water Journal, Grille Editions, Contrejour Review, and several architecture practices across the Rhine valley.</p>",
]);

if ($studio !== []) {
    set_post_thumbnail($about, $studio[0]);
}

// No "Projets" page: the post type archive at /projets/ is the listing, and a page
// with that title would take the URL from it through WordPress's canonical redirect.

// ──────────────────────────────────────────────
// Testimonials
// ──────────────────────────────────────────────

$testimonials = [
    'water-journal' => [
        'title' => 'Water Journal',
        'content' => 'She shot four issues for us. The images hold together without ever repeating themselves, which is exactly what you ask of a series.',
        'author_name' => 'Water Journal',
        'author_role' => 'Art direction',
    ],
    'grille' => [
        'title' => 'Grille Editions',
        'content' => 'An impossible brief, met anyway. She argues with the constraints before the shoot, never after.',
        'author_name' => 'Grille Editions',
        'author_role' => 'Publishing',
    ],
    'contrejour' => [
        'title' => 'Contrejour Review',
        'content' => 'The faceless portrait became the cover of the issue. We had not commissioned it.',
        'author_name' => 'Contrejour Review',
        'author_role' => 'Editor in chief',
    ],
];

foreach ($testimonials as $slug => $testimonial) {
    $upsert(
        'testimonial',
        $slug,
        [
            'post_title' => $testimonial['title'],
            'post_content' => $testimonial['content'],
        ],
        [
            'author_name' => $testimonial['author_name'],
            'author_role' => $testimonial['author_role'],
        ],
    );
}

// ──────────────────────────────────────────────
// Site configuration
// ──────────────────────────────────────────────

update_option('blogname', 'Føhn');
update_option('blogdescription', 'Architecture, portrait and still-life photography. Strasbourg.');
update_option('show_on_front', 'page');
update_option('page_on_front', $home);
update_option('permalink_structure', '/%postname%/');
update_option('demo_contact_email', 'studio@foehn.test');
update_option('demo_show_banner', false);
update_option('demo_posts_per_archive', 12);

// Menus, which the theme's #[AsMenu] classes register locations for.
$menus = [
    'header' => [
        ['Projects', '/projects/'],
        ['About',    get_permalink($about)],
    ],
    'footer' => [
        ['Projects', '/projects/'],
        ['About',    get_permalink($about)],
    ],
    'legal' => [['Legal', '/legal/']],
];

foreach ($menus as $location => $items) {
    $name = 'Demo ' . $location;
    $term = wp_get_nav_menu_object($name) ?: wp_create_nav_menu($name);
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

flush_rewrite_rules(false);

WP_CLI::success('demo content seeded');
