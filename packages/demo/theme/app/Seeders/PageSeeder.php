<?php

declare(strict_types=1);

namespace Demo\Seeders;

use Studiometa\Foehn\Attributes\AsSeeder;
use Studiometa\Foehn\Contracts\SeederInterface;
use Studiometa\Foehn\Seeders\Upsert;
use WP_Block_Patterns_Registry;

/**
 * The Home, About and Legal pages.
 *
 * No "Projects" page: the post type archive at /projects/ is the listing, and a page
 * with that title would take the URL from it through WordPress's canonical redirect.
 */
#[AsSeeder(order: 30, description: 'The Home, About and Legal pages')]
final class PageSeeder implements SeederInterface
{
    private const string ABOUT_EXCERPT = 'Independent photographer, Strasbourg. Architecture, portraiture and still life, mostly on black and white film.';

    public function run(): void
    {
        Upsert::post('page', 'home', [
            'post_title' => 'Home',
            'post_content' => '',
        ]);

        $studio = Photographs::ofProject('_studio');

        $about = Upsert::post('page', 'about', [
            'post_title' => 'About',
            'post_excerpt' => self::ABOUT_EXCERPT,
            'post_content' => self::aboutContent($studio[1] ?? null),
        ]);

        // The Legal menu links here. A demo has no real publisher, so the page says
        // what a legal notice holds and gives example values only.
        Upsert::post('page', 'legal', [
            'post_title' => 'Legal',
            'post_content' => "<p>This site is a demonstration of the Føhn framework. The photographer, the clients and the testimonials are invented.</p>\n<h2>Publisher</h2>\n<p>Føhn demo, studio@foehn.test.</p>\n<h2>Photographs</h2>\n<p>The photographs come from Unsplash and are published under the Unsplash licence. Each one is credited to its author under the image.</p>\n<h2>Personal data</h2>\n<p>The site sets no tracking cookie and keeps no visitor data.</p>",
        ]);

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

    /**
     * The About page as blocks: what the theme's blocks, its binding and its pattern
     * look like on a page an editor wrote.
     *
     * A hero with the studio photograph, a paragraph bound to `theme/reading-time`,
     * the copy in core blocks, and the `theme/commission` pattern as the editor
     * inserts it.
     */
    private static function aboutContent(?int $background): string
    {
        $hero = self::block('theme/hero', [
            'title' => 'About',
            'subtitle' => self::ABOUT_EXCERPT,
            'backgroundId' => $background,
            'height' => 'small',
            'ctaLabel' => 'See the work',
            'ctaUrl' => '/projects/',
        ]);

        $readingTime = self::block(
            'paragraph',
            ['className' => 'label', 'metadata' => ['bindings' => ['content' => ['source' => 'theme/reading-time']]]],
            '<p class="label"></p>',
        );

        $copy = [
            self::paragraph(
                'I have been photographing since 2016, first for architects, now mostly for publishers and institutions. My personal work and my commissioned work look much the same: same equipment, same light, same refusal of colour.',
            ),
            self::paragraph(
                'Black and white is not nostalgia. It is a way of removing a variable — the colour of a wall, the colour of a sky — so that only structure and light are left. On a building site or in a studio it comes down to the same question: where is the line, and where is the light coming from?',
            ),
            self::block('heading', ['level' => 2], '<h2 class="wp-block-heading">Services</h2>'),
            self::paragraph(
                'Architectural reportage, editorial portraiture, still life and artwork reproduction. Prints available on request, in numbered limited editions.',
            ),
            self::block('heading', ['level' => 2], '<h2 class="wp-block-heading">Selected clients</h2>'),
            self::paragraph(
                'Water Journal, Grille Editions, Contrejour Review, and several architecture practices across the Rhine valley.',
            ),
        ];

        $pattern = WP_Block_Patterns_Registry::get_instance()->get_registered('theme/commission');

        return implode("\n\n", [$hero, $readingTime, ...$copy, is_array($pattern) ? $pattern['content'] : '']);
    }

    private static function paragraph(string $text): string
    {
        return self::block('paragraph', [], '<p>' . $text . '</p>');
    }

    /**
     * Block markup: the comment delimiters around the saved HTML, or a self-closing
     * comment for a dynamic block that saves none.
     *
     * @param array<string, mixed> $attributes
     */
    private static function block(string $name, array $attributes, string $html = ''): string
    {
        $attributes = array_filter($attributes, static fn(mixed $value): bool => $value !== null);
        $json = $attributes === []
            ? ''
            : ' ' . wp_json_encode($attributes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($html === '') {
            return "<!-- wp:{$name}{$json} /-->";
        }

        return "<!-- wp:{$name}{$json} -->\n{$html}\n<!-- /wp:{$name} -->";
    }
}
