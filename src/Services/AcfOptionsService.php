<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Services;

/**
 * Helper service for retrieving ACF options page values.
 *
 * Provides a convenient API for getting field values from ACF options pages.
 */
final class AcfOptionsService
{
    /**
     * Get a field value from an options page.
     *
     * @param string $selector The field name or key
     * @param string $postId The options page post_id (menu_slug)
     * @param bool $formatValue Whether to format the value
     * @return mixed The field value
     */
    public function get(string $selector, string $postId = 'options', bool $formatValue = true): mixed
    {
        if (!function_exists('get_field')) {
            return null;
        }

        return get_field($selector, $postId, $formatValue);
    }

    /**
     * Get all field values from an options page.
     *
     * @param string $postId The options page post_id (menu_slug)
     * @param bool $formatValue Whether to format the values
     * @return array<string, mixed> All field values
     */
    public function all(string $postId = 'options', bool $formatValue = true): array
    {
        if (!function_exists('get_fields')) {
            return [];
        }

        return get_fields($postId, $formatValue) ?: [];
    }

    /**
     * Check if an options page has a specific field value.
     *
     * @param string $selector The field name or key
     * @param string $postId The options page post_id (menu_slug)
     * @return bool Whether the field has a value
     */
    public function has(string $selector, string $postId = 'options'): bool
    {
        $value = $this->get($selector, $postId);

        return $value !== null && $value !== '' && $value !== false;
    }

    /**
     * Get a field object (includes field settings) from an options page.
     *
     * @param string $selector The field name or key
     * @param string $postId The options page post_id (menu_slug)
     * @param bool $formatValue Whether to format the value
     * @return array<string, mixed>|null The field object or null
     */
    public function getObject(string $selector, string $postId = 'options', bool $formatValue = true): ?array
    {
        if (!function_exists('get_field_object')) {
            return null;
        }

        $object = get_field_object($selector, $postId, $formatValue);

        return is_array($object) ? $object : null;
    }
}
