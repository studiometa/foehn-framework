<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Places\Admin;

use Studiometa\Foehn\Attributes\AsSettingsPage;
use Studiometa\Foehn\Contracts\SettingsInterface;
use Studiometa\Foehn\Places\Config\PlacesConfig;
use Studiometa\Foehn\Settings\Setting;

/**
 * The Mapbox token and style, under the `place` post type menu.
 *
 * A non-null `PlacesConfig::$accessToken` or `$style` wins over the option: PlacesHooks
 * answers the option with it through `pre_option_{$option}`, so the page shows it
 * read-only, and keeps the stored option when the page is saved.
 */
#[AsSettingsPage(slug: 'place-locator-settings', title: 'Place locator settings', parent: 'edit.php?post_type=place')]
final readonly class PlacesSettings implements SettingsInterface
{
    // The option name, not a secret: the token is public and printed in the markup.
    // @mago-expect lint:no-literal-password
    public const string TOKEN = 'foehn_places_mapbox_access_token';

    public const string STYLE = 'foehn_places_mapbox_style';

    public const string SLUG = 'place-locator-settings';

    public static function settings(): array
    {
        return [
            self::TOKEN => Setting::string(
                label: 'Mapbox access token',
                description: 'A public token: it is printed in the page markup. Restrict it to the URLs of this site in your Mapbox account (https://docs.mapbox.com/accounts/guides/tokens/#url-restrictions).',
            ),
            self::STYLE => Setting::string(
                label: 'Map style',
                description: 'A Mapbox style URL. Empty: ' . PlacesConfig::DEFAULT_STYLE . '.',
                control: 'url',
            ),
        ];
    }

    /**
     * The token: the config value, else the option, else empty.
     */
    public static function accessToken(PlacesConfig $config): string
    {
        return $config->accessToken ?? self::option(self::TOKEN);
    }

    /**
     * The style: the config value, else the option, else the default style.
     */
    public static function style(PlacesConfig $config): string
    {
        $style = $config->style ?? self::option(self::STYLE);

        return $style === '' ? PlacesConfig::DEFAULT_STYLE : $style;
    }

    private static function option(string $name): string
    {
        $value = get_option($name, '');

        return is_string($value) ? trim($value) : '';
    }
}
