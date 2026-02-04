<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Blocks;

use Timber\Integration\AcfIntegration;

/**
 * Transforms ACF field values to Timber objects.
 */
final class AcfFieldTransformer
{
    /**
     * Field types that should be transformed via Timber's ACF integration.
     */
    private const TRANSFORMABLE_TYPES = [
        'image',
        'gallery',
        'file',
        'post_object',
        'relationship',
        'taxonomy',
        'user',
        'date_picker',
        'date_time_picker',
    ];

    /**
     * Field types that contain nested fields.
     */
    private const NESTED_TYPES = [
        'repeater',
        'flexible_content',
        'group',
    ];

    /**
     * Transform all fields for a block.
     *
     * @param array<string, mixed> $fields Field values
     * @param string $blockId The block ID
     * @return array<string, mixed> Transformed field values
     */
    public function transformFields(array $fields, string $blockId): array
    {
        if (!function_exists('get_field_object')) {
            return $fields;
        }

        foreach ($fields as $key => $value) {
            $fields[$key] = $this->transformField($value, $key, $blockId);
        }

        return $fields;
    }

    /**
     * Transform a single field value based on its type.
     *
     * @param mixed $value The field value
     * @param string $fieldName The field name
     * @param string $blockId The block ID for context
     * @return mixed The transformed value
     */
    private function transformField(mixed $value, string $fieldName, string $blockId): mixed
    {
        // Skip empty values
        if ($this->isEmpty($value)) {
            return $value;
        }

        // Get the field object to determine its type
        $fieldObject = get_field_object($fieldName, $blockId);

        if ($fieldObject === false || !isset($fieldObject['type'])) {
            return $value;
        }

        $type = $fieldObject['type'];

        // Handle nested field types recursively
        if (in_array($type, self::NESTED_TYPES, true)) {
            return $this->transformNestedField($value, $type, $fieldObject, $blockId);
        }

        // Transform simple field types
        if (in_array($type, self::TRANSFORMABLE_TYPES, true)) {
            return $this->applyTransformation($value, $type, $fieldObject);
        }

        return $value;
    }

    /**
     * Transform nested field values (repeater, flexible_content, group).
     *
     * @param mixed $value The nested field value
     * @param string $type The field type
     * @param array<string, mixed> $fieldObject The field object
     * @param string $blockId The block ID for context
     * @return mixed The transformed value
     */
    private function transformNestedField(mixed $value, string $type, array $fieldObject, string $blockId): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $subFields = $fieldObject['sub_fields'] ?? [];

        if ($subFields === []) {
            return $value;
        }

        // Build a map of sub-field names to their field objects
        $subFieldMap = $this->buildSubFieldMap($subFields);

        return match ($type) {
            'group' => $this->transformGroupValue($value, $subFieldMap, $blockId),
            'repeater' => $this->transformRepeaterValue($value, $subFieldMap, $blockId),
            'flexible_content' => $this->transformFlexibleContentValue($value, $fieldObject, $blockId),
            default => $value,
        };
    }

    /**
     * Build a map of sub-field names to their field objects.
     *
     * @param array<int, array<string, mixed>> $subFields
     * @return array<string, array<string, mixed>>
     */
    private function buildSubFieldMap(array $subFields): array
    {
        $subFieldMap = [];
        foreach ($subFields as $subField) {
            $name = $subField['name'] ?? null;

            if (!is_string($name)) {
                continue;
            }

            $subFieldMap[$name] = $subField;
        }

        return $subFieldMap;
    }

    /**
     * Transform group field value.
     *
     * @param array<string, mixed> $value The group value
     * @param array<string, array<string, mixed>> $subFieldMap Sub-field definitions
     * @param string $blockId The block ID
     * @return array<string, mixed>
     */
    private function transformGroupValue(array $value, array $subFieldMap, string $blockId): array
    {
        foreach ($value as $key => $subValue) {
            $value[$key] = $this->transformSubField($subValue, $key, $subFieldMap, $blockId);
        }

        return $value;
    }

    /**
     * Transform a sub-field value using the sub-field map.
     *
     * @param mixed $subValue The sub-field value
     * @param string|int $key The sub-field key
     * @param array<string, array<string, mixed>> $subFieldMap Sub-field definitions
     * @param string $blockId The block ID
     * @return mixed The transformed value
     */
    private function transformSubField(mixed $subValue, string|int $key, array $subFieldMap, string $blockId): mixed
    {
        if (!is_string($key) || !isset($subFieldMap[$key])) {
            return $subValue;
        }

        $subField = $subFieldMap[$key];
        $subType = $subField['type'] ?? null;

        if ($subType === null) {
            return $subValue;
        }

        if (in_array($subType, self::NESTED_TYPES, true)) {
            return $this->transformNestedField($subValue, $subType, $subField, $blockId);
        }

        if (in_array($subType, self::TRANSFORMABLE_TYPES, true)) {
            return $this->applyTransformation($subValue, $subType, $subField);
        }

        return $subValue;
    }

    /**
     * Transform repeater field value.
     *
     * @param array<int, mixed> $value The repeater rows
     * @param array<string, array<string, mixed>> $subFieldMap Sub-field definitions
     * @param string $blockId The block ID
     * @return array<int, mixed>
     */
    private function transformRepeaterValue(array $value, array $subFieldMap, string $blockId): array
    {
        foreach ($value as $rowIndex => $row) {
            if (!is_array($row)) {
                continue;
            }

            $value[$rowIndex] = $this->transformGroupValue($row, $subFieldMap, $blockId);
        }

        return $value;
    }

    /**
     * Transform flexible content field value.
     *
     * @param array<int, mixed> $value The flexible content layouts
     * @param array<string, mixed> $fieldObject The field object
     * @param string $blockId The block ID
     * @return array<int, mixed>
     */
    private function transformFlexibleContentValue(array $value, array $fieldObject, string $blockId): array
    {
        $layouts = $fieldObject['layouts'] ?? [];
        $layoutMap = $this->buildLayoutMap($layouts);

        foreach ($value as $rowIndex => $row) {
            if (!is_array($row)) {
                continue;
            }

            $value[$rowIndex] = $this->transformFlexibleContentRow($row, $layoutMap, $blockId);
        }

        return $value;
    }

    /**
     * Build a map of layout names to their sub-field maps.
     *
     * @param array<int, array<string, mixed>> $layouts
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function buildLayoutMap(array $layouts): array
    {
        $layoutMap = [];
        foreach ($layouts as $layout) {
            $name = $layout['name'] ?? null;
            $subFields = $layout['sub_fields'] ?? null;

            if (!is_string($name) || !is_array($subFields)) {
                continue;
            }

            $layoutMap[$name] = $this->buildSubFieldMap($subFields);
        }

        return $layoutMap;
    }

    /**
     * Transform a single flexible content row.
     *
     * @param array<string, mixed> $row The row data
     * @param array<string, array<string, array<string, mixed>>> $layoutMap Layout definitions
     * @param string $blockId The block ID
     * @return array<string, mixed>
     */
    private function transformFlexibleContentRow(array $row, array $layoutMap, string $blockId): array
    {
        $layoutName = $row['acf_fc_layout'] ?? null;

        if (!is_string($layoutName) || !isset($layoutMap[$layoutName])) {
            return $row;
        }

        $subFieldMap = $layoutMap[$layoutName];

        foreach ($row as $key => $subValue) {
            // Skip the layout identifier
            if ($key === 'acf_fc_layout') {
                continue;
            }

            $row[$key] = $this->transformSubField($subValue, $key, $subFieldMap, $blockId);
        }

        return $row;
    }

    /**
     * Apply the appropriate Timber transformation for a field type.
     *
     * @param mixed $value The field value
     * @param string $type The ACF field type
     * @param array<string, mixed> $fieldObject The field object
     * @return mixed The transformed value
     */
    private function applyTransformation(mixed $value, string $type, array $fieldObject): mixed
    {
        // Skip empty values
        if ($this->isEmpty($value)) {
            return $value;
        }

        // The $id parameter is not used by Timber's transform methods, but is required.
        // We pass 0 as a dummy value since the value is already extracted.
        return match ($type) {
            'image' => AcfIntegration::transform_image($value, 0, $fieldObject),
            'gallery' => AcfIntegration::transform_gallery($value, 0, $fieldObject),
            'file' => AcfIntegration::transform_file($value, 0, $fieldObject),
            'post_object' => AcfIntegration::transform_post_object($value, 0, $fieldObject),
            'relationship' => AcfIntegration::transform_relationship($value, 0, $fieldObject),
            'taxonomy' => AcfIntegration::transform_taxonomy($value, 0, $fieldObject),
            'user' => AcfIntegration::transform_user($value, 0, $fieldObject),
            'date_picker', 'date_time_picker' => AcfIntegration::transform_date_picker($value, 0, $fieldObject),
            default => $value,
        };
    }

    /**
     * Check if a value is considered empty.
     */
    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }
}
