<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Settings;

use Closure;
use InvalidArgumentException;

/**
 * One setting a page stores, and the field that edits it.
 *
 * The type says what is stored. The control says which native WordPress field
 * edits it: Føhn draws that field on a settings page with `add_settings_field()`,
 * and in the Customizer with `add_control()`. So a page has no form of its own to
 * write, and every page looks like the WordPress screens around it.
 *
 * The controls are the ones WordPress draws with no script: text, textarea,
 * email, url, tel, number, checkbox and select. Repeaters, conditional fields and
 * media pickers are ACF's product, not this one.
 */
final readonly class Setting
{
    /**
     * The controls a string setting can use.
     */
    public const array STRING_CONTROLS = ['text', 'textarea', 'email', 'url', 'tel'];

    /**
     * The sanitiser each control falls back to.
     *
     * `register_setting()` runs the callback on every save, and a setting with
     * none stores whatever was posted.
     */
    private const array DEFAULT_SANITIZERS = [
        'text' => 'sanitize_text_field',
        'tel' => 'sanitize_text_field',
        'textarea' => 'sanitize_textarea_field',
        'email' => 'sanitize_email',
        'url' => 'esc_url_raw',
        'checkbox' => 'rest_sanitize_boolean',
    ];

    /**
     * @param string $type One of `string`, `boolean`, `integer`, `number`
     * @param scalar|array<array-key, mixed>|null $default What `Settings::get()` answers
     *   before the setting has ever been saved
     * @param string $label The field label. The option name when it is empty
     * @param string $description The help text under the field, and the
     *   description in the REST schema when $showInRest is on
     * @param string $control One of `text`, `textarea`, `email`, `url`, `tel`,
     *   `number`, `checkbox`, `select`
     * @param array<string|int, string> $options The values a `select` offers, as
     *   value => label
     * @param string|null $sanitize A function name, or the name of a public static
     *   method on the page class. Never a closure: a discovery item reaches the
     *   cache through var_export()
     * @param bool $showInRest Off by default, unlike post meta. Settings are
     *   configuration and sometimes credentials, so exposure is opt-in
     * @param bool $translatable One value for each language of a multilingual site,
     *   instead of one value for all. String settings only
     */
    private function __construct(
        public string $type,
        public string|int|float|bool|array|null $default,
        public string $label,
        public string $description,
        public string $control,
        public array $options,
        public ?string $sanitize,
        public bool $showInRest,
        public bool $translatable = false,
    ) {}

    /**
     * A string, edited in a text field, or in a select when $options is given.
     *
     * @param string $control One of STRING_CONTROLS. Ignored when $options is given
     * @param array<string, string> $options The values a select offers, as value => label
     * @param bool $translatable One value for each language, on a site with Polylang.
     *   An empty translation reads the value of the default language
     */
    public static function string(
        string $default = '',
        string $label = '',
        string $description = '',
        string $control = 'text',
        array $options = [],
        ?string $sanitize = null,
        bool $showInRest = false,
        bool $translatable = false,
    ): self {
        if (!in_array($control, self::STRING_CONTROLS, true)) {
            throw new InvalidArgumentException(sprintf(
                'Unknown control "%s" for a string setting. Use one of: %s.',
                $control,
                implode(', ', self::STRING_CONTROLS),
            ));
        }

        return new self(
            'string',
            $default,
            $label,
            $description,
            $options === [] ? $control : 'select',
            $options,
            $sanitize,
            $showInRest,
            $translatable,
        );
    }

    /**
     * A checkbox.
     */
    public static function bool(
        bool $default = false,
        string $label = '',
        string $description = '',
        ?string $sanitize = null,
        bool $showInRest = false,
    ): self {
        return new self('boolean', $default, $label, $description, 'checkbox', [], $sanitize, $showInRest);
    }

    /**
     * A whole number, edited in a number field, or in a select when $options is given.
     *
     * @param array<int, string> $options The values a select offers, as value => label
     */
    public static function int(
        int $default = 0,
        string $label = '',
        string $description = '',
        array $options = [],
        ?string $sanitize = null,
        bool $showInRest = false,
    ): self {
        return new self(
            'integer',
            $default,
            $label,
            $description,
            $options === [] ? 'number' : 'select',
            $options,
            $sanitize,
            $showInRest,
        );
    }

    /**
     * A number with a fractional part.
     */
    public static function number(
        float $default = 0.0,
        string $label = '',
        string $description = '',
        ?string $sanitize = null,
        bool $showInRest = false,
    ): self {
        return new self('number', $default, $label, $description, 'number', [], $sanitize, $showInRest);
    }

    /**
     * The label of the field: the declared one, or the option name.
     */
    public function label(string $name): string
    {
        return $this->label !== '' ? $this->label : $name;
    }

    /**
     * The sanitiser this setting is saved through.
     *
     * The declared one when there is one, and otherwise whatever the control
     * implies. A page class may name one of its own static methods, which
     * `SettingsPageDiscovery` resolves against the class. A select keeps only
     * one of its options, and falls back to the default for anything else.
     *
     * @param class-string|null $pageClass
     * @return string|array{class-string, string}|Closure(mixed): mixed A callable,
     *   in a form register_setting() accepts
     */
    public function sanitizer(?string $pageClass = null): string|array|Closure
    {
        if ($this->sanitize !== null) {
            if ($pageClass !== null && method_exists($pageClass, $this->sanitize)) {
                return [$pageClass, $this->sanitize];
            }

            return $this->sanitize;
        }

        if ($this->control === 'select') {
            return $this->keepOption(...);
        }

        return match ($this->type) {
            'integer' => 'absint',
            'number' => 'floatval',
            default => self::DEFAULT_SANITIZERS[$this->control] ?? 'sanitize_text_field',
        };
    }

    /**
     * The sanitiser as text, for `wp foehn inspect`.
     *
     * @param class-string|null $pageClass
     */
    public function sanitizerName(?string $pageClass = null): string
    {
        $sanitizer = $this->sanitizer($pageClass);

        return match (true) {
            $sanitizer instanceof Closure => 'one of the options',
            is_array($sanitizer) => implode('::', $sanitizer),
            default => $sanitizer,
        };
    }

    /**
     * A posted value when it is one of the options, the default otherwise.
     */
    private function keepOption(mixed $value): mixed
    {
        foreach (array_keys($this->options) as $option) {
            if (is_scalar($value) && (string) $option === (string) $value) {
                return $option;
            }
        }

        return $this->default;
    }
}
