<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Assets;

/**
 * Enqueue assets built by Vite, in development and in production.
 *
 * `@studiometa/foehn-vite-plugin` writes two things, and which one exists decides
 * where the page loads its assets from:
 *
 * - `dist/hot`, while `npm run dev` runs, holding the dev server's URL. The page
 *   then loads the Vite client and the entries straight from that server, so hot
 *   module replacement works.
 * - `dist/.vite/manifest.json`, after `npm run build`, mapping each entry to its
 *   content-hashed file.
 *
 * ```php
 * #[AsAction('wp_enqueue_scripts')]
 * public function enqueueAssets(): void
 * {
 *     ViteManifest::fromTheme()
 *         ->enqueue('assets/js/app.js', handle: 'theme-app', inFooter: true)
 *         ->enqueue('assets/css/app.css', handle: 'theme-styles');
 * }
 * ```
 *
 * Entry names are the paths Vite was given as inputs, exactly as they appear as
 * keys in the manifest — usually relative to the Vite project root rather than to
 * the theme.
 *
 * This is not `WebpackManifest` with a different name: the two read different
 * formats. `@studiometa/webpack-config` emits an `assets-manifest.json` of
 * entrypoints; Vite emits a flat map of chunks, where a JavaScript entry carries
 * the CSS it imported in a `css` array. Enqueuing the JS without that array is the
 * mistake this class exists to stop a theme making — the page loads and the styles
 * are simply absent.
 */
final class ViteManifest
{
    /** @var array<string, array<string, mixed>>|null */
    private ?array $manifest = null;

    private ?string $devServer = null;

    private string $baseUri;

    /** @var array<string, true> Handles this instance enqueued as ES modules */
    private array $modules = [];

    private bool $filterRegistered = false;

    /**
     * @param string $distPath Absolute path to the build output directory
     * @param string $distUri  Public URI of that directory
     * @param string $hotFile  Name of the dev-server file the plugin writes
     */
    public function __construct(string $distPath, string $distUri, string $hotFile = 'hot')
    {
        $this->baseUri = rtrim($distUri, '/') . '/';

        $hotPath = rtrim($distPath, '/') . '/' . $hotFile;

        if (is_file($hotPath)) {
            $url = trim((string) file_get_contents($hotPath));

            if ($url !== '') {
                $this->devServer = rtrim($url, '/');

                return;
            }
        }

        $manifestPath = rtrim($distPath, '/') . '/.vite/manifest.json';

        if (!is_file($manifestPath)) {
            return;
        }

        $decoded = json_decode((string) file_get_contents($manifestPath), true);

        if (is_array($decoded)) {
            /** @var array<string, array<string, mixed>> $decoded */
            $this->manifest = $decoded;
        }
    }

    /**
     * Build from the active theme, for the layout the vite plugin produces.
     *
     * `dist/` sits inside the theme because only the theme is served — a build
     * written beside it never reaches a browser.
     */
    public static function fromTheme(string $distPath = 'dist', string $hotFile = 'hot'): self
    {
        $relative = trim($distPath, '/');

        return new self(
            rtrim(get_template_directory(), '/') . '/' . $relative,
            rtrim(get_template_directory_uri(), '/') . '/' . $relative,
            $hotFile,
        );
    }

    /**
     * Build from the child theme, when there is one.
     */
    public static function fromChildTheme(string $distPath = 'dist', string $hotFile = 'hot'): self
    {
        $relative = trim($distPath, '/');

        return new self(
            rtrim(get_stylesheet_directory(), '/') . '/' . $relative,
            rtrim(get_stylesheet_directory_uri(), '/') . '/' . $relative,
            $hotFile,
        );
    }

    /**
     * Enqueue one entry, and everything the build says it needs.
     *
     * @param string   $entry    Entry name, as it appears in the manifest
     * @param string   $handle   Handle to register it under
     * @param bool     $inFooter Load the script in the footer
     * @param string[] $deps     Dependencies
     * @param string   $media    Media attribute for stylesheets
     * @return self              Fluent interface
     */
    public function enqueue(
        string $entry,
        string $handle,
        bool $inFooter = false,
        array $deps = [],
        string $media = 'all',
    ): self {
        if ($this->devServer !== null) {
            // Passed rather than read from the property, so its non-nullness travels
            // with it — the caller is the only place that can prove it.
            $this->enqueueFromDevServer($this->devServer, $entry, $handle, $deps);

            return $this;
        }

        if ($this->manifest === null) {
            return $this;
        }

        $chunk = $this->manifest[$entry] ?? null;

        if (!is_array($chunk)) {
            return $this;
        }

        // A CSS entry has a `file` that is a stylesheet; a JS entry carries the CSS
        // it imported in `css`. Both have to be enqueued or the page loads unstyled.
        foreach ($this->stylesheets($chunk) as $index => $href) {
            $styleHandle = $index === 0 ? $handle . '-style' : $handle . '-style-' . $index;

            wp_enqueue_style($styleHandle, $this->baseUri . $href, $deps, null, $media);
        }

        $file = $chunk['file'] ?? null;

        if (is_string($file) && !str_ends_with($file, '.css')) {
            wp_enqueue_script($handle, $this->baseUri . $file, $deps, null, $inFooter);
            $this->markAsModule($handle);
        }

        return $this;
    }

    /**
     * Whether anything was found to enqueue from — a build or a dev server.
     */
    public function exists(): bool
    {
        return $this->devServer !== null || $this->manifest !== null;
    }

    /**
     * Whether the Vite dev server is running, which changes where assets come from.
     */
    public function isDevServer(): bool
    {
        return $this->devServer !== null;
    }

    /**
     * Every stylesheet an entry needs: its own file when it is a stylesheet, plus
     * whatever the chunk imported.
     *
     * @param array<string, mixed> $chunk
     * @return list<string>
     */
    private function stylesheets(array $chunk): array
    {
        $hrefs = [];
        $file = $chunk['file'] ?? null;

        if (is_string($file) && str_ends_with($file, '.css')) {
            $hrefs[] = $file;
        }

        $imported = $chunk['css'] ?? [];

        if (is_array($imported)) {
            foreach ($imported as $href) {
                if (!is_string($href)) {
                    continue;
                }

                $hrefs[] = $href;
            }
        }

        return array_values(array_unique($hrefs));
    }

    /**
     * @param string[] $deps
     */
    private function enqueueFromDevServer(string $devServer, string $entry, string $handle, array $deps): void
    {
        // The Vite client has to be on the page before any entry it serves, and once
        // per request no matter how many entries are enqueued.
        if (($this->modules['vite-client'] ?? false) === false) {
            wp_enqueue_script('vite-client', $devServer . '/@vite/client', [], null, false);
            $this->markAsModule('vite-client');
        }

        // Unhashed and unbundled: the dev server resolves the source path itself,
        // and a stylesheet arrives as a module that injects itself.
        wp_enqueue_script($handle, $devServer . '/' . ltrim($entry, '/'), $deps, null, false);
        $this->markAsModule($handle);
    }

    /**
     * Vite emits ES modules, which a classic `<script>` tag will not parse.
     *
     * `wp_script_add_data($handle, 'type', 'module')` looks like the way to say so
     * and is not: WP_Scripts reads `strategy`, `before`, `after` and `data`, and
     * nothing reads `type`. The tag has to be rewritten through `script_loader_tag`.
     *
     * The filter is bound to this instance and rewrites only the handles this
     * instance enqueued, so a theme's other scripts keep their tags.
     */
    private function markAsModule(string $handle): void
    {
        if (!$this->filterRegistered) {
            add_filter('script_loader_tag', $this->moduleTag(...), 10, 3);
            $this->filterRegistered = true;
        }

        $this->modules[$handle] = true;
    }

    /**
     * Rewrite one script tag as a module. Public because it is a filter callback.
     */
    public function moduleTag(string $tag, string $handle, string $source): string
    {
        if (($this->modules[$handle] ?? false) === false) {
            return $tag;
        }

        return sprintf('<script type="module" src="%s" id="%s"></script>' . "\n", esc_url($source), esc_attr($handle));
    }
}
