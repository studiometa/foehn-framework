<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Settings;

/**
 * Registers and reads the settings a #[AsSettingsPage] or a
 * #[AsCustomizerSection] declared.
 *
 * `get_option('contact_email')` answers `false` for a setting that has never
 * been saved, whatever the page said its default was — the default reaches
 * WordPress through `register_setting()`, which only applies once the option
 * exists. Reading through here answers what was declared.
 *
 * Populated by SettingsPageDiscovery and CustomizerSectionDiscovery when they
 * apply.
 */
final class Settings
{
    /** @var array<string, Setting> */
    private static array $declared = [];

    /**
     * Register settings with WordPress, and record them.
     *
     * `register_setting()` gives each option its type, default and sanitiser,
     * which run on every save: from a settings page, the Customizer or
     * `wp option update`.
     *
     * @param string $group The option group: the page slug or the section id
     * @param array<string, Setting> $settings
     * @param class-string $className The class that declared them, whose static
     *   methods a setting may name as its sanitiser
     */
    public static function register(string $group, array $settings, string $className): void
    {
        foreach ($settings as $name => $setting) {
            register_setting($group, $name, [
                'type' => $setting->type,
                'default' => $setting->default,
                'description' => $setting->description,
                'show_in_rest' => $setting->showInRest,
                'sanitize_callback' => $setting->sanitizer($className),
            ]);
        }

        // So Settings::get() can answer with the declared default and type
        // rather than with whatever get_option() found.
        self::declare($settings);
    }

    /**
     * Record what a page declared.
     *
     * @param array<string, Setting> $settings
     */
    public static function declare(array $settings): void
    {
        self::$declared = [...self::$declared, ...$settings];
    }

    /**
     * The current value of a declared setting.
     *
     * The declared default is used when the option is absent, and the declared
     * type is applied on the way out: WordPress stores an unchecked checkbox as
     * the empty string, and `'0'` is not what a boolean setting means.
     */
    public static function get(string $name, mixed $default = null): mixed
    {
        $setting = self::$declared[$name] ?? null;

        if ($setting === null) {
            return get_option($name, $default);
        }

        $value = get_option($name, $default ?? $setting->default);

        return self::cast($value, $setting->type);
    }

    /**
     * Whether a setting was declared by a page.
     */
    public static function has(string $name): bool
    {
        return array_key_exists($name, self::$declared);
    }

    /**
     * Every declared setting.
     *
     * @return array<string, Setting>
     */
    public static function all(): array
    {
        return self::$declared;
    }

    /**
     * Forget every declaration.
     *
     * @internal For tests.
     */
    public static function clear(): void
    {
        self::$declared = [];
    }

    /**
     * A stored value as the type the page said it was.
     */
    private static function cast(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => (bool) $value,
            'integer' => is_numeric($value) ? (int) $value : $value,
            'number' => is_numeric($value) ? (float) $value : $value,
            'string' => is_scalar($value) ? (string) $value : $value,
            default => $value,
        };
    }
}
