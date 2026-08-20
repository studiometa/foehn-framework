<?php

declare(strict_types=1);

/**
 * Put the photographs back where the database says they are.
 *
 *     wp eval-file database/restore-media.php
 *
 * The SQL dump carries the attachment rows; it cannot carry the files, because the
 * demo offloads uploads to object storage and the bucket is not in the repository.
 * So the originals travel as plain JPEGs in `database/media/`, and this copies each
 * one to the path its attachment already claims.
 *
 * `get_attached_file()` returns an `s3://` path while humanmade/s3-uploads is
 * active, so the copy goes straight into the bucket through the plugin's own stream
 * wrapper — no S3 client here, and the same script works on a site with the plugin
 * switched off, where the path is a local one.
 */

$mediaDir = dirname(__DIR__) . '/database/media';
$credits = json_decode((string) file_get_contents($mediaDir . '/credits.json'), true, 512, JSON_THROW_ON_ERROR);

/** @var array<string, array<string, mixed>> $byId */
$byId = [];

foreach ($credits as $entry) {
    $byId[$entry['unsplash_id']] = $entry;
}

require_once ABSPATH . 'wp-admin/includes/image.php';

$attachments = get_posts([
    'post_type' => 'attachment',
    'post_status' => 'inherit',
    'posts_per_page' => -1,
    'fields' => 'ids',
]);

$copied = 0;
$regenerated = 0;
$skipped = 0;

foreach ($attachments as $id) {
    $unsplashId = get_post_meta($id, 'unsplash_id', true);
    $entry = is_string($unsplashId) ? $byId[$unsplashId] ?? null : null;

    if ($entry === null) {
        $skipped++;

        continue;
    }

    $target = get_attached_file($id);
    $source = $mediaDir . '/' . $entry['file'];

    if (!is_string($target) || !file_exists($source)) {
        $skipped++;

        continue;
    }

    if (!file_exists($target)) {
        // The uploads directory may not exist yet on a fresh restore.
        wp_mkdir_p(dirname($target));

        if (!copy($source, $target)) {
            WP_CLI::warning("could not write {$target}");

            continue;
        }

        $copied++;
    }

    // Sub-sizes are derived, so they are never shipped — they are rebuilt from the
    // original, which is also what proves the original really arrived.
    $metadata = wp_generate_attachment_metadata($id, $target);

    if (is_array($metadata) && $metadata !== []) {
        wp_update_attachment_metadata($id, $metadata);
        $regenerated++;
    }
}

WP_CLI::success(sprintf(
    '%d originals copied, %d attachments regenerated, %d skipped',
    $copied,
    $regenerated,
    $skipped,
));
