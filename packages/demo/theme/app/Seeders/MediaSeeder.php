<?php

declare(strict_types=1);

namespace Demo\Seeders;

use RuntimeException;
use Studiometa\Foehn\Attributes\AsSeeder;
use Studiometa\Foehn\Contracts\SeederInterface;

/**
 * Import the photographs of `database/media/` and stamp each one with its credit.
 *
 * A photograph already imported is found by its Unsplash id and only gets its meta
 * written again. Every attachment carries the photographer's name and profile URL as
 * post meta, because the licence asks for attribution and a template can only print
 * what the database holds.
 */
#[AsSeeder(order: 10, description: 'The thirty photographs, with their credits')]
final class MediaSeeder implements SeederInterface
{
    public function run(): void
    {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        foreach (Photographs::credits() as $entry) {
            $this->import($entry);
        }
    }

    /**
     * Import one photograph, or find the one already imported, and stamp its credit.
     *
     * @param array<string, mixed> $entry
     */
    private function import(array $entry): void
    {
        $file = (string) $entry['file'];
        $description = (string) $entry['description'];
        $id = Photographs::find((string) $entry['unsplash_id']);

        if ($id === null) {
            // media_handle_sideload moves the file it is given, so it gets a copy.
            $temporary = wp_tempnam($file);
            copy(Photographs::directory() . '/' . $file, $temporary);

            $id = media_handle_sideload(['name' => $file, 'tmp_name' => $temporary], 0, $description ?: null);

            if (is_wp_error($id)) {
                if (is_file($temporary)) {
                    unlink($temporary);
                }

                throw new RuntimeException("could not import {$file}: " . $id->get_error_message());
            }
        }

        update_post_meta($id, 'unsplash_id', $entry['unsplash_id']);
        update_post_meta($id, 'credit_photographer', $entry['photographer']);
        update_post_meta($id, 'credit_url', $entry['photographer_url']);
        update_post_meta($id, 'credit_source', $entry['page']);
        update_post_meta($id, '_wp_attachment_image_alt', $description ?: $entry['photographer']);
    }
}
