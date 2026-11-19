<?php

declare(strict_types=1);

namespace Demo\Seeders;

use Studiometa\Foehn\Attributes\AsSeeder;
use Studiometa\Foehn\Contracts\SeederInterface;
use Studiometa\Foehn\Seeders\Upsert;
use WP_Block_Patterns_Registry;

/**
 * The Home, About, Journal and Legal pages, and a draft with accessibility issues.
 *
 * No "Projects" page: the post type archive at /projects/ is the listing, and a page
 * with that title would take the URL from it through WordPress's canonical redirect.
 */
#[AsSeeder(order: 30, description: 'The Home, About, Journal and Legal pages, and a draft with accessibility issues')]
final class PageSeeder implements SeederInterface
{
    private const string ABOUT_EXCERPT = 'Independent photographer, Strasbourg. Architecture, portraiture and still life, mostly on black and white film.';

    public function run(): void
    {
        // The sample page `wp core install` writes is not part of the site.
        $sample = get_page_by_path('sample-page');

        if ($sample !== null) {
            wp_delete_post($sample->ID, true);
        }

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

        // The posts page, set in SettingsSeeder: the journal lists here.
        Upsert::post('page', 'journal', [
            'post_title' => 'Journal',
            'post_content' => '',
        ]);

        // The Legal menu links here. A demo has no real publisher, so the page says
        // what a legal notice holds and gives example values only.
        Upsert::post('page', 'legal', [
            'post_title' => 'Legal',
            'post_content' => "<p>This site is a demonstration of the Føhn framework. The photographer, the clients and the testimonials are invented.</p>\n<h2>Publisher</h2>\n<p>Føhn demo, studio@foehn.test.</p>\n<h2>Photographs</h2>\n<p>The photographs come from Unsplash and are published under the Unsplash licence. Each one is credited to its author under the image.</p>\n<h2>Personal data</h2>\n<p>The site sets no tracking cookie and keeps no visitor data.</p>",
        ]);

        // A draft, so the site stays as it is. Its Publish button opens the
        // Accessibility panel of the pre-publish checks, which refuse to publish it.
        Upsert::post('page', 'accessibility-draft', [
            'post_title' => 'Accessibility draft',
            'post_status' => 'draft',
            'post_content' => self::accessibilityDraftContent($studio[1] ?? null),
        ]);

        if ($studio !== []) {
            set_post_thumbnail($about, $studio[0]);
        }
    }

    /**
     * The About page as blocks: what the theme's blocks, its binding and its pattern
     * look like on a page an editor wrote.
     *
     * A hero with the studio photograph, a paragraph bound to `theme/reading-time`,
     * the copy in core blocks, two paragraphs set in the "Schedule" panel, the
     * `theme/commission` pattern as the editor inserts it, and the `theme/faq`
     * block, which also gives the page its `FAQPage` JSON-LD.
     */
    private static function aboutContent(?int $background): string
    {
        $hero = BlockMarkup::block('theme/hero', [
            'title' => 'About',
            'subtitle' => self::ABOUT_EXCERPT,
            'backgroundId' => $background,
            'height' => 'small',
            'ctaLabel' => 'See the work',
            'ctaUrl' => '/projects/',
        ]);

        $readingTime = BlockMarkup::block(
            'paragraph',
            ['className' => 'label', 'metadata' => ['bindings' => ['content' => ['source' => 'theme/reading-time']]]],
            '<p class="label"></p>',
        );

        $copy = [
            BlockMarkup::paragraph(
                'I have been photographing since 2016, first for architects, now mostly for publishers and institutions. My personal work and my commissioned work look much the same: same equipment, same light, same refusal of colour.',
            ),
            BlockMarkup::paragraph(
                'Black and white is not nostalgia. It is a way of removing a variable — the colour of a wall, the colour of a sky — so that only structure and light are left. On a building site or in a studio it comes down to the same question: where is the line, and where is the light coming from?',
            ),
            BlockMarkup::block('heading', ['level' => 2], '<h2 class="wp-block-heading">Services</h2>'),
            BlockMarkup::paragraph(
                'Architectural reportage, editorial portraiture, still life and artwork reproduction. Prints available on request, in numbered limited editions.',
            ),
            // The theme's #[AsShortcode], in the block editor's own shortcode block.
            BlockMarkup::block('shortcode', [], '[edition size="15" paper="baryta" format="40x50 cm"]'),
            BlockMarkup::block('heading', ['level' => 2], '<h2 class="wp-block-heading">Selected clients</h2>'),
            BlockMarkup::paragraph(
                'Water Journal, Grille Editions, Contrejour Review, and several architecture practices across the Rhine valley.',
            ),
            // Scheduled blocks: the first shows between its two dates, so a visitor
            // sees it now. The second shows only from a date in the future.
            BlockMarkup::block(
                'paragraph',
                ['visibleFrom' => '2026-01-01T09:00', 'visibleUntil' => '2099-01-01T09:00'],
                '<p>Open studio on the first Saturday of each month, by appointment.</p>',
            ),
            BlockMarkup::block(
                'paragraph',
                ['visibleFrom' => '2099-01-01T09:00'],
                '<p>The retrospective opens at the studio on 1 January 2099.</p>',
            ),
        ];

        $pattern = WP_Block_Patterns_Registry::get_instance()->get_registered('theme/commission');

        $faq = BlockMarkup::block('theme/faq', ['title' => 'Questions'], implode("\n", [
            BlockMarkup::block('theme/faq-question', [
                'question' => 'What does a commission cost?',
                'answer' => 'A day rate for architecture and editorial work, a per-image rate for still life and reproduction. Both include a first selection and one round of edits; usage rights are quoted separately because they depend on where the images run.',
            ]),
            BlockMarkup::block('theme/faq-question', [
                'question' => 'Do you shoot in colour?',
                'answer' => 'Yes, when the brief asks for it. The personal work is black and white because removing colour removes a variable, not because colour is worse — a commission that needs it gets it.',
            ]),
            BlockMarkup::block('theme/faq-question', [
                'question' => 'How long until I see the images?',
                'answer' => 'A contact sheet within a week of the shoot, final files two weeks after selection. Film adds a few days for processing, which is worth saying out loud before a deadline is agreed rather than after.',
            ]),
            BlockMarkup::block('theme/faq-question', [
                'question' => 'Are prints available?',
                'answer' => 'From any series, in numbered editions of fifteen, printed on baryta paper at 40x50 cm. Larger formats on request.',
            ]),
        ]));

        return implode("\n\n", [$hero, $readingTime, ...$copy, is_array($pattern) ? $pattern['content'] : '', $faq]);
    }

    /**
     * Content that the pre-publish checks refuse: a hero at level 1 and a second
     * level 1 heading, a jump from h2 to h4, and an image with no alternative text.
     */
    private static function accessibilityDraftContent(?int $image): string
    {
        $blocks = [
            BlockMarkup::block('theme/hero', [
                'title' => 'Accessibility draft',
                'headingLevel' => 1,
                'height' => 'small',
            ]),
            BlockMarkup::paragraph(
                'This draft has accessibility issues on purpose. Click Publish: the Accessibility panel lists them, and the editor refuses to publish until they are fixed.',
            ),
            BlockMarkup::block('heading', ['level' => 1], '<h1 class="wp-block-heading">A second level 1 heading</h1>'),
            BlockMarkup::block('heading', ['level' => 2], '<h2 class="wp-block-heading">A level 2 heading</h2>'),
            BlockMarkup::block(
                'heading',
                ['level' => 4],
                '<h4 class="wp-block-heading">A level 4 heading after a level 2</h4>',
            ),
        ];

        if ($image !== null) {
            $src = (string) wp_get_attachment_url($image);

            $blocks[] = BlockMarkup::block(
                'image',
                ['id' => $image, 'sizeSlug' => 'large'],
                "<figure class=\"wp-block-image size-large\"><img src=\"{$src}\" alt=\"\" class=\"wp-image-{$image}\"/></figure>",
            );
        }

        return implode("\n\n", $blocks);
    }
}
