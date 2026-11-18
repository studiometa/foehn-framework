<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Settings;

use function Studiometa\Foehn\multilingual;

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
 *
 * **Translatable settings.** On a multilingual site, a setting declared
 * `translatable` stores one value for each language, as ACF does for options
 * pages: `{name}` for the default language and `{name}_{lang}` for the others.
 * A settings page saves each language in its own option group, `{slug}_{lang}`,
 * because `options.php` empties every option of the group that the form does
 * not post.
 */
final class Settings
{
    /** @var array<string, Setting> */
    private static array $declared = [];

    /**
     * The option of each translation of a translatable setting => the setting.
     *
     * @var array<string, string>
     */
    private static array $translations = [];

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
        $multilingual = multilingual();
        $languages = $multilingual === null ? [] : array_keys($multilingual->languages());
        $languages = array_diff($languages, [$multilingual?->defaultLanguage()]);

        foreach ($settings as $name => $setting) {
            $args = [
                'type' => $setting->type,
                'default' => $setting->default,
                'description' => $setting->description,
                'show_in_rest' => $setting->showInRest,
                'sanitize_callback' => $setting->sanitizer($className),
            ];

            register_setting($group, $name, $args);

            // The page of each other language saves every setting: its own translation
            // of a translatable one, the shared value of the others.
            foreach ($languages as $language) {
                if (!$setting->translatable) {
                    register_setting(self::group($group, $language), $name, $args);

                    continue;
                }

                $option = self::option($name, $language);
                self::$translations[$option] = $name;

                // No default: an empty translation reads the default language.
                register_setting(self::group($group, $language), $option, [
                    ...$args,
                    'default' => '',
                    'show_in_rest' => false,
                ]);
            }
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
        $language = $setting->translatable ? self::language() : null;

        if ($language !== null) {
            $translation = get_option(self::option($name, $language), '');
            $value = $translation === '' || $translation === false || $translation === null ? $value : $translation;
        }

        return self::cast($value, $setting->type);
    }

    /**
     * The stored value of a setting in a language, without the fallback of get().
     *
     * What the settings page shows in the field of a translation.
     */
    public static function stored(string $name, ?string $language): mixed
    {
        if ($language === null) {
            return self::get($name);
        }

        return get_option(self::option($name, $language), '');
    }

    /**
     * The language a translatable setting is read and edited in, or null for the
     * default language.
     *
     * The language of the request on the front end. In the admin, the language
     * selected in the admin bar, and the default language when "all languages" is
     * selected. Null on a site with one language.
     */
    public static function language(): ?string
    {
        $multilingual = multilingual();

        if ($multilingual === null) {
            return null;
        }

        $language = is_admin() ? $multilingual->adminLanguage() : $multilingual->currentLanguage();

        return $language === null || $language === $multilingual->defaultLanguage() ? null : $language;
    }

    /**
     * The option that stores a setting in a language: `{name}` for the default
     * language (null), `{name}_{lang}` for the others.
     */
    public static function option(string $name, ?string $language): string
    {
        return $language === null ? $name : $name . '_' . $language;
    }

    /**
     * The option group that saves a page in a language: `{slug}` for the default
     * language (null), `{slug}_{lang}` for the others.
     */
    public static function group(string $group, ?string $language): string
    {
        return $language === null ? $group : $group . '_' . $language;
    }

    /**
     * Whether a setting, or the translation of a setting, was declared by a page.
     */
    public static function has(string $name): bool
    {
        return array_key_exists($name, self::$declared) || array_key_exists($name, self::$translations);
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
        self::$translations = [];
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
