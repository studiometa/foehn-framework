<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Blocks;

use Studiometa\Foehn\Helpers\Env;

/**
 * Splits a block attribute schema into its WordPress registration part
 * and its editor UI part.
 *
 * A Foehn block declares its attributes with the standard WordPress keys
 * (type, default, enum, ...) plus a few UI-only keys (control, label, help,
 * options) which describe how the editor sidebar renders the attribute.
 * WordPress must never see the UI-only keys, and the editor must never
 * guess the UI from the raw schema. This class is the single translation point.
 */
final class BlockAttributeSchema
{
    /**
     * UI-only keys, stripped before the schema reaches WordPress.
     */
    private const array UI_KEYS = ['control', 'label', 'help', 'options'];

    /**
     * Controls the editor knows how to render.
     */
    private const array SUPPORTED_CONTROLS = ['text', 'textarea', 'toggle', 'number', 'select', 'image'];

    /**
     * Get the schema as WordPress expects it, without the UI-only keys.
     *
     * @param array<string, array<string, mixed>> $attributes
     * @return array<string, array<string, mixed>>
     */
    public static function toRegistration(array $attributes): array
    {
        $registration = [];

        foreach ($attributes as $name => $schema) {
            $registration[$name] = array_diff_key($schema, array_flip(self::UI_KEYS));
        }

        return $registration;
    }

    /**
     * Get the editor field descriptors, one per attribute, in schema order.
     *
     * The schema `type` travels with the descriptor because the control alone does not
     * carry it: `number` and `integer` share the `number` control, and the editor has
     * to round an integer attribute rather than store a float WordPress would reject.
     *
     * @param array<string, array<string, mixed>> $attributes
     * @return array<string, array{control: string|null, type: string|null, label: string, help: string|null, options: list<array{label: string, value: string|int|float|bool}>|null}>
     */
    public static function toEditorFields(array $attributes): array
    {
        $fields = [];

        foreach ($attributes as $name => $schema) {
            $control = self::resolveControl($schema, $name);

            $fields[$name] = [
                'control' => $control,
                'type' => is_string($schema['type'] ?? null) ? $schema['type'] : null,
                'label' => is_string($schema['label'] ?? null) ? $schema['label'] : self::humanize($name),
                'help' => is_string($schema['help'] ?? null) ? $schema['help'] : null,
                'options' => $control === 'select' ? self::resolveOptions($schema) : null,
            ];
        }

        return $fields;
    }

    /**
     * Resolve the control to use for a single attribute.
     *
     * An explicit control wins, but only when the editor supports it. An unsupported
     * one falls back to the type derived control and says so, because silently
     * dropping an explicit instruction leaves nothing to grep for.
     *
     * @param array<string, mixed> $schema
     */
    private static function resolveControl(array $schema, string $name): ?string
    {
        /** @var mixed $control */
        $control = $schema['control'] ?? null;

        if ($control !== null) {
            if (is_string($control) && in_array($control, self::SUPPORTED_CONTROLS, true)) {
                return $control;
            }

            self::warnUnsupportedControl($name, $control);
        }

        $hasChoices = ($schema['options'] ?? null) !== null || ($schema['enum'] ?? null) !== null;

        return match ($schema['type'] ?? null) {
            'string' => $hasChoices ? 'select' : 'text',
            'boolean' => 'toggle',
            'number', 'integer' => 'number',
            default => null,
        };
    }

    /**
     * Report an explicit control the editor cannot render.
     *
     * Debug gated like every other Foehn developer warning: it is an authoring
     * mistake, not a runtime condition an editor can act on.
     */
    private static function warnUnsupportedControl(string $name, mixed $control): void
    {
        if (!Env::isDebug()) {
            return;
        }

        trigger_error(
            sprintf(
                '[Foehn] Unsupported control "%s" on block attribute "%s". Supported controls: %s. '
                . 'Falling back to the control derived from the attribute type.',
                is_string($control) ? $control : get_debug_type($control),
                $name,
                implode(', ', self::SUPPORTED_CONTROLS),
            ),
            E_USER_WARNING,
        );
    }

    /**
     * Normalize the choices of a select attribute to a list of label/value pairs.
     *
     * Three shapes are accepted:
     * - a plain list of raw values: ['small', 'large']
     * - an associative value => label map: ['sm' => 'Small', 'lg' => 'Large']
     * - an already normalized list of ['label' => ..., 'value' => ...] arrays
     *
     * @param array<string, mixed> $schema
     * @return list<array{label: string, value: string|int|float|bool}>|null
     */
    private static function resolveOptions(array $schema): ?array
    {
        $type = is_string($schema['type'] ?? null) ? $schema['type'] : null;

        if (is_array($schema['options'] ?? null)) {
            $options = [];

            // A list has no meaningful keys, so its values are the choices themselves.
            // Reading the key instead would store array indices in the attribute.
            $isList = array_is_list($schema['options']);

            /** @var mixed $option */
            foreach ($schema['options'] as $key => $option) {
                if (is_array($option) && ($option['value'] ?? null) !== null) {
                    $options[] = [
                        'label' => self::toLabel($option['label'] ?? $option['value']),
                        'value' => self::toValue($option['value'], $type),
                    ];

                    continue;
                }

                $options[] = [
                    'label' => self::toLabel($option),
                    'value' => self::toValue($isList ? $option : $key, $type),
                ];
            }

            return $options;
        }

        if (is_array($schema['enum'] ?? null)) {
            $options = [];

            /** @var mixed $value */
            foreach ($schema['enum'] as $value) {
                $options[] = [
                    'label' => self::toLabel($value),
                    'value' => self::toValue($value, $type),
                ];
            }

            return $options;
        }

        return null;
    }

    /**
     * Coerce a raw label to a string.
     */
    private static function toLabel(mixed $label): string
    {
        return is_scalar($label) ? (string) $label : '';
    }

    /**
     * Coerce a raw choice value to a JSON friendly scalar of the declared type.
     *
     * The type matters: WordPress validates a select value against the attribute
     * schema both in the block renderer REST endpoint and in
     * WP_Block_Type::prepare_attributes_for_render(). A value of the wrong type is
     * dropped and replaced by the default, so the editor and the front end would
     * silently disagree.
     */
    private static function toValue(mixed $value, ?string $type): string|int|float|bool
    {
        $scalar = is_scalar($value) ? $value : self::toLabel($value);

        return match ($type) {
            'string' => is_string($scalar) ? $scalar : self::toLabel($scalar),
            'integer' => is_numeric($scalar) ? (int) $scalar : $scalar,
            'number' => is_numeric($scalar) ? (float) $scalar : $scalar,
            'boolean' => (bool) $scalar,
            default => $scalar,
        };
    }

    /**
     * Turn an attribute key into a human readable label.
     *
     * Handles both snake_case and camelCase: 'ctaLabel' => 'Cta label',
     * 'image_id' => 'Image id'.
     */
    private static function humanize(string $name): string
    {
        $words = preg_replace('/(?<!^)[A-Z]/', ' $0', $name) ?? $name;
        $words = str_replace(['_', '-'], ' ', $words);
        $words = trim(preg_replace('/\s+/', ' ', $words) ?? $words);

        return ucfirst(strtolower($words));
    }
}
