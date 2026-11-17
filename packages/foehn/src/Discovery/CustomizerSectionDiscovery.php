<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use Studiometa\Foehn\Attributes\AsCustomizerSection;
use Studiometa\Foehn\Attributes\AsDiscovery;
use Studiometa\Foehn\Contracts\SettingsInterface;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Studiometa\Foehn\Settings\Setting;
use Studiometa\Foehn\Settings\Settings;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;
use WP_Customize_Manager;

/**
 * Discovers classes marked with #[AsCustomizerSection] and adds their settings
 * to the Customizer.
 *
 * `register_setting()` runs on `init`, as for a settings page, so the sanitiser
 * also runs for `wp option update`. The section and its controls are added on
 * `customize_register`, after core adds its own sections, so a section id that
 * already exists gets the fields instead of a second section.
 */
#[AsDiscovery(phase: DiscoveryPhase::Main)]
final class CustomizerSectionDiscovery implements Discovery
{
    use IsWpDiscovery;

    public function __construct(
        private readonly DiscoveryProblems $problems,
    ) {}

    /**
     * Discover Customizer sections.
     */
    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        if (!$this->isConcrete($class)) {
            return;
        }

        $attribute = $class->getAttribute(AsCustomizerSection::class);

        if ($attribute === null) {
            return;
        }

        if (!$class->implements(SettingsInterface::class)) {
            $this->problems->report(
                $location,
                $class->getName(),
                '#[AsCustomizerSection]',
                sprintf(
                    '%s has #[AsCustomizerSection] but does not implement %s, so the Customizer section is not registered.',
                    $class->getName(),
                    SettingsInterface::class,
                ),
                sprintf('Declare %s with `implements \\%s`.', $class->getName(), SettingsInterface::class),
            );

            return;
        }

        $this->addItem($location, [
            'attribute' => $attribute,
            'className' => $class->getName(),
            'settings' => $class->getName()::settings(),
        ]);
    }

    /**
     * Apply discovered Customizer sections.
     */
    public function apply(): void
    {
        $items = iterator_to_array($this->getItems());

        if ($items === []) {
            return;
        }

        foreach ($items as $item) {
            /** @var AsCustomizerSection $attribute */
            $attribute = $item['attribute'];

            Settings::register($attribute->id, $item['settings'], $item['className']);
        }

        // Core adds its sections on `customize_register` at the default priority.
        add_action(
            'customize_register',
            function (WP_Customize_Manager $manager) use ($items): void {
                foreach ($items as $item) {
                    $this->registerSection($manager, $item);
                }
            },
            20,
        );
    }

    /**
     * Add the section, unless it exists, and a setting and a control for each
     * setting.
     *
     * @param array<string, mixed> $item
     */
    private function registerSection(WP_Customize_Manager $manager, array $item): void
    {
        /** @var AsCustomizerSection $attribute */
        $attribute = $item['attribute'];
        /** @var class-string $className */
        $className = $item['className'];
        /** @var array<string, Setting> $settings */
        $settings = $item['settings'];

        if ($manager->get_section($attribute->id) === null) {
            $manager->add_section($attribute->id, [
                'title' => $attribute->title,
                'description' => $attribute->description,
                'panel' => $attribute->panel ?? '',
                'priority' => $attribute->priority,
                'capability' => $attribute->capability,
            ]);
        }

        foreach ($settings as $name => $setting) {
            $sanitizer = $setting->sanitizer($className);

            $manager->add_setting($name, [
                // Stored with update_option(), as a settings page stores it, not
                // as a theme mod: Settings::get() and `wp option` read it.
                'type' => 'option',
                'default' => $setting->default,
                'capability' => $attribute->capability,
                // The Customizer passes the setting object as a second argument,
                // which `esc_url_raw()` would take for its list of protocols.
                'sanitize_callback' => static fn(mixed $value): mixed => $sanitizer($value),
            ]);

            $manager->add_control($name, [
                'label' => $setting->label($name),
                'description' => $setting->description,
                'section' => $attribute->id,
                // The Setting controls are Customizer control types too. The
                // default control draws `tel` as an input of that type.
                'type' => $setting->control,
                'choices' => $setting->options,
            ]);
        }
    }
}
