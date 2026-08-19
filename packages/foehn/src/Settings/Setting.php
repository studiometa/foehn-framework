<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Settings;

/**
 * One setting a page stores.
 *
 * What is stored, not how it looks. That is the whole difference from an ACF
 * options page, which declares both — and the reason a page migrated off ACF
 * writes its form once by hand.
 *
 * There is deliberately no field builder here. Text and checkboxes are a day;
 * repeaters, conditional fields and media pickers are ACF's actual product, and
 * a `Field::text(...)` is the first step towards maintaining a field library
 * nobody asked Foehn for.
 */
final readonly class Setting
{
    /**
     * The sanitiser each type falls back to.
     *
     * `register_setting()` runs the callback on every save, and a setting with
     * none stores whatever was posted.
     */
    private const array DEFAULT_SANITIZERS = [
        'string' => 'sanitize_text_field',
        'integer' => 'absint',
        'number' => 'floatval',
        'boolean' => 'rest_sanitize_boolean',
    ];

    /**
     * @param string $type One of `string`, `boolean`, `integer`, `number`
     * @param scalar|array<array-key, mixed>|null $default What `Settings::get()` answers
     *   before the setting has ever been saved
     * @param string|null $sanitize A function name, or the name of a public static
     *   method on the page class. Never a closure: a discovery item reaches the
     *   cache through var_export()
     * @param bool $showInRest Off by default, unlike post meta. Settings are
     *   configuration and sometimes credentials, so exposure is opt-in
     * @param string $description Only has an effect when $showInRest is on
     */
    private function __construct(
        public string $type,
        public string|int|float|bool|array|null $default = null,
        public ?string $sanitize = null,
        public bool $showInRest = false,
        public string $description = '',
    ) {}

    /**
     * A line of text.
     */
    public static function string(
        string $default = '',
        ?string $sanitize = null,
        bool $showInRest = false,
        string $description = '',
    ): self {
        return new self('string', $default, $sanitize, $showInRest, $description);
    }

    /**
     * A checkbox.
     */
    public static function bool(
        bool $default = false,
        ?string $sanitize = null,
        bool $showInRest = false,
        string $description = '',
    ): self {
        return new self('boolean', $default, $sanitize, $showInRest, $description);
    }

    /**
     * A whole number.
     */
    public static function int(
        int $default = 0,
        ?string $sanitize = null,
        bool $showInRest = false,
        string $description = '',
    ): self {
        return new self('integer', $default, $sanitize, $showInRest, $description);
    }

    /**
     * A number with a fractional part.
     */
    public static function number(
        float $default = 0.0,
        ?string $sanitize = null,
        bool $showInRest = false,
        string $description = '',
    ): self {
        return new self('number', $default, $sanitize, $showInRest, $description);
    }

    /**
     * The sanitiser this setting is saved through.
     *
     * The declared one when there is one, and otherwise whatever the type
     * implies. A page class may name one of its own static methods, which
     * `SettingsPageDiscovery` resolves against the class.
     *
     * @param class-string|null $pageClass
     * @return string|array{class-string, string} A callable, in either of the two
     *   forms register_setting() accepts
     */
    public function sanitizer(?string $pageClass = null): string|array
    {
        if ($this->sanitize === null) {
            return self::DEFAULT_SANITIZERS[$this->type] ?? 'sanitize_text_field';
        }

        if ($pageClass !== null && method_exists($pageClass, $this->sanitize)) {
            return [$pageClass, $this->sanitize];
        }

        return $this->sanitize;
    }
}
