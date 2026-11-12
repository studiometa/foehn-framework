<?php

declare(strict_types=1);

namespace Demo\Seeders;

/**
 * The thirty photographs in `database/media/`, and the attachments made from them.
 *
 * `credits.json` is written by `database/fetch-media.py` against the Unsplash API.
 * It gives each file its series (`project`, or `_studio` for the About page) and the
 * credit the licence asks for. An attachment is found by its `unsplash_id` meta,
 * which MediaSeeder writes, so the other seeders find the photographs it imported.
 */
final class Photographs
{
    /**
     * The directory that holds the originals and `credits.json`.
     */
    public static function directory(): string
    {
        return dirname(__DIR__, 3) . '/database/media';
    }

    /**
     * Every entry of `credits.json`, in file order.
     *
     * @return list<array<string, mixed>>
     */
    public static function credits(): array
    {
        /** @var list<array<string, mixed>> */
        return json_decode(
            (string) file_get_contents(self::directory() . '/credits.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    /**
     * The attachment imported from one photograph, or null before MediaSeeder ran.
     */
    public static function find(string $unsplashId): ?int
    {
        $ids = get_posts([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'meta_key' => 'unsplash_id',
            'meta_value' => $unsplashId,
            'posts_per_page' => 1,
            'fields' => 'ids',
        ]);

        return $ids === [] ? null : (int) $ids[0];
    }

    /**
     * The attachments of one series, in the order of `credits.json`.
     *
     * @return list<int>
     */
    public static function ofProject(string $project): array
    {
        $ids = [];

        foreach (self::credits() as $entry) {
            if ($entry['project'] !== $project) {
                continue;
            }

            $id = self::find((string) $entry['unsplash_id']);

            if ($id !== null) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
