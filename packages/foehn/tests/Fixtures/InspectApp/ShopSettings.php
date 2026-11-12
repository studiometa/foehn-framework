<?php

declare(strict_types=1);

namespace Tests\Fixtures\InspectApp;

use Studiometa\Foehn\Attributes\AsSettingsPage;
use Studiometa\Foehn\Contracts\SettingsPageInterface;
use Studiometa\Foehn\Settings\Setting;

/**
 * A settings page with a declared sanitizer, a type default one, and one of its own.
 */
#[AsSettingsPage(slug: 'shop-settings', title: 'Shop settings', template: 'settings/shop')]
final class ShopSettings implements SettingsPageInterface
{
    public static function settings(): array
    {
        return [
            'shop_contact_email' => Setting::string(sanitize: 'sanitize_email'),
            'shop_show_banner' => Setting::bool(default: true),
            'shop_currency' => Setting::string(default: 'EUR', sanitize: 'normalizeCurrency'),
        ];
    }

    public static function normalizeCurrency(mixed $value): string
    {
        return strtoupper((string) $value);
    }
}
