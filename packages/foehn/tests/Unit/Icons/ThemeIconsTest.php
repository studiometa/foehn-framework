<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Icons\ThemeIcons;

/**
 * Create a theme directory with the given files under assets/icons/.
 *
 * @param list<string> $files
 */
function themeWithIcons(array $files): string
{
    $theme = sys_get_temp_dir() . '/foehn-icons-theme-' . uniqid();
    mkdir($theme . '/' . ThemeIcons::DIRECTORY, 0o777, true);

    foreach ($files as $file) {
        $path = $theme . '/' . ThemeIcons::DIRECTORY . '/' . $file;

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0o777, true);
        }

        file_put_contents(
            $path,
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M0 0h24v24H0z"/></svg>',
        );
    }

    $GLOBALS['wp_stub_template_directory'] = $theme;

    return $theme;
}

/**
 * Run a callback and return the E_USER_NOTICE messages it raised.
 *
 * @return list<string>
 */
function iconNotices(callable $callback): array
{
    $notices = [];

    set_error_handler(function (int $errno, string $errstr) use (&$notices): bool {
        $notices[] = $errstr;

        return true;
    }, E_USER_NOTICE);

    try {
        $callback();
    } finally {
        restore_error_handler();
    }

    return $notices;
}

describe('ThemeIcons', function () {
    beforeEach(function () {
        wp_stub_reset();
    });

    it('registers on init', function () {
        new ThemeIcons(new FoehnConfig())->register();

        expect(array_column(array_column(wp_stub_get_calls('add_action'), 'args'), 'hook'))->toBe(['init']);
    });

    it('registers one collection named after the theme, and one icon per file', function () {
        $GLOBALS['wp_stub_stylesheet'] = 'starter-theme';
        $theme = themeWithIcons(['arrow-right.svg', 'close_small.svg']);

        new ThemeIcons(new FoehnConfig())->registerIcons();

        $collections = wp_stub_get_calls('wp_register_icon_collection');
        expect($collections)->toHaveCount(1);
        expect($collections[0]['args'])->toBe(['slug' => 'starter-theme', 'args' => ['label' => 'Test Theme']]);

        $icons = array_column(wp_stub_get_calls('wp_register_icon'), 'args');
        usort($icons, fn(array $a, array $b): int => strcmp($a['icon_name'], $b['icon_name']));

        expect($icons)->toBe([
            [
                'icon_name' => 'starter-theme/arrow-right',
                'args' => ['label' => 'Arrow right', 'file_path' => $theme . '/assets/icons/arrow-right.svg'],
            ],
            [
                'icon_name' => 'starter-theme/close_small',
                'args' => ['label' => 'Close small', 'file_path' => $theme . '/assets/icons/close_small.svg'],
            ],
        ]);
    });

    it('registers nothing, not even a collection, when the theme has no icons', function () {
        $GLOBALS['wp_stub_template_directory'] = sys_get_temp_dir() . '/foehn-icons-none-' . uniqid();

        new ThemeIcons(new FoehnConfig())->registerIcons();

        expect(wp_stub_get_calls('wp_register_icon_collection'))->toBe([]);
    });

    it('reads only the SVG files directly in the directory', function () {
        themeWithIcons(['social/x.svg', 'readme.txt']);

        expect(ThemeIcons::files())->toBe([]);
    });

    it('skips a file whose name WordPress refuses, with a notice when debug is on', function () {
        themeWithIcons(['ArrowLeft.svg', 'plus.svg']);

        $notices = iconNotices(fn() => new ThemeIcons(new FoehnConfig(debug: true))->registerIcons());

        expect(array_column(array_column(wp_stub_get_calls('wp_register_icon'), 'args'), 'icon_name'))->toBe([
            'theme/plus',
        ]);
        expect($notices)->toHaveCount(1);
        expect($notices[0])->toContain('ArrowLeft.svg');
    });

    it('skips such a file silently when debug is off', function () {
        themeWithIcons(['ArrowLeft.svg']);

        expect(iconNotices(fn() => new ThemeIcons(new FoehnConfig())->registerIcons()))->toBe([]);
    });

    it('makes a valid collection name from any theme directory name', function (
        string $stylesheet,
        string $collection,
    ) {
        $GLOBALS['wp_stub_stylesheet'] = $stylesheet;

        expect(ThemeIcons::collection())->toBe($collection);
    })->with([
        'already valid' => ['starter-theme', 'starter-theme'],
        'uppercase and dots' => ['My.Theme', 'my-theme'],
        'leading and trailing separators' => ['_theme-', 'theme'],
        'nothing usable' => ['---', 'theme'],
    ]);

    it('qualifies a bare name with the theme collection and keeps a full name', function () {
        $GLOBALS['wp_stub_stylesheet'] = 'starter-theme';

        expect(ThemeIcons::qualify('arrow-right'))->toBe('starter-theme/arrow-right');
        expect(ThemeIcons::qualify('core/search'))->toBe('core/search');
    });
});
