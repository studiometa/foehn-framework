<?php

declare(strict_types=1);

namespace Tests\Fixtures\Settings;

use Studiometa\Foehn\Attributes\AsSettingsPage;
use Studiometa\Foehn\Contracts\SettingsPageInterface;
use Studiometa\Foehn\Settings\Setting;

#[AsSettingsPage(
    slug: 'shop-settings',
    title: 'Shop',
    menuTitle: 'Shop settings',
    parent: null,
    capability: 'edit_posts',
    icon: 'dashicons-cart',
    position: 58,
    template: 'settings/shop',
)]
final class TopLevelSettingsFixture implements SettingsPageInterface
{
    /**
     * @return array<string, Setting>
     */
    public static function settings(): array
    {
        return ['foehn_currency' => Setting::string(default: 'EUR')];
    }
}
