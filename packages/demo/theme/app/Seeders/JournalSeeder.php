<?php

declare(strict_types=1);

namespace Demo\Seeders;

use RuntimeException;
use Studiometa\Foehn\Attributes\AsSeeder;
use Studiometa\Foehn\Contracts\SeederInterface;
use Studiometa\Foehn\Seeders\Upsert;

/**
 * The journal: three posts in the Notes category, with tags.
 *
 * The posts are where the theme's content blocks are seen: the contact sheet, the
 * facts, related projects, the press kit, and a core image that ContentImageHooks
 * sizes through the transformer. They also give `wp foehn inspect templates` a URL
 * for the single, category and tag templates.
 *
 * The third post is protected by the password `foehn`, which the README gives, so
 * the password form can be tried on a seeded site.
 *
 * Runs after ProjectSeeder, whose projects the related-projects blocks link.
 */
#[AsSeeder(order: 35, description: 'Three journal posts, one of them password-protected')]
final class JournalSeeder implements SeederInterface
{
    /** The password of the protected post. The README gives it. */
    public const string PASSWORD = 'foehn';

    public function run(): void
    {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $corridors = Photographs::ofProject('corridors');
        $editions = Photographs::ofProject('editions');

        $this->post('notes-on-corridors', 'Notes on Corridors', ['film', 'architecture'], implode("\n\n", [
            BlockMarkup::paragraph(
                'Corridors took six months and about four hundred frames. These are the ones that did not make the series, and the reasons why.',
            ),
            BlockMarkup::block('theme/contact-sheet', [
                'imageIds' => array_slice($corridors, 0, 5),
                'caption' => 'Contact sheet, Corridors, 2024',
            ]),
            BlockMarkup::paragraph(
                'Every frame was made with the light that was there. The rules for the series fit in a few lines:',
            ),
            BlockMarkup::block('theme/facts', [], implode("\n", [
                BlockMarkup::block('theme/fact', ['label' => 'Camera', 'value' => 'Hasselblad 500C/M']),
                BlockMarkup::block('theme/fact', ['label' => 'Film', 'value' => 'Ilford HP5+, pushed one stop']),
                BlockMarkup::block('theme/fact', ['label' => 'Light', 'value' => 'Available light only']),
            ])),
            self::image($corridors[5] ?? $corridors[0] ?? null),
            BlockMarkup::block('theme/related-projects', [
                'projectIds' => self::projectIds(['corridors', 'osaka']),
                'title' => 'The series these notes are about',
            ]),
        ]));

        $this->post('editions-press-kit', 'A press kit for Editions', ['publishing'], implode("\n\n", [
            BlockMarkup::paragraph(
                'Editions is in two bookshops this spring. For the people who write about it, the press kit has the texts, the credits and the conditions of use of the images.',
            ),
            BlockMarkup::block('theme/press-kit', [
                'fileId' => $this->pressKit(),
                'label' => 'Download the Editions press kit',
            ]),
            self::image($editions[0] ?? null),
            BlockMarkup::block('theme/related-projects', ['projectIds' => self::projectIds(['editions', 'objects'])]),
        ]));

        $this->post(
            'studio-contact-sheets',
            'Contact sheets for the studio',
            ['film'],
            BlockMarkup::paragraph(
                'The full contact sheets of the winter series, for the people who work on the prints. Ask the studio for the password.',
            ),
            self::PASSWORD,
        );
    }

    /**
     * @param list<string> $tags
     */
    private function post(string $slug, string $title, array $tags, string $content, string $password = ''): void
    {
        $id = Upsert::post('post', $slug, [
            'post_title' => $title,
            'post_content' => $content,
            'post_password' => $password,
        ]);

        wp_set_object_terms($id, 'Notes', 'category');
        wp_set_object_terms($id, $tags, 'post_tag');
    }

    /**
     * The press kit PDF, imported once and found again by its `demo_file` meta, which
     * database/restore-media.php also reads to put the file back.
     */
    private function pressKit(): int
    {
        $file = 'press-kit.pdf';
        $ids = get_posts([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'meta_key' => 'demo_file',
            'meta_value' => $file,
            'posts_per_page' => 1,
            'fields' => 'ids',
        ]);

        if ($ids !== []) {
            return (int) $ids[0];
        }

        $temporary = wp_tempnam($file);
        copy(Photographs::directory() . '/' . $file, $temporary);

        $id = media_handle_sideload(['name' => $file, 'tmp_name' => $temporary], 0, 'Editions press kit');

        if (is_wp_error($id)) {
            throw new RuntimeException("could not import {$file}: " . $id->get_error_message());
        }

        update_post_meta($id, 'demo_file', $file);

        return $id;
    }

    /**
     * @param list<string> $slugs
     * @return list<int>
     */
    private static function projectIds(array $slugs): array
    {
        $ids = [];

        foreach ($slugs as $slug) {
            $project = get_page_by_path($slug, OBJECT, 'project');

            if ($project === null) {
                throw new RuntimeException("the {$slug} project does not exist. Run ProjectSeeder first.");
            }

            $ids[] = $project->ID;
        }

        return $ids;
    }

    /**
     * A core image block, as the editor saves one: the `wp-image-<id>` class is what
     * lets WordPress, and ContentImageHooks, find the attachment behind the tag.
     */
    private static function image(?int $id): string
    {
        if ($id === null) {
            return '';
        }

        $src = (string) wp_get_attachment_url($id);
        $alt = esc_attr((string) get_post_meta($id, '_wp_attachment_image_alt', true));

        return BlockMarkup::block(
            'image',
            ['id' => $id, 'sizeSlug' => 'large'],
            "<figure class=\"wp-block-image size-large\"><img src=\"{$src}\" alt=\"{$alt}\" class=\"wp-image-{$id}\"/></figure>",
        );
    }
}
