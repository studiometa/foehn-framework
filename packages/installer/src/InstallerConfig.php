<?php

declare(strict_types=1);

namespace Studiometa\FoehnInstaller;

/**
 * Configuration for the Foehn installer plugin.
 *
 * Configured via `extra.foehn` in the project's `composer.json`:
 *
 * ```json
 * {
 *     "extra": {
 *         "foehn": {
 *             "web-dir": "web",
 *             "wp-dir": "wp",
 *             "theme-dir": "theme",
 *             "theme-name": "my-theme",
 *             "mu-plugins-dir": "mu-plugins",
 *             "config-dir": "config"
 *         }
 *     }
 * }
 * ```
 */
final readonly class InstallerConfig
{
    public function __construct(
        /** Whether the installer is enabled (auto-detected or explicit). */
        public bool $enabled = false,
        /** Relative path to the web root directory. */
        public string $webDir = 'web',
        /** Relative path to WordPress core within the web root. */
        public string $wpDir = 'wp',
        /** Relative path to the theme source directory. */
        public string $themeDir = 'theme',
        /** Theme directory name as it appears in wp-content/themes/. */
        public string $themeName = 'theme',
        /** Relative path to the custom mu-plugins directory. */
        public string $muPluginsDir = 'mu-plugins',
        /** Relative path to the config directory. */
        public string $configDir = 'config',
    ) {}

    /**
     * Create from the `extra.foehn` section of composer.json.
     */
    public static function fromArray(array $config, string $projectRoot): self
    {
        // Auto-detect: if there's a theme/ directory or explicit config, enable
        $hasThemeDir = is_dir($projectRoot . '/' . ($config['theme-dir'] ?? 'theme'));
        $hasExplicitConfig = $config !== [];
        $enabled = $hasThemeDir || $hasExplicitConfig;

        return new self(
            enabled: $enabled,
            webDir: $config['web-dir'] ?? 'web',
            wpDir: $config['wp-dir'] ?? 'wp',
            themeDir: $config['theme-dir'] ?? 'theme',
            themeName: $config['theme-name'] ?? 'theme',
            muPluginsDir: $config['mu-plugins-dir'] ?? 'mu-plugins',
            configDir: $config['config-dir'] ?? 'config',
        );
    }
}
