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
use Studiometa\Foehn\Discovery\TemplateControllerDiscovery;

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
            ];
        }

        return Rows::sorted($rows);
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
