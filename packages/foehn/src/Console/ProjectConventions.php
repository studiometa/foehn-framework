<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console;

use ReflectionClass;
use Studiometa\Foehn\Discovery\DiscoveryRunner;
use Studiometa\Foehn\Discovery\PostTypeDiscovery;
use Studiometa\Foehn\Discovery\TemplateControllerDiscovery;
use Studiometa\Foehn\Discovery\TemplateControllerMap;
use Studiometa\Foehn\Inspect\DiscoveredItems;
use Studiometa\Foehn\Inspect\Templates;
use Timber\Timber;

/**
 * How the project already does what a `make:` command is about to do.
 *
 * A generated class goes where the project keeps the classes of the same kind, in
 * their namespace and under their parent class, and a generated post type is given
 * the Twig files the project's controllers render. Both are read from what discovery
 * collected and from the theme's Twig files, so a command follows the project
 * instead of a layout fixed in the framework. When the project declares nothing of
 * the kind, the command's own defaults stand.
 */
final readonly class ProjectConventions
{
    private DiscoveredItems $items;

    public function __construct(
        private string $appPath,
        private DiscoveryRunner $runner,
    ) {
        $this->items = new DiscoveredItems($runner);
    }

    /**
     * Put a request next to the project's classes of the same kind.
     *
     * The directory is the one that holds most of them. When each one has a directory
     * of its own (`Blocks/Hero/HeroBlock.php`), it is the directory that holds them
     * all. The parent class is the one most of them extend, and replaces the stub's
     * only when the stub has one: a controller stub stays a class that extends nothing.
     */
    public function place(GenerationRequest $request): GenerationRequest
    {
        if ($request->discovery === null) {
            return $request;
        }

        $classes = $this->projectClasses($request->discovery);

        if ($classes === []) {
            return $request;
        }

        $byDirectory = [];

        foreach ($classes as $class) {
            $byDirectory[$class['directory']][] = $class;
        }

        uksort(
            $byDirectory,
            static fn(string $a, string $b): int => (
                [count($byDirectory[$b]), $a] <=> [
                    count($byDirectory[$a]),
                    $b,
                ]
            ),
        );

        $directory = array_key_first($byDirectory);
        $chosen = $byDirectory[$directory];
        $namespace = $chosen[0]['namespace'];
        $common = self::commonDirectory(array_keys($byDirectory));

        if (count($chosen) === 1 && count($byDirectory) > 1 && $common !== '') {
            $depth = self::depth($directory) - self::depth($common);
            $segments = explode('\\', $namespace);
            $namespace = implode('\\', array_slice($segments, 0, max(1, count($segments) - $depth)));
            $directory = $common;
            $chosen = $classes;
        }

        $parent = $request->parent;

        if (get_parent_class($request->stub) !== false) {
            $parent = self::mostCommon(array_values(array_filter(array_column($chosen, 'parent'))))[0] ?? $parent;
        }

        return $request->placedIn($directory, $namespace, $parent);
    }

    /**
     * The view a WordPress template is rendered with, and the controller that renders it.
     *
     * The project's controllers decide the view at runtime, so it is read from the
     * theme's Twig files: the new view goes where the views of the other post types
     * are (`pages/single-project.twig`), or else where the generic one is
     * (`pages/single.twig`).
     *
     * @param 'single'|'archive' $kind
     */
    public function view(string $kind, string $postType): ViewHint
    {
        $template = "{$kind}-{$postType}";
        $controller = TemplateControllerMap::fromItems($this->items->of(TemplateControllerDiscovery::class))->find(
            $template,
        );

        $files = new Templates($this->items)->twigFiles();
        $perType = array_map(static fn(string $type): string => "{$kind}-{$type}", $this->postTypes());
        $directories = ['perType' => [], 'generic' => []];
        $roots = [];

        foreach ($files as $name => $file) {
            // PHP makes a numeric key, the view of a root `404.twig`, an int.
            $name = (string) $name;
            $directory = dirname($name) === '.' ? '' : dirname($name);
            // The Timber directory that holds the file, e.g. 'templates/'.
            $roots[$directory] ??= substr($file, 0, -strlen($name . '.twig'));

            if (in_array(basename($name), $perType, true)) {
                $directories['perType'][] = $directory;
            }

            if (basename($name) === $kind) {
                $directories['generic'][] = $directory;
            }
        }

        // A view of another post type is the closest model; the generic view the
        // controllers fall back to comes next.
        $directory = self::mostCommon($directories['perType'])[0] ?? self::mostCommon($directories['generic'])[0] ?? '';
        $view = ltrim("{$directory}/{$template}", '/');
        $root = $roots[$directory] ?? rtrim(self::timberDirectory(), '/') . '/';

        return new ViewHint(
            template: $template,
            view: $view,
            file: $root . $view . '.twig',
            controller: $controller['className'] ?? null,
            exists: array_key_exists($view, $files),
            directory: $directory,
        );
    }

    /**
     * The project's own classes a discovery collected: those under the app path, not
     * the framework's or a package's.
     *
     * @return list<array{directory: string, namespace: string, parent: class-string|null}>
     */
    private function projectClasses(string $discovery): array
    {
        $root = realpath($this->appPath);

        if ($root === false) {
            return [];
        }

        $classes = [];

        foreach ($this->items->of($discovery) as $item) {
            $className = $item['className'] ?? null;

            if (!is_string($className) || !class_exists($className) || array_key_exists($className, $classes)) {
                continue;
            }

            $reflection = new ReflectionClass($className);
            $file = $reflection->getFileName();

            if ($file === false || !str_starts_with($file, $root . '/')) {
                continue;
            }

            $parent = $reflection->getParentClass();

            $classes[$className] = [
                'directory' => trim(substr(dirname($file), strlen($root)), '/'),
                'namespace' => $reflection->getNamespaceName(),
                'parent' => $parent === false ? null : $parent->getName(),
            ];
        }

        return array_values($classes);
    }

    /**
     * The post types the project declares.
     *
     * @return list<string>
     */
    private function postTypes(): array
    {
        $names = [];

        foreach ($this->items->of(PostTypeDiscovery::class) as $item) {
            $attribute = $item['attribute'] ?? null;

            if (is_object($attribute) && property_exists($attribute, 'name') && is_string($attribute->name)) {
                $names[] = $attribute->name;
            }
        }

        return $names;
    }

    /**
     * The values of a list, the most frequent first; ties in alphabetical order.
     *
     * @template T of string
     * @param array<T> $values
     * @return list<T>
     */
    private static function mostCommon(array $values): array
    {
        $counts = array_count_values($values);
        uksort($counts, static fn(string $a, string $b): int => [$counts[$b], $a] <=> [$counts[$a], $b]);

        return array_keys($counts);
    }

    /**
     * The deepest directory that holds every one of a list, relative to the app path.
     *
     * @param array<array-key, string> $directories
     */
    private static function commonDirectory(array $directories): string
    {
        $common = explode('/', (string) reset($directories));

        foreach ($directories as $directory) {
            $segments = explode('/', $directory);
            $length = 0;

            while ($length < count($common) && ($segments[$length] ?? null) === $common[$length]) {
                $length++;
            }

            $common = array_slice($common, 0, $length);
        }

        return implode('/', $common);
    }

    /**
     * How many directories deep a path relative to the app path is.
     */
    private static function depth(string $directory): int
    {
        return $directory === '' ? 0 : substr_count($directory, '/') + 1;
    }

    /**
     * The directory Timber loads views from, relative to the theme.
     */
    private static function timberDirectory(): string
    {
        /** @var string|array<mixed> $dirname */
        $dirname = Timber::$dirname;

        foreach ((array) $dirname as $directory) {
            if (is_string($directory)) {
                return trim($directory, '/');
            }
        }

        return 'templates';
    }
}
