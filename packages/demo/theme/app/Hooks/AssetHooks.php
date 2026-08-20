<?php

declare(strict_types=1);

namespace Demo\Hooks;

use Studiometa\Foehn\Attributes\AsAction;
use Studiometa\Foehn\Attributes\AsFilter;

/**
 * Put the Vite build on the page.
 *
 * `@studiometa/foehn-vite-plugin` writes two things: `dist/.vite/manifest.json` for
 * a production build, and a `hot` file holding the dev server's URL while `npm run
 * dev` is running. This reads whichever is there.
 *
 * The framework has `WebpackManifest`, which reads the `assets-manifest.json` that
 * `@studiometa/webpack-config` emits — a different format from Vite's, so it cannot
 * be used here. The vite plugin's README points at a `ViteManifest` helper that the
 * framework does not ship; until it does, a theme reads the manifest itself.
 */
final class AssetHooks
{
    private const ENTRIES = ['theme/assets/css/app.css', 'theme/assets/js/app.js'];

    #[AsAction('wp_enqueue_scripts')]
    public function enqueue(): void
    {
        $themeDir = get_template_directory();
        $themeUri = rtrim(get_template_directory_uri(), '/');
        $hot = $themeDir . '/dist/hot';

        if (file_exists($hot)) {
            $server = rtrim(trim((string) file_get_contents($hot)), '/');

            wp_enqueue_script('vite-client', $server . '/@vite/client', [], null, false);

            foreach (self::ENTRIES as $entry) {
                wp_enqueue_script('vite-' . sanitize_title($entry), $server . '/' . $entry, [], null, false);
            }

            return;
        }

        $manifestPath = $themeDir . '/dist/.vite/manifest.json';

        if (!file_exists($manifestPath)) {
            return;
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);

        if (!is_array($manifest)) {
            return;
        }

        foreach (self::ENTRIES as $entry) {
            $file = $manifest[$entry]['file'] ?? null;

            if (!is_string($file)) {
                continue;
            }

            $url = $themeUri . '/dist/' . $file;
            $handle = 'demo-' . sanitize_title($entry);
            // The built filename is already content-hashed, so a version query would
            // only add a second cache key for the same bytes.
            $version = null;

            if (str_ends_with($file, '.css')) {
                wp_enqueue_style($handle, $url, [], $version);

                continue;
            }

            wp_enqueue_script($handle, $url, [], $version, true);
        }
    }

    /**
     * Vite emits ES modules, which a classic <script> tag will not parse.
     */
    #[AsFilter('script_loader_tag', acceptedArgs: 3)]
    public function moduleType(string $tag, string $handle, string $source): string
    {
        if (!str_starts_with($handle, 'demo-') && !str_starts_with($handle, 'vite-')) {
            return $tag;
        }

        return sprintf('<script type="module" src="%s" id="%s"></script>' . "\n", esc_url($source), esc_attr($handle));
    }
}
