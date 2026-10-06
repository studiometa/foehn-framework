<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Blocks;

use acf_field;
use Timber\Integration\AcfIntegration;

/**
 * Reads the field values of the ACF block being rendered.
 *
 * ACF stores a block's values flat and unformatted in the block comment: a
 * repeater is `items: 2` plus `items_0_title`, a group is `group_label`, an image
 * is an attachment ID. Before it calls the render callback, ACF loads them as the
 * block's meta, so `get_fields($blockId)` returns them formatted and nested, like
 * the values of a post.
 *
 * With transformation on, Timber's ACF transforms stand in for ACF's own
 * formatting of the field types they cover, the way Timber does for
 * `$post->meta($name, ['transform_value' => true])`. ACF still walks groups,
 * repeaters, flexible content and clones, so a sub-field is transformed at any
 * depth, and every other type keeps ACF's formatting.
 */
final readonly class AcfBlockFields
{
    /**
     * Timber transform for each ACF field type it covers.
     *
     * @var array<string, callable-string|array{class-string, string}>
     */
    private const TRANSFORMS = [
        'file' => [AcfIntegration::class, 'transform_file'],
        'image' => [AcfIntegration::class, 'transform_image'],
        'gallery' => [AcfIntegration::class, 'transform_gallery'],
        'date_picker' => [AcfIntegration::class, 'transform_date_picker'],
        'date_time_picker' => [AcfIntegration::class, 'transform_date_picker'],
        'post_object' => [AcfIntegration::class, 'transform_post_object'],
        'relationship' => [AcfIntegration::class, 'transform_relationship'],
        'taxonomy' => [AcfIntegration::class, 'transform_taxonomy'],
        'user' => [AcfIntegration::class, 'transform_user'],
    ];

    public function __construct(
        private bool $transform = true,
    ) {}

    /**
     * Get the field values of a block.
     *
     * @param string $blockId The block ID ACF set up the block's meta under
     * @return array<string, mixed> Field values, keyed by field name
     */
    public function get(string $blockId): array
    {
        if (!function_exists('get_fields')) {
            return [];
        }

        if (!$this->transform) {
            return get_fields($blockId) ?: [];
        }

        $this->flushFormattedValues($blockId);
        $swapped = $this->swapFormatting();

        try {
            return get_fields($blockId) ?: [];
        } finally {
            $this->restoreFormatting($swapped);
            $this->flushFormattedValues($blockId);
        }
    }

    /**
     * Replace ACF's formatting with Timber's transforms.
     *
     * @return array<string, acf_field> The ACF field types whose formatting was replaced
     */
    private function swapFormatting(): array
    {
        if (!function_exists('acf_get_field_type')) {
            return [];
        }

        $swapped = [];

        foreach (self::TRANSFORMS as $type => $transform) {
            $fieldType = acf_get_field_type($type);

            if (!$fieldType instanceof acf_field) {
                continue;
            }

            remove_filter("acf/format_value/type={$type}", [$fieldType, 'format_value']);
            add_filter("acf/format_value/type={$type}", $transform, 10, 3);
            $swapped[$type] = $fieldType;
        }

        return $swapped;
    }

    /**
     * Put ACF's formatting back.
     *
     * @param array<string, acf_field> $swapped
     */
    private function restoreFormatting(array $swapped): void
    {
        foreach ($swapped as $type => $fieldType) {
            remove_filter("acf/format_value/type={$type}", self::TRANSFORMS[$type]);
            add_filter("acf/format_value/type={$type}", [$fieldType, 'format_value'], 10, 3);
        }
    }

    /**
     * Forget the formatted values ACF cached for the block.
     *
     * ACF caches each formatted value by post ID and field name. A value cached
     * before the swap would skip the transforms, and a transformed value left
     * after it would make a `get_field()` or `get_sub_field()` in the block's own
     * code return a Timber object instead of what ACF formats.
     */
    private function flushFormattedValues(string $blockId): void
    {
        if (!function_exists('acf_get_store')) {
            return;
        }

        $store = acf_get_store('values');
        $values = $store->get();
        $prefix = $blockId . ':';

        if (!is_array($values)) {
            return;
        }

        foreach (array_keys($values) as $key) {
            if (!(is_string($key) && str_starts_with($key, $prefix) && str_ends_with($key, ':formatted'))) {
                continue;
            }

            $store->remove($key);
        }
    }
}
