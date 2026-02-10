<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Assets;

use Studiometa\WebpackConfig\Manifest;

/**
 * Helper for enqueuing assets from @studiometa/webpack-config manifest.
 *
 * Usage:
 * ```php
 * use Studiometa\Foehn\Assets\WebpackManifest;
 * use Studiometa\Foehn\Attributes\AsAction;
 *
 * #[AsAction('wp_enqueue_scripts')]
 * public function enqueueAssets(): void
 * {
 *     WebpackManifest::fromTheme('/dist/assets-manifest.json')
 *         ->enqueueEntry('css/app', prefix: 'theme')
 *         ->enqueueEntry('js/app', prefix: 'theme', inFooter: true);
 * }
 * ```
 */
final class WebpackManifest
{
    private ?Manifest $manifest = null;

    private string $baseUri;

    private string $basePath;

    /**
     * @param string $manifestPath Absolute path to the manifest file
     * @param string $distPath     Relative path from theme root to dist directory (used by Manifest class to prefix asset paths)
     * @param string $baseUri      Base URI for assets (defaults to theme URI, without dist path since Manifest adds it)
     * @param string $basePath     Base path for assets (defaults to theme directory, without dist path since Manifest adds it)
     */
    public function __construct(
        string $manifestPath,
        string $distPath = 'dist/',
        ?string $baseUri = null,
        ?string $basePath = null,
    ) {
        if (file_exists($manifestPath)) {
            $this->manifest = new Manifest($manifestPath, $distPath);
        }

        // Note: Manifest class prepends distPath to asset hrefs/srcs, so baseUri/basePath should NOT include it
        $this->baseUri = $baseUri ?? rtrim(get_template_directory_uri(), '/') . '/';
        $this->basePath = $basePath ?? rtrim(get_template_directory(), '/') . '/';
    }

    /**
     * Create instance from theme directory.
     *
     * @param string $manifestPath Relative path to manifest from theme root (e.g., '/dist/assets-manifest.json')
     * @param string $distPath     Relative path to dist directory (e.g., 'dist/')
     */
    public static function fromTheme(
        string $manifestPath = '/dist/assets-manifest.json',
        string $distPath = 'dist/',
    ): self {
        return new self(get_template_directory() . '/' . ltrim($manifestPath, '/'), $distPath);
    }

    /**
     * Create instance from child theme directory.
     *
     * @param string $manifestPath Relative path to manifest from child theme root
     * @param string $distPath     Relative path to dist directory
     */
    public static function fromChildTheme(
        string $manifestPath = '/dist/assets-manifest.json',
        string $distPath = 'dist/',
    ): self {
        return new self(
            get_stylesheet_directory() . '/' . ltrim($manifestPath, '/'),
            $distPath,
            rtrim(get_stylesheet_directory_uri(), '/') . '/',
            rtrim(get_stylesheet_directory(), '/') . '/',
        );
    }

    /**
     * Enqueue all assets from an entry.
     *
     * @param string   $entry    Entry name (e.g., 'css/app', 'js/app')
     * @param string   $prefix   Handle prefix (e.g., 'theme' → 'theme-app')
     * @param bool     $inFooter Load scripts in footer (default: false)
     * @param string[] $deps     Dependencies for scripts/styles
     * @param string   $media    Media attribute for styles (default: 'all')
     * @return self              Fluent interface
     */
    public function enqueueEntry(
        string $entry,
        string $prefix = 'theme',
        bool $inFooter = false,
        array $deps = [],
        string $media = 'all',
    ): self {
        if ($this->manifest === null) {
            return $this;
        }

        $entryData = $this->manifest->entry($entry);

        if ($entryData === null) {
            return $this;
        }

        // Enqueue styles
        foreach ($entryData->styles as $handle => $link) {
            $href = $link->getAttribute('href');
            $fullPath = $this->basePath . $href;
            $version = file_exists($fullPath) ? md5_file($fullPath) : null;

            wp_enqueue_style($prefix . '-' . sanitize_title($handle), $this->baseUri . $href, $deps, $version, $media);
        }

        // Enqueue scripts
        foreach ($entryData->scripts as $handle => $script) {
            $src = $script->getAttribute('src');
            $fullPath = $this->basePath . $src;
            $version = file_exists($fullPath) ? md5_file($fullPath) : null;

            wp_enqueue_script(
                $prefix . '-' . sanitize_title($handle),
                $this->baseUri . $src,
                $deps,
                $version,
                $inFooter,
            );
        }

        return $this;
    }

    /**
     * Enqueue multiple entries at once.
     *
     * @param string[] $entries  Entry names to enqueue
     * @param string   $prefix   Handle prefix
     * @param bool     $inFooter Load scripts in footer
     * @return self              Fluent interface
     */
    public function enqueueEntries(array $entries, string $prefix = 'theme', bool $inFooter = false): self
    {
        foreach ($entries as $entry) {
            $this->enqueueEntry($entry, $prefix, $inFooter);
        }

        return $this;
    }

    /**
     * Check if the manifest was loaded successfully.
     */
    public function exists(): bool
    {
        return $this->manifest !== null;
    }

    /**
     * Get the underlying Manifest instance for advanced usage.
     *
     * Returns null if manifest file was not found.
     */
    public function getManifest(): ?Manifest
    {
        return $this->manifest;
    }
}
