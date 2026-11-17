<?php

declare(strict_types=1);

namespace Tests\Fixtures\Settings;

use Studiometa\Foehn\Attributes\AsSettingsPage;
use Studiometa\Foehn\Contracts\SettingsInterface;
use Studiometa\Foehn\Settings\Setting;

#[AsSettingsPage(
    slug: 'shop-settings',
    title: 'Shop',
    menuTitle: 'Shop settings',
    parent: null,
    capability: 'edit_posts',
    icon: 'dashicons-cart',
    position: 58,
)]
final class TopLevelSettingsFixture implements SettingsInterface
{
    /**
     * @return array<string, Setting>
     */
    public static function settings(): array
    {
        return ['foehn_currency' => Setting::string(default: 'EUR')];
    }
}
