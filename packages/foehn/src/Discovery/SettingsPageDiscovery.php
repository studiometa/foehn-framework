<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use Studiometa\Foehn\Attributes\AsDiscovery;
use Studiometa\Foehn\Attributes\AsSettingsPage;
use Studiometa\Foehn\Contracts\SettingsInterface;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Studiometa\Foehn\Settings\Setting;
use Studiometa\Foehn\Settings\SettingField;
use Studiometa\Foehn\Settings\Settings;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;

/**
 * Discovers classes marked with #[AsSettingsPage] and registers their settings,
 * their admin page and its form.
 *
 * Two hooks, not one. `register_setting()` belongs on `init`, which is this
 * phase; the menu entry and the fields belong on `admin_menu`, which apply()
 * hooks itself — one discovery wanting a different hook is not a reason for a
 * fourth phase.
 */
#[AsDiscovery(phase: DiscoveryPhase::Main)]
final class SettingsPageDiscovery implements Discovery
{
    use IsWpDiscovery;

    public function __construct(
        private readonly DiscoveryProblems $problems,
    ) {}

    /**
     * Discover settings pages.
     */
    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        if (!$this->isConcrete($class)) {
            return;
        }

        $attribute = $class->getAttribute(AsSettingsPage::class);

        if ($attribute === null) {
            return;
        }

        // Checked before the settings are read: a class carrying the attribute
        // without the interface has no settings() to call, and the error it
        // would produce names the wrong thing.
        if (!$class->implements(SettingsInterface::class)) {
            $this->problems->report(
                $location,
                $class->getName(),
                '#[AsSettingsPage]',
                sprintf(
                    '%s has #[AsSettingsPage] but does not implement %s, so the settings page is not registered.',
                    $class->getName(),
                    SettingsInterface::class,
                ),
                sprintf('Declare %s with `implements \\%s`.', $class->getName(), SettingsInterface::class),
            );

            return;
        }

        /** @var array<string, Setting> $settings */
        $settings = $class->getName()::settings();

        $this->addItem($location, [
            'attribute' => $attribute,
            'className' => $class->getName(),
            // Read here rather than in apply(): settings() is what the page
            // declares, and the declaration belongs in the cache with the rest
            // of the item.
            'settings' => $settings,
        ]);
    }

    /**
     * Apply discovered settings pages.
     */
    public function apply(): void
    {
        $items = iterator_to_array($this->getItems());

        if ($items === []) {
            return;
        }

        foreach ($items as $item) {
            /** @var AsSettingsPage $attribute */
            $attribute = $item['attribute'];

            Settings::register($attribute->slug, $item['settings'], $item['className']);
        }

        add_action('admin_menu', function () use ($items): void {
            foreach ($items as $item) {
                $this->registerPage($item);
                $this->registerFields($item);
            }
        });
    }

    /**
     * Add the page to the admin menu.
     *
     * @param array<string, mixed> $item
     */
    private function registerPage(array $item): void
    {
        /** @var AsSettingsPage $attribute */
        $attribute = $item['attribute'];

        $render = function () use ($attribute): void {
            $this->renderPage($attribute);
        };

        if ($attribute->parent !== null) {
            // No position: WordPress orders a submenu by registration, and the
            // attribute documents `position` as a top-level concern.
            add_submenu_page(
                $attribute->parent,
                $attribute->title,
                $attribute->menuTitle(),
                $attribute->capability,
                $attribute->slug,
                $render,
            );

            return;
        }

        $icon = $attribute->icon ?? '';

        // Called with six arguments rather than seven when no position was
        // declared: `null` is WordPress's own default and means "at the bottom",
        // but the parameter is not typed to accept it.
        if ($attribute->position === null) {
            add_menu_page(
                $attribute->title,
                $attribute->menuTitle(),
                $attribute->capability,
                $attribute->slug,
                $render,
                $icon,
            );

            return;
        }

        add_menu_page(
            $attribute->title,
            $attribute->menuTitle(),
            $attribute->capability,
            $attribute->slug,
            $render,
            $icon,
            $attribute->position,
        );
    }

    /**
     * Add a field for each setting, in one untitled section of the page.
     *
     * `do_settings_sections()` prints them as the rows of a `form-table`, with
     * the label in the row header, as on the WordPress settings screens. A
     * checkbox gets no `label_for`: its own label holds the box and its text.
     *
     * @param array<string, mixed> $item
     */
    private function registerFields(array $item): void
    {
        /** @var AsSettingsPage $attribute */
        $attribute = $item['attribute'];
        /** @var array<string, Setting> $settings */
        $settings = $item['settings'];

        // No title and nothing printed above the fields: the page heading names them.
        add_settings_section('default', '', static function (): void {}, $attribute->slug);

        foreach ($settings as $name => $setting) {
            add_settings_field(
                $name,
                esc_html($setting->label($name)),
                static function () use ($name, $setting): void {
                    echo SettingField::render($name, $setting, Settings::get($name));
                },
                $attribute->slug,
                'default',
                $setting->control === 'checkbox' ? [] : ['label_for' => $name],
            );
        }
    }

    /**
     * The page WordPress expects: the heading, the notices and the form.
     *
     * `settings_fields()` prints the nonce and the option group without which
     * `options.php` rejects the save, and `do_settings_sections()` prints the
     * fields registerFields() added.
     */
    private function renderPage(AsSettingsPage $attribute): void
    {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html($attribute->title) . '</h1>';

        settings_errors();

        echo '<form action="options.php" method="post">';

        settings_fields($attribute->slug);
        do_settings_sections($attribute->slug);
        submit_button();

        echo '</form>';
        echo '</div>';
    }
}
