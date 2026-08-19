<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use InvalidArgumentException;
use Studiometa\Foehn\Attributes\AsDiscovery;
use Studiometa\Foehn\Attributes\AsSettingsPage;
use Studiometa\Foehn\Contracts\SettingsFormInterface;
use Studiometa\Foehn\Contracts\SettingsPageInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Studiometa\Foehn\Settings\Setting;
use Studiometa\Foehn\Settings\Settings;
use Tempest\Container\Container;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;

/**
 * Discovers classes marked with #[AsSettingsPage] and registers their settings
 * and their admin page.
 *
 * Two hooks, not one. `register_setting()` belongs on `init`, which is this
 * phase; the menu entry belongs on `admin_menu`, which apply() hooks itself —
 * one discovery wanting a different hook is not a reason for a fourth phase.
 */
#[AsDiscovery(phase: DiscoveryPhase::Main)]
final class SettingsPageDiscovery implements Discovery
{
    use IsWpDiscovery;

    public function __construct(
        private readonly Container $container,
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
        if (!$class->implements(SettingsPageInterface::class)) {
            throw new InvalidArgumentException(sprintf(
                'Class %s must implement %s to use #[AsSettingsPage].',
                $class->getName(),
                SettingsPageInterface::class,
            ));
        }

        $buildsItsOwnForm = $class->implements(SettingsFormInterface::class);

        // Without one or the other there is nothing between the heading and the
        // submit button, and the page renders as an empty form.
        if ($attribute->template === null && !$buildsItsOwnForm) {
            throw new InvalidArgumentException(sprintf(
                'Class %s declares no form: name a `template` on #[AsSettingsPage], or implement %s.',
                $class->getName(),
                SettingsFormInterface::class,
            ));
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
            'buildsItsOwnForm' => $buildsItsOwnForm,
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
            $this->registerSettings($item);
        }

        $container = $this->container;

        add_action('admin_menu', function () use ($items, $container): void {
            foreach ($items as $item) {
                $this->registerPage($item, $container);
            }
        });
    }

    /**
     * Register each of a page's settings with WordPress.
     *
     * @param array<string, mixed> $item
     */
    private function registerSettings(array $item): void
    {
        /** @var AsSettingsPage $attribute */
        $attribute = $item['attribute'];
        /** @var class-string $className */
        $className = $item['className'];
        /** @var array<string, Setting> $settings */
        $settings = $item['settings'];

        foreach ($settings as $name => $setting) {
            register_setting($attribute->slug, $name, [
                'type' => $setting->type,
                'default' => $setting->default,
                'description' => $setting->description,
                'show_in_rest' => $setting->showInRest,
                'sanitize_callback' => $setting->sanitizer($className),
            ]);
        }

        // So Settings::get() can answer with the declared default and type
        // rather than with whatever get_option() found.
        Settings::declare($settings);
    }

    /**
     * Add the page to the admin menu.
     *
     * @param array<string, mixed> $item
     */
    private function registerPage(array $item, Container $container): void
    {
        /** @var AsSettingsPage $attribute */
        $attribute = $item['attribute'];

        $render = function () use ($attribute, $container, $item): void {
            $this->renderPage($attribute, $this->form($attribute, $container, $item));
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
     * The body of the form, from whichever of the two places supplies it.
     *
     * The page wins when it implements SettingsFormInterface: a class that went
     * to the trouble of building its form means it, and a template left on the
     * attribute is likely to be the one it renders itself.
     *
     * @param array<string, mixed> $item
     */
    private function form(AsSettingsPage $attribute, Container $container, array $item): string
    {
        /** @var class-string $className */
        $className = $item['className'];

        if ($item['buildsItsOwnForm']) {
            /** @var SettingsFormInterface $page */
            $page = $container->get($className);

            return $page->form();
        }

        /** @var ViewEngineInterface $view */
        $view = $container->get(ViewEngineInterface::class);

        return $view->render((string) $attribute->template, [
            'page' => ['slug' => $attribute->slug, 'title' => $attribute->title],
            // The current values, typed as the page declared them, so a template
            // reads `settings.show_banner` and gets a boolean.
            'settings' => self::values($item['settings']),
        ]);
    }

    /**
     * The current value of each of a page's settings.
     *
     * @param array<string, Setting> $settings
     * @return array<string, mixed>
     */
    private static function values(array $settings): array
    {
        $values = [];

        foreach (array_keys($settings) as $name) {
            $values[$name] = Settings::get($name);
        }

        return $values;
    }

    /**
     * The page shell WordPress expects, around the page's own form fields.
     *
     * `settings_fields()` prints the nonce and the option group without which
     * `options.php` rejects the save; the page cannot forget it because it never
     * writes it.
     */
    private function renderPage(AsSettingsPage $attribute, string $form): void
    {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html($attribute->title) . '</h1>';

        settings_errors();

        echo '<form action="options.php" method="post">';

        settings_fields($attribute->slug);
        do_settings_sections($attribute->slug);

        echo $form;

        submit_button();

        echo '</form>';
        echo '</div>';
    }
}
