<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Inspect;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Studiometa\Foehn\Attributes\AsContextProvider;
use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Discovery\ContextProviderDiscovery;
use Studiometa\Foehn\Discovery\MenuDiscovery;
use Studiometa\Foehn\Discovery\TemplateControllerDiscovery;
use Studiometa\Foehn\Discovery\TemplateControllerMap;
use Timber\Timber;

/**
 * What renders each template, and the Twig variables it receives.
 *
 * Two names meet here and must not be confused. A controller claims WordPress
 * template names (`single-product`, `archive-*`); a context provider matches view
 * names, the Twig file a `render()` call names (`pages/single`). Which views a
 * controller renders is decided at runtime, so the two are reported side by side:
 * `templates` per WordPress template, `views` per Twig file.
 */
final readonly class Templates
{
    public function __construct(
        private DiscoveredItems $items,
    ) {}

    /**
     * Each WordPress template a controller claims, the controller that renders it and
     * the ones it overrides.
     *
     * What repeats from row to row is given once: `base`, the keys every controller
     * receives, and `controllers`, the priority and the declared keys of each class.
     * A row keeps only `request`, the keys `Timber::context()` adds for that template.
     *
     * @return array{
     *     base: list<string>,
     *     controllers: array<class-string, array{priority: int, keys: list<string>, partial: bool}>,
     *     templates: list<array<string, mixed>>
     * }
     */
    public function templates(): array
    {
        $items = $this->items->of(TemplateControllerDiscovery::class);
        $controllers = [];

        foreach ($items as $item) {
            /** @var AsTemplateController $attribute */
            $attribute = $item['attribute'];
            /** @var class-string $className */
            $className = $item['className'];

            $controllers[$className] = ['priority' => $attribute->priority, ...ContextKeys::declaredBy($className)];
        }

        ksort($controllers);
        $rows = [];

        foreach (TemplateControllerMap::fromItems($items)->claims() as $claim) {
            $rows[] = [
                'template' => $claim['template'],
                'controller' => $claim['controller']['className'],
                'overridden' => $claim['overridden'],
                'request' => ContextKeys::request($claim['template']),
            ];
        }

        return [
            'base' => ContextKeys::render($this->hasMenus()),
            'controllers' => $controllers,
            'templates' => Rows::sortedBy($rows, 'template'),
        ];
    }

    /**
     * Each Twig file of the theme, and the context providers its view name matches.
     *
     * What repeats from row to row is given once: `base`, the keys every view
     * receives, `providers`, the keys each matching provider adds, and `everyView`,
     * the providers on `*`, which match every view name. A row keeps only the other
     * providers, in the order they run.
     *
     * `everyView` is read from the patterns, not from the files: a provider on
     * `pages/*` that happens to match every file listed would not reach a view added
     * in `components/`.
     *
     * @return array{
     *     base: list<string>,
     *     providers: array<class-string, array{priority: int, keys: list<string>, partial: bool}>,
     *     everyView: list<class-string>,
     *     views: list<array<string, mixed>>
     * }
     */
    public function views(): array
    {
        $providers = $this->providers();
        $global = array_filter($providers, static fn(array $provider): bool => in_array(
            '*',
            $provider['templates'],
            true,
        ));
        usort($global, static fn(array $a, array $b): int => $a['priority'] <=> $b['priority']);
        $everyView = array_column($global, 'class');
        $used = $everyView;
        $rows = [];

        foreach ($this->twigFiles() as $view => $file) {
            // PHP keys the view of a root `404.twig` as the int 404.
            $view = (string) $view;
            $matching = array_column(self::matchingProviders($view, $providers), 'class');
            $used = [...$used, ...$matching];
            $rows[] = [
                'view' => $view,
                'file' => $file,
                'providers' => array_values(array_diff($matching, $everyView)),
            ];
        }

        $byClass = [];

        foreach ($providers as $provider) {
            if (!in_array($provider['class'], $used, true)) {
                continue;
            }

            $byClass[$provider['class']] = [
                'priority' => $provider['priority'],
                'keys' => $provider['keys'],
                'partial' => $provider['partial'],
            ];
        }

        ksort($byClass);

        return [
            'base' => ContextKeys::render($this->hasMenus()),
            'providers' => $byClass,
            'everyView' => $everyView,
            'views' => Rows::sortedBy($rows, 'view'),
        ];
    }

    /**
     * Context providers, the view names they match, and the keys each one adds.
     *
     * @return list<array<string, mixed>>
     */
    public function contextProviders(): array
    {
        $rows = array_map(static fn(array $provider): array => [
            'class' => $provider['class'],
            'templates' => $provider['templates'],
            'priority' => $provider['priority'],
            'keys' => $provider['keys'],
            'partial' => $provider['partial'],
        ], $this->providers());

        return Rows::sortedBy($rows, 'class');
    }

    /**
     * Every context provider in discovery order, which is the order the registry
     * receives them in.
     *
     * @return list<array{class: class-string, templates: list<string>, priority: int, keys: list<string>, partial: bool}>
     */
    private function providers(): array
    {
        $providers = [];

        foreach ($this->items->of(ContextProviderDiscovery::class) as $item) {
            /** @var AsContextProvider $attribute */
            $attribute = $item['attribute'];
            /** @var class-string $className */
            $className = $item['className'];

            $providers[] = [
                'class' => $className,
                'templates' => array_values($attribute->getTemplates()),
                'priority' => $attribute->priority,
                ...ContextKeys::declaredBy($className),
            ];
        }

        return $providers;
    }

    /**
     * The providers that run for a view name, in the order ContextProviderRegistry
     * runs them: exact patterns, then wildcard patterns in the order they were first
     * registered, then sorted by priority without reordering ties.
     *
     * @param list<array{class: class-string, templates: list<string>, priority: int, keys: list<string>, partial: bool}> $providers
     * @return list<array{class: class-string, templates: list<string>, priority: int, keys: list<string>, partial: bool}>
     */
    private static function matchingProviders(string $view, array $providers): array
    {
        $exact = [];
        $wildcards = [];

        foreach ($providers as $provider) {
            foreach ($provider['templates'] as $pattern) {
                if (str_contains($pattern, '*')) {
                    $wildcards[$pattern][] = $provider;

                    continue;
                }

                if ($pattern === $view) {
                    $exact[] = $provider;
                }
            }
        }

        $matching = $exact;

        foreach ($wildcards as $pattern => $entries) {
            if (!TemplateControllerMap::matches($view, $pattern)) {
                continue;
            }

            $matching = [...$matching, ...$entries];
        }

        usort($matching, static fn(array $a, array $b): int => $a['priority'] <=> $b['priority']);

        return $matching;
    }

    /**
     * The Twig files Timber can load, by view name, with their path relative to the
     * theme directory that holds them.
     *
     * The child theme comes first, as in Timber's loader, so a view it overrides is
     * listed once, with the child's file.
     *
     * @return array<string, string>
     */
    private function twigFiles(): array
    {
        /** @var string|array<mixed> $dirname */
        $dirname = Timber::$dirname;
        $roots = array_unique([get_stylesheet_directory(), get_template_directory()]);
        $files = [];

        foreach ($roots as $root) {
            foreach ((array) $dirname as $directory) {
                // Timber also takes `[namespace => directories]`, which Føhn does not
                // set; its views are named `@namespace/…` and are not listed.
                if (!is_string($directory)) {
                    continue;
                }

                $base = rtrim($root, '/') . '/' . trim($directory, '/');

                if (!is_dir($base)) {
                    continue;
                }

                $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
                    $base,
                    FilesystemIterator::SKIP_DOTS,
                ));

                /** @var SplFileInfo $file */
                foreach ($iterator as $file) {
                    if ($file->getExtension() !== 'twig') {
                        continue;
                    }

                    $relative = substr($file->getPathname(), strlen($base) + 1);
                    $view = substr($relative, 0, -strlen('.twig'));

                    $files[$view] ??= trim($directory, '/') . '/' . $relative;
                }
            }
        }

        return $files;
    }

    private function hasMenus(): bool
    {
        return $this->items->of(MenuDiscovery::class) !== [];
    }
}
