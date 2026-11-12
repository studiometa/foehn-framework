<?php

declare(strict_types=1);

namespace Demo\Seeders;

use Studiometa\Foehn\Attributes\AsSeeder;
use Studiometa\Foehn\Contracts\SeederInterface;
use Studiometa\Foehn\Seeders\Upsert;

/**
 * The Home, About and Legal pages.
 *
 * No "Projects" page: the post type archive at /projects/ is the listing, and a page
 * with that title would take the URL from it through WordPress's canonical redirect.
 */
#[AsSeeder(order: 30, description: 'The Home, About and Legal pages')]
final class PageSeeder implements SeederInterface
{
    public function run(): void
    {
        Upsert::post('page', 'home', [
            'post_title' => 'Home',
            'post_content' => '',
        ]);

        $about = Upsert::post('page', 'about', [
            'post_title' => 'About',
            'post_excerpt' => 'Independent photographer, Strasbourg. Architecture, portraiture and still life, mostly on black and white film.',
            'post_content' => "<p>I have been photographing since 2016, first for architects, now mostly for publishers and institutions. My personal work and my commissioned work look much the same: same equipment, same light, same refusal of colour.</p>\n<p>Black and white is not nostalgia. It is a way of removing a variable — the colour of a wall, the colour of a sky — so that only structure and light are left. On a building site or in a studio it comes down to the same question: where is the line, and where is the light coming from?</p>\n<h2>Services</h2>\n<p>Architectural reportage, editorial portraiture, still life and artwork reproduction. Prints available on request, in numbered limited editions.</p>\n<h2>Selected clients</h2>\n<p>Water Journal, Grille Editions, Contrejour Review, and several architecture practices across the Rhine valley.</p>",
        ]);

        // The Legal menu links here. A demo has no real publisher, so the page says
        // what a legal notice holds and gives example values only.
        Upsert::post('page', 'legal', [
            'post_title' => 'Legal',
            'post_content' => "<p>This site is a demonstration of the Føhn framework. The photographer, the clients and the testimonials are invented.</p>\n<h2>Publisher</h2>\n<p>Føhn demo, studio@foehn.test.</p>\n<h2>Photographs</h2>\n<p>The photographs come from Unsplash and are published under the Unsplash licence. Each one is credited to its author under the image.</p>\n<h2>Personal data</h2>\n<p>The site sets no tracking cookie and keeps no visitor data.</p>",
        ]);

        $studio = Photographs::ofProject('_studio');

        if ($studio !== []) {
            set_post_thumbnail($about, $studio[0]);
        }

        // Rendered with @ui/Accordion, which is the studiometa/ui component the demo uses
        // to show the Twig namespace and the JS autoloader working together.
        update_post_meta($about, 'faq', [
            [
                'title' => 'What does a commission cost?',
                'content' => 'A day rate for architecture and editorial work, a per-image rate for still life and reproduction. Both include a first selection and one round of edits; usage rights are quoted separately because they depend on where the images run.',
            ],
            [
                'title' => 'Do you shoot in colour?',
                'content' => 'Yes, when the brief asks for it. The personal work is black and white because removing colour removes a variable, not because colour is worse — a commission that needs it gets it.',
            ],
            [
                'title' => 'How long until I see the images?',
                'content' => 'A contact sheet within a week of the shoot, final files two weeks after selection. Film adds a few days for processing, which is worth saying out loud before a deadline is agreed rather than after.',
            ],
            [
                'title' => 'Are prints available?',
                'content' => 'From any series, in numbered editions of fifteen, printed on baryta paper at 40x50 cm. Larger formats on request.',
            ],
        ]);
    }
}
