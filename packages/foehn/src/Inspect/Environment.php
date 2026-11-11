<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Inspect;

use Studiometa\Foehn\Assets\ViteManifest;

/**
 * What no attribute declares: plugins, theme supports and the Vite build, read from
 * WordPress and the filesystem.
 */
final readonly class Environment
{
    /**
     * Active plugins, network-activated ones and must-use plugins, with versions.
     *
     * @return list<array<string, mixed>>
     */
    public function plugins(): array
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $installed = get_plugins();
        $active = get_option('active_plugins', []);
        $active = is_array($active) ? $active : [];

        if (is_multisite()) {
            $network = get_site_option('active_sitewide_plugins', []);
            $active = [...$active, ...array_keys(is_array($network) ? $network : [])];
        }

        $rows = [];

        foreach (Rows::unique($active) as $file) {
            $rows[] = self::plugin($file, $installed[$file] ?? [], mustUse: false);
        }

        foreach (get_mu_plugins() as $file => $data) {
            $rows[] = self::plugin((string) $file, $data, mustUse: true);
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $data The plugin header, as get_plugins() reads it
     * @return array<string, mixed>
     */
    private static function plugin(string $file, array $data, bool $mustUse): array
    {
        $name = $data['Name'] ?? '';
        $version = $data['Version'] ?? '';

        return [
            'name' => is_string($name) && $name !== '' ? $name : $file,
            'version' => is_string($version) && $version !== '' ? $version : null,
            'file' => $file,
            'mustUse' => $mustUse,
        ];
    }

    /**
     * Every theme feature declared with add_theme_support(), with its arguments.
     *
     * WordPress has no function that lists them, only one that answers for a feature
     * already named, so the global it keeps them in is the source.
     *
     * @return list<array<string, mixed>>
     */
    public function themeSupports(): array
    {
        // @mago-expect lint:no-global
        $features = $GLOBALS['_wp_theme_features'] ?? [];
        $rows = [];

        foreach (is_array($features) ? $features : [] as $feature => $value) {
            $rows[] = ['feature' => (string) $feature, 'value' => $value];
        }

        return Rows::sortedBy($rows, 'feature');
    }

    /**
     * The theme's Vite build: its entry points and whether the dev server runs.
     *
     * @return array{devServer: ?string, entries: list<array{entry: string, file: string, css: list<string>}>}
     */
    public function vite(): array
    {
        $manifest = ViteManifest::fromTheme();

        return [
            'devServer' => $manifest->devServer(),
            'entries' => $manifest->entries(),
        ];
    }
}
