<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Icons;

use Studiometa\Foehn\Config\FoehnConfig;

/**
 * Registers the theme's SVG icons with the WordPress Icons API, by convention.
 *
 * Every `assets/icons/*.svg` file of the theme becomes an icon of one collection
 * named after the theme: `assets/icons/arrow-right.svg` in the `starter-theme`
 * theme is `starter-theme/arrow-right`. Nothing is declared anywhere, the way a
 * block finds its stylesheet in {@see \Studiometa\Foehn\Blocks\BlockAssets}.
 *
 * Only the files directly in the directory count: an icon name cannot hold a
 * slash, so a subdirectory has no name to map to.
 *
 * WordPress reads a file only when the icon is first rendered, and sanitizes it
 * then. Registering costs a directory listing per request, nothing more.
 *
 * The Icons API arrived in WordPress 7.1. On an older site the icons are not
 * registered, and a notice says so when debug is on.
 */
final readonly class ThemeIcons
{
    /** Theme-relative directory the icons are read from. */
    public const string DIRECTORY = 'assets/icons';

    /**
     * What WordPress accepts as an icon name and as a collection name: lowercase
     * letters, digits, `-` and `_`, starting and ending with a letter or a digit.
     */
    private const string NAME_PATTERN = '/^[a-z0-9](?:[a-z0-9_-]*[a-z0-9])?$/';

    public function __construct(
        private FoehnConfig $config,
    ) {}

    /**
     * Register the icons on `init`, the hook core registers its own icons on.
     */
    public function register(): void
    {
        add_action('init', $this->registerIcons(...));
    }

    /**
     * Register the collection and one icon per file.
     */
    public function registerIcons(): void
    {
        $files = self::files();

        if ($files === []) {
            return;
        }

        if (!function_exists('wp_register_icon_collection') || !function_exists('wp_register_icon')) {
            $this->notice(sprintf(
                '[Foehn] The theme has icons in "%s", but this WordPress has no Icons API (WordPress 7.1). They are not registered.',
                self::DIRECTORY,
            ));

            return;
        }

        $collection = self::collection();
        $label = wp_get_theme()->get('Name');

        wp_register_icon_collection($collection, ['label' => $label !== '' ? $label : $collection]);

        foreach ($files as $name => $path) {
            if (preg_match(self::NAME_PATTERN, $name) !== 1) {
                $this->notice(sprintf(
                    '[Foehn] The icon "%s" is not registered: rename the file to lowercase letters, digits, "-" and "_".',
                    $path,
                ));

                continue;
            }

            wp_register_icon($collection . '/' . $name, ['label' => self::label($name), 'file_path' => $path]);
        }
    }

    /**
     * The name of the theme's collection: the theme's directory name, made valid.
     */
    public static function collection(): string
    {
        $name = trim((string) preg_replace('/[^a-z0-9_-]+/', '-', strtolower(get_stylesheet())), '-_');

        return $name !== '' ? $name : 'theme';
    }

    /**
     * The full name of an icon: a name without a collection is the theme's.
     */
    public static function qualify(string $name): string
    {
        return str_contains($name, '/') ? $name : self::collection() . '/' . $name;
    }

    /**
     * The theme's icon files, by icon name.
     *
     * get_theme_file_path() looks in the child theme first, so a child theme with
     * its own directory uses that one.
     *
     * @return array<string, string>
     */
    public static function files(): array
    {
        $directory = get_theme_file_path(self::DIRECTORY);

        if (!is_dir($directory)) {
            return [];
        }

        $files = [];

        foreach (glob($directory . '/*.svg') ?: [] as $path) {
            $files[basename($path, '.svg')] = $path;
        }

        return $files;
    }

    /**
     * A label from a file name: `arrow-right` is "Arrow right".
     */
    private static function label(string $name): string
    {
        return ucfirst(str_replace(['-', '_'], ' ', $name));
    }

    private function notice(string $message): void
    {
        if ($this->config->isDebugEnabled()) {
            trigger_error($message, E_USER_NOTICE);
        }
    }
}
