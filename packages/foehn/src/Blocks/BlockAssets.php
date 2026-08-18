<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Blocks;

/**
 * Finds the per-block assets of a native block by convention.
 *
 * A block named `theme/callout` picks up `assets/css/blocks/callout.css` and
 * `assets/js/blocks/callout.js` from the theme, when those files exist. Nothing
 * is declared anywhere: the files are named after the block, so a block owns its
 * assets without configuring them, and a block with no assets needs no config.
 *
 * Both are attached to the block type rather than enqueued globally, so
 * WordPress loads them only for pages that actually render the block, and loads
 * the stylesheet into the editor as well — which is what makes the
 * server-rendered preview look like the front end.
 */
final class BlockAssets
{
    /** Theme-relative stylesheet path, by block slug. */
    private const string STYLE_PATH = 'assets/css/blocks/%s.css';

    /** Theme-relative front-end script path, by block slug. */
    private const string SCRIPT_PATH = 'assets/js/blocks/%s.js';

    /**
     * Register whichever assets exist for a block, and return the
     * register_block_type() arguments that attach them.
     *
     * @return array<string, list<string>>
     */
    public static function register(string $blockName): array
    {
        $slug = self::slug($blockName);
        $handle = str_replace('/', '-', $blockName);
        $args = [];

        $style = self::registerStyle(sprintf(self::STYLE_PATH, $slug), $handle . '-style');

        if ($style !== null) {
            $args['style_handles'] = [$style];
        }

        $script = self::registerScript(sprintf(self::SCRIPT_PATH, $slug), $handle . '-view-script');

        if ($script !== null) {
            // A view script loads only when the block is on the page, which is
            // the right default for behaviour attached to a single block.
            $args['view_script_handles'] = [$script];
        }

        return $args;
    }

    /**
     * The part of a block name after its namespace: `theme/callout` is `callout`.
     */
    private static function slug(string $blockName): string
    {
        $parts = explode('/', $blockName);

        return end($parts);
    }

    /**
     * Register a stylesheet, or nothing at all when the file is absent.
     */
    private static function registerStyle(string $relativePath, string $handle): ?string
    {
        $path = self::existingPath($relativePath);

        if ($path === null) {
            return null;
        }

        wp_register_style($handle, get_theme_file_uri($relativePath), [], (string) filemtime($path));

        return $handle;
    }

    /**
     * Register a script, or nothing at all when the file is absent.
     */
    private static function registerScript(string $relativePath, string $handle): ?string
    {
        $path = self::existingPath($relativePath);

        if ($path === null) {
            return null;
        }

        wp_register_script($handle, get_theme_file_uri($relativePath), [], (string) filemtime($path), true);

        return $handle;
    }

    /**
     * Resolve a theme-relative path, but only when it really is a file.
     *
     * Registering an asset that does not exist would emit a 404 on every page
     * that renders the block, so absence simply means "this block has no such
     * asset". get_theme_file_path() resolves through the child theme first.
     */
    private static function existingPath(string $relativePath): ?string
    {
        $path = get_theme_file_path($relativePath);

        return is_file($path) ? $path : null;
    }
}
