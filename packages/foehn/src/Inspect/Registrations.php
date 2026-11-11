<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Inspect;

use Studiometa\Foehn\Attributes\AsBlock;
use Studiometa\Foehn\Attributes\AsBlockBinding;
use Studiometa\Foehn\Attributes\AsBlockPattern;
use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Attributes\AsContextProvider;
use Studiometa\Foehn\Attributes\AsImageSize;
use Studiometa\Foehn\Attributes\AsMenu;
use Studiometa\Foehn\Attributes\AsRestRoute;
use Studiometa\Foehn\Attributes\AsRewriteRule;
use Studiometa\Foehn\Attributes\AsSettingsPage;
use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Discovery\BlockBindingDiscovery;
use Studiometa\Foehn\Discovery\BlockDiscovery;
use Studiometa\Foehn\Discovery\BlockPatternDiscovery;
use Studiometa\Foehn\Discovery\CliCommandDiscovery;
use Studiometa\Foehn\Discovery\ContextProviderDiscovery;
use Studiometa\Foehn\Discovery\ImageSizeDiscovery;
use Studiometa\Foehn\Discovery\MenuDiscovery;
use Studiometa\Foehn\Discovery\RestRouteDiscovery;
use Studiometa\Foehn\Discovery\RewriteRuleDiscovery;
use Studiometa\Foehn\Discovery\SettingsPageDiscovery;
use Studiometa\Foehn\Discovery\TemplateControllerDiscovery;
use Studiometa\Foehn\Settings\Setting;

/**
 * What one attribute registers on its own: one row per discovered item.
 */
final readonly class Registrations
{
    public function __construct(
        private DiscoveredItems $items,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function blocks(): array
    {
        $rows = [];

        foreach ($this->items->of(BlockDiscovery::class) as $item) {
            /** @var AsBlock $attribute */
            $attribute = $item['attribute'];

            $rows[] = [
                'name' => $attribute->name,
                'title' => $attribute->title,
                'class' => $item['className'],
                'category' => $attribute->category,
                'description' => $attribute->description,
                'parent' => $attribute->parent,
                'interactivity' => $attribute->interactivity,
                'attributes' => self::blockAttributes($item['className']),
            ];
        }

        return Rows::sorted($rows);
    }

    /**
     * What a block stores in its comment delimiter, as WordPress validates it.
     *
     * The editor-only keys (control, label, ...) are left out: they say how the
     * sidebar renders an attribute, not what a post's content may hold.
     *
     * @param class-string $className
     * @return list<array{name: string, type: mixed, default: mixed, enum: mixed}>
     */
    private static function blockAttributes(string $className): array
    {
        if (!method_exists($className, 'attributes')) {
            return [];
        }

        /** @var array<string, array<string, mixed>> $schema */
        $schema = $className::attributes();
        $attributes = [];

        foreach ($schema as $name => $definition) {
            $attributes[] = [
                'name' => $name,
                'type' => $definition['type'] ?? null,
                'default' => $definition['default'] ?? null,
                'enum' => $definition['enum'] ?? null,
            ];
        }

        return $attributes;
    }

    /**
     * Settings pages and the options each one stores.
     *
     * Every setting is saved through a sanitizer: the one the page declares, or the
     * one its type implies. `register_setting()` runs in the main discovery phase, on
     * `init`, which WordPress fires under WP-CLI too, so `wp option update` saves
     * through the same sanitizer as the admin form.
     *
     * @return list<array<string, mixed>>
     */
    public function settings(): array
    {
        $rows = [];

        foreach ($this->items->of(SettingsPageDiscovery::class) as $item) {
            /** @var AsSettingsPage $attribute */
            $attribute = $item['attribute'];
            /** @var class-string $className */
            $className = $item['className'];
            /** @var array<string, Setting> $settings */
            $settings = $item['settings'];
            $options = [];

            foreach ($settings as $name => $setting) {
                $sanitizer = $setting->sanitizer($className);

                $options[] = [
                    'option' => $name,
                    'type' => $setting->type,
                    'default' => $setting->default,
                    'sanitize' => is_array($sanitizer) ? implode('::', $sanitizer) : $sanitizer,
                    'sanitizeDeclared' => $setting->sanitize !== null,
                    'sanitizeOnWpCli' => true,
                    'showInRest' => $setting->showInRest,
                ];
            }

            $rows[] = [
                'page' => $attribute->slug,
                'title' => $attribute->title,
                'class' => $className,
                'optionGroup' => $attribute->slug,
                'settings' => $options,
            ];
        }

        return Rows::sortedBy($rows, 'page');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function patterns(): array
    {
        $rows = [];

        foreach ($this->items->of(BlockPatternDiscovery::class) as $item) {
            /** @var AsBlockPattern $attribute */
            $attribute = $item['attribute'];

            $rows[] = [
                'name' => $attribute->name,
                'title' => $attribute->title,
                'class' => $item['className'],
                'template' => $attribute->getTemplatePath(),
                'categories' => $attribute->categories,
            ];
        }

        return Rows::sorted($rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function bindings(): array
    {
        $rows = [];

        foreach ($this->items->of(BlockBindingDiscovery::class) as $item) {
            /** @var AsBlockBinding $attribute */
            $attribute = $item['attribute'];

            $rows[] = [
                'name' => $attribute->name,
                'label' => $attribute->label,
                'class' => $item['className'],
                'usesContext' => $attribute->usesContext,
            ];
        }

        return Rows::sorted($rows);
    }

    /**
     * Template controllers and the WordPress templates each one handles.
     *
     * @return list<array<string, mixed>>
     */
    public function templates(): array
    {
        $rows = [];

        foreach ($this->items->of(TemplateControllerDiscovery::class) as $item) {
            /** @var AsTemplateController $attribute */
            $attribute = $item['attribute'];

            $rows[] = [
                'class' => $item['className'],
                'templates' => $attribute->getTemplates(),
                'priority' => $attribute->priority,
            ];
        }

        return Rows::sortedBy($rows, 'class');
    }

    /**
     * Context providers and the view names they match.
     *
     * @return list<array<string, mixed>>
     */
    public function contextProviders(): array
    {
        $rows = [];

        foreach ($this->items->of(ContextProviderDiscovery::class) as $item) {
            /** @var AsContextProvider $attribute */
            $attribute = $item['attribute'];

            $rows[] = [
                'class' => $item['className'],
                'templates' => $attribute->getTemplates(),
                'priority' => $attribute->priority,
            ];
        }

        return Rows::sortedBy($rows, 'class');
    }

    /**
     * Menu locations, with the Twig variable each assigned menu is exposed as.
     *
     * @return list<array<string, mixed>>
     */
    public function menus(): array
    {
        $rows = [];

        foreach ($this->items->of(MenuDiscovery::class) as $item) {
            /** @var AsMenu $attribute */
            $attribute = $item['attribute'];

            $rows[] = [
                'location' => $attribute->location,
                'description' => $attribute->description,
                'class' => $item['className'],
                'contextKey' => 'menus.' . $attribute->location,
            ];
        }

        return Rows::sortedBy($rows, 'location');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function imageSizes(): array
    {
        $rows = [];

        foreach ($this->items->of(ImageSizeDiscovery::class) as $item) {
            /** @var AsImageSize $attribute */
            $attribute = $item['attribute'];

            $rows[] = [
                'name' => ImageSizeDiscovery::sizeName($attribute, $item['className']),
                'width' => $attribute->width,
                'height' => $attribute->height,
                'crop' => $attribute->crop,
                'class' => $item['className'],
            ];
        }

        return Rows::sorted($rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rewriteRules(): array
    {
        $rows = [];

        foreach ($this->items->of(RewriteRuleDiscovery::class) as $item) {
            /** @var AsRewriteRule $attribute */
            $attribute = $item['attribute'];

            $rows[] = [
                'regex' => $attribute->regex,
                'query' => $attribute->query,
                'queryVars' => $attribute->queryVars,
                'after' => $attribute->after,
                'class' => $item['className'],
                'handles' => $item['handles'],
            ];
        }

        return Rows::sortedBy($rows, 'regex');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function restRoutes(): array
    {
        $rows = [];

        foreach ($this->items->of(RestRouteDiscovery::class) as $item) {
            /** @var AsRestRoute $attribute */
            $attribute = $item['attribute'];

            $rows[] = [
                'path' => '/' . trim($attribute->namespace, '/') . '/' . ltrim($attribute->route, '/'),
                'method' => strtoupper($attribute->method),
                'permission' => $attribute->permission,
                'handler' => $item['className'] . '::' . $item['methodName'],
            ];
        }

        usort($rows, static fn(array $a, array $b): int => [$a['path'], $a['method']] <=> [$b['path'], $b['method']]);

        return $rows;
    }

    /**
     * Commands registered under `wp foehn`, the framework's own included.
     *
     * @return list<array<string, mixed>>
     */
    public function cliCommands(): array
    {
        $rows = [];

        foreach ($this->items->of(CliCommandDiscovery::class) as $item) {
            /** @var AsCliCommand $attribute */
            $attribute = $item['attribute'];

            $rows[] = [
                'command' => 'foehn ' . $attribute->name,
                'description' => $attribute->description,
                'class' => $item['className'],
            ];
        }

        return Rows::sortedBy($rows, 'command');
    }
}
