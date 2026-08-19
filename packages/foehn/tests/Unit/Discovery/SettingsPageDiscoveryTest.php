<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsSettingsPage;
use Studiometa\Foehn\Discovery\SettingsPageDiscovery;
use Studiometa\Foehn\Settings\Setting;
use Tempest\Container\GenericContainer;
use Tests\Fixtures\PostTypeFixture;
use Tests\Fixtures\Settings\InterfacelessSettingsFixture;
use Tests\Fixtures\Settings\ThemeSettingsFixture;
use Tests\Fixtures\Settings\TopLevelSettingsFixture;

/**
 * @return list<array<string, mixed>>
 */
function discoveredPages(string $fixture): array
{
    $discovery = new SettingsPageDiscovery(new GenericContainer());

    discoverFixture($discovery, $fixture);

    return array_values(iterator_to_array($discovery->getItems()));
}

describe('SettingsPageDiscovery', function () {
    it('discovers a page and what it declares', function () {
        $items = discoveredPages(ThemeSettingsFixture::class);

        expect($items)->toHaveCount(1);
        expect($items[0]['attribute'])->toBeInstanceOf(AsSettingsPage::class);
        expect($items[0]['attribute']->slug)->toBe('theme-settings');
        expect($items[0]['className'])->toBe(ThemeSettingsFixture::class);
    });

    it('reads the settings during discovery, not when the page is applied', function () {
        // The declaration belongs in the cache with the rest of the item.
        $settings = discoveredPages(ThemeSettingsFixture::class)[0]['settings'];

        expect(array_keys($settings))->toBe([
            'foehn_contact_email',
            'foehn_show_banner',
            'foehn_posts_per_page',
            'foehn_ratio',
        ]);
        expect($settings['foehn_show_banner'])->toBeInstanceOf(Setting::class);
        expect($settings['foehn_show_banner']->type)->toBe('boolean');
    });

    it('ignores a class without the attribute', function () {
        expect(discoveredPages(PostTypeFixture::class))->toHaveCount(0);
    });

    it('rejects a page that cannot say what it stores', function () {
        expect(fn() => discoveredPages(InterfacelessSettingsFixture::class))
            ->toThrow(InvalidArgumentException::class, 'must implement');
    });

    it('discovers a top-level page', function () {
        $attribute = discoveredPages(TopLevelSettingsFixture::class)[0]['attribute'];

        expect($attribute->parent)->toBeNull();
        expect($attribute->menuTitle())->toBe('Shop settings');
        expect($attribute->icon)->toBe('dashicons-cart');
    });
});
