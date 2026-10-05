<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use Studiometa\Foehn\Attributes\AsBlockCategory;
use Studiometa\Foehn\Attributes\AsDiscovery;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;

/**
 * Discovers #[AsBlockCategory] attributes and adds the categories to the block
 * editor through the `block_categories_all` filter.
 *
 * The categories go before the ones WordPress and plugins provide, so a theme's
 * own blocks lead the inserter. A slug that is already in the list is skipped:
 * a core slug such as `theme` keeps its core title, and when two classes declare
 * the same slug, the class whose name sorts first wins.
 */
#[AsDiscovery(phase: DiscoveryPhase::Main)]
final class BlockCategoryDiscovery implements Discovery
{
    use IsWpDiscovery;

    /**
     * Discover block category attributes on a class.
     */
    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        foreach ($class->getReflection()->getAttributes(AsBlockCategory::class) as $reflected) {
            $this->addItem($location, [
                'attribute' => $reflected->newInstance(),
                'className' => $class->getName(),
            ]);
        }
    }

    /**
     * Apply discovered block categories by hooking them into the editor.
     */
    public function apply(): void
    {
        $categories = $this->categories();

        if ($categories === []) {
            return;
        }

        add_filter('block_categories_all', static fn(array $existing): array => self::merge($categories, $existing));
    }

    /**
     * The discovered categories, ordered by class name.
     *
     * Scan order is filesystem order, and a restored cache replays the order it
     * was written in, so neither is an order the inserter can show. The sort is
     * stable: categories declared on one class keep their declaration order.
     *
     * @return list<array{slug: string, title: string, icon: ?string}>
     */
    private function categories(): array
    {
        /** @var list<array{attribute: AsBlockCategory, className: class-string}> $items */
        $items = iterator_to_array($this->getItems(), false);

        usort($items, static fn(array $a, array $b): int => strcmp($a['className'], $b['className']));

        $categories = [];

        foreach ($items as $item) {
            $categories[] = [
                'slug' => $item['attribute']->slug,
                'title' => $item['attribute']->title,
                'icon' => $item['attribute']->icon,
            ];
        }

        return $categories;
    }

    /**
     * Put the discovered categories before the existing ones, skipping any slug
     * that is already in the list.
     *
     * @param list<array{slug: string, title: string, icon: ?string}> $categories
     * @param array<array-key, mixed> $existing
     * @return list<mixed>
     */
    private static function merge(array $categories, array $existing): array
    {
        $taken = [];

        foreach ($existing as $category) {
            $slug = is_array($category) ? $category['slug'] ?? null : null;

            if (!is_string($slug)) {
                continue;
            }

            $taken[$slug] = true;
        }

        $added = [];

        foreach ($categories as $category) {
            if (array_key_exists($category['slug'], $taken)) {
                continue;
            }

            $taken[$category['slug']] = true;
            $added[] = $category;
        }

        return [...$added, ...array_values($existing)];
    }
}
