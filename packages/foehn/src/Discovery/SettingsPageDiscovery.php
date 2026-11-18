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

use function Studiometa\Foehn\multilingual;

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

        /** @var array<string, Setting> $settings */
        $settings = $item['settings'];
        $translatable = array_any($settings, static fn(Setting $setting): bool => $setting->translatable);

        $render = function () use ($attribute, $translatable): void {
            $this->renderPage($attribute, $translatable);
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
     * A translatable setting edits its translation in the language of the admin
     * bar, so its field carries the name of that option.
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

        $language = Settings::language();

        foreach ($settings as $name => $setting) {
            $fieldLanguage = $setting->translatable ? $language : null;
            $option = Settings::option($name, $fieldLanguage);

            add_settings_field(
                $option,
                esc_html($setting->label($name)),
                static function () use ($name, $option, $setting, $fieldLanguage): void {
                    echo SettingField::render($option, $setting, Settings::stored($name, $fieldLanguage));
                },
                $attribute->slug,
                'default',
                $setting->control === 'checkbox' ? [] : ['label_for' => $option],
            );
        }
    }

    /**
     * The page WordPress expects: the heading, the notices and the form.
     *
     * `settings_fields()` prints the nonce and the option group without which
     * `options.php` rejects the save, and `do_settings_sections()` prints the
     * fields registerFields() added. A page with translatable settings saves in
     * the option group of the language it edits.
     */
    private function renderPage(AsSettingsPage $attribute, bool $translatable = false): void
    {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html($attribute->title) . '</h1>';

        settings_errors();

        if ($translatable) {
            $this->renderLanguageNotice();
        }

        echo '<form action="options.php" method="post">';

        settings_fields(Settings::group($attribute->slug, $translatable ? Settings::language() : null));
        do_settings_sections($attribute->slug);
        submit_button();

        echo '</form>';
        echo '</div>';
    }

    /**
     * Say which language the translatable fields edit.
     *
     * With "all languages" selected in the admin bar, the page edits the default
     * language, and asks to select a language to edit another one.
     */
    private function renderLanguageNotice(): void
    {
        $multilingual = multilingual();

        if ($multilingual === null) {
            return;
        }

        $default = $multilingual->defaultLanguage();
        $languages = $multilingual->languages();
        $selected = $multilingual->adminLanguage();
        $name = $languages[$selected ?? $default] ?? $selected ?? $default;

        if ($selected === null) {
            printf(
                '<div class="notice notice-warning inline"><p>%s</p></div>',
                esc_html(sprintf(
                    'All languages are selected: the translatable fields show the %s values. Select a language in the admin bar to edit its values.',
                    $name,
                )),
            );

            return;
        }

        printf(
            '<div class="notice notice-info inline"><p>%s</p></div>',
            esc_html(sprintf('The translatable fields show the %s values.', $name)),
        );
    }
}
