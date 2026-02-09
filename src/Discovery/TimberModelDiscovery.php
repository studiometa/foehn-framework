<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use InvalidArgumentException;
use ReflectionClass;
use Studiometa\Foehn\Attributes\AsTimberModel;
use Studiometa\Foehn\Discovery\Concerns\CacheableDiscovery;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Timber\Post;
use Timber\Term;

/**
 * Discovers classes marked with #[AsTimberModel] attribute
 * and registers them in Timber's class map without registering
 * a post type or taxonomy.
 */
final class TimberModelDiscovery implements WpDiscovery
{
    use IsWpDiscovery;
    use CacheableDiscovery;

    /**
     * Discover timber model attributes on classes.
     *
     * @param DiscoveryLocation $location
     * @param ReflectionClass<object> $class
     */
    public function discover(DiscoveryLocation $location, ReflectionClass $class): void
    {
        $attributes = $class->getAttributes(AsTimberModel::class);

        if ($attributes === []) {
            return;
        }

        $isPost = $class->isSubclassOf(Post::class);
        $isTerm = $class->isSubclassOf(Term::class);

        if (!$isPost && !$isTerm) {
            throw new InvalidArgumentException(sprintf(
                'Class %s must extend %s or %s to use #[AsTimberModel]',
                $class->getName(),
                Post::class,
                Term::class,
            ));
        }

        $attribute = $attributes[0]->newInstance();

        $this->addItem($location, [
            'attribute' => $attribute,
            'className' => $class->getName(),
            'type' => $isPost ? 'post' : 'term',
        ]);
    }

    /**
     * Apply discovered timber models by registering class maps.
     */
    public function apply(): void
    {
        foreach ($this->getItems() as $item) {
            $this->registerTimberModel($item);
        }
    }

    /**
     * Register a single Timber class map entry.
     *
     * @param array<string, mixed> $item
     */
    private function registerTimberModel(array $item): void
    {
        $name = $item['name'] ?? $item['attribute']->name;
        $className = $item['className'];
        $type = $item['type'];

        $hook = $type === 'post' ? 'timber/post/classmap' : 'timber/term/classmap';

        add_filter($hook, static function (array $map) use ($name, $className): array {
            $map[$name] = $className;

            return $map;
        });
    }

    /**
     * Convert a discovered item to a cacheable format.
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    protected function itemToCacheable(array $item): array
    {
        /** @var AsTimberModel $attribute */
        $attribute = $item['attribute'];

        return [
            'name' => $attribute->name,
            'className' => $item['className'],
            'type' => $item['type'],
        ];
    }
}
