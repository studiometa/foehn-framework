<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Settings;

/**
 * The field of one setting on a settings page.
 *
 * The markup WordPress uses on its own settings screens: `regular-text` and
 * `large-text` inputs, a `small-text` number, a checkbox in a `fieldset` and
 * the help text in a `p.description`. `add_settings_field()` prints the row and
 * the label around it.
 */
final class SettingField
{
    /** The help text of a field whose value the code configuration sets. */
    public const string READONLY_NOTE = 'The configuration sets this value, so it cannot be changed here.';

    /**
     * The field, filled with the current value.
     *
     * A read-only field shows a value the code sets, with a note that says so. A text
     * field is `readonly`, so it still posts the value it shows. A checkbox and a select
     * have no read-only state: they are `disabled`.
     */
    public static function render(string $name, Setting $setting, mixed $value, bool $readonly = false): string
    {
        $description = $readonly ? trim($setting->description . ' ' . self::READONLY_NOTE) : $setting->description;
        $describedBy = $description !== '' ? sprintf(' aria-describedby="%s-description"', esc_attr($name)) : '';
        $help = $description !== ''
            ? sprintf('<p class="description" id="%s-description">%s</p>', esc_attr($name), esc_html($description))
            : '';
        $describedBy .= match (true) {
            !$readonly => '',
            in_array($setting->control, ['checkbox', 'select'], true) => ' disabled="disabled"',
            default => ' readonly="readonly"',
        };

        if ($setting->control === 'checkbox') {
            return self::checkbox($name, $setting, (bool) $value, $readonly) . ($readonly ? $help : '');
        }

        $field = match ($setting->control) {
            'select' => self::select($name, $setting, $value, $describedBy),
            'textarea' => sprintf(
                '<textarea id="%1$s" name="%1$s" rows="5" class="large-text"%2$s>%3$s</textarea>',
                esc_attr($name),
                $describedBy,
                esc_html(self::text($value)),
            ),
            'number' => sprintf(
                '<input type="number" id="%1$s" name="%1$s" value="%2$s" step="%3$s" class="small-text"%4$s />',
                esc_attr($name),
                esc_attr(self::text($value)),
                $setting->type === 'integer' ? '1' : 'any',
                $describedBy,
            ),
            default => sprintf(
                '<input type="%1$s" id="%2$s" name="%2$s" value="%3$s" class="regular-text"%4$s />',
                esc_attr($setting->control),
                esc_attr($name),
                esc_attr(self::text($value)),
                $describedBy,
            ),
        };

        return $field . $help;
    }

    /**
     * A checkbox, as WordPress draws one: the box and its text in one label, and
     * the field label repeated for screen readers.
     */
    private static function checkbox(string $name, Setting $setting, bool $checked, bool $readonly = false): string
    {
        $label = $setting->label($name);

        return sprintf(
            '<fieldset><legend class="screen-reader-text"><span>%1$s</span></legend>'
            . '<label for="%2$s"><input type="checkbox" id="%2$s" name="%2$s" value="1"%3$s /> %4$s</label></fieldset>',
            esc_html($label),
            esc_attr($name),
            ($checked ? ' checked="checked"' : '') . ($readonly ? ' disabled="disabled"' : ''),
            esc_html($setting->description !== '' ? $setting->description : $label),
        );
    }

    private static function select(string $name, Setting $setting, mixed $value, string $describedBy): string
    {
        $options = '';

        foreach ($setting->options as $option => $label) {
            $options .= sprintf(
                '<option value="%s"%s>%s</option>',
                esc_attr((string) $option),
                (string) $option === self::text($value) ? ' selected="selected"' : '',
                esc_html($label),
            );
        }

        return sprintf('<select id="%1$s" name="%1$s"%2$s>%3$s</select>', esc_attr($name), $describedBy, $options);
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
