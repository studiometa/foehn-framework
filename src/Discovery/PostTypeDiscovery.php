<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use InvalidArgumentException;
use ReflectionClass;
use Studiometa\Foehn\Attributes\AsPostType;
use Studiometa\Foehn\Contracts\ConfiguresPostType;
use Studiometa\Foehn\Discovery\Concerns\CacheableDiscovery;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Studiometa\Foehn\PostTypes\PostTypeBuilder;
use Timber\Post;

/**
 * Discovers classes marked with #[AsPostType] attribute
 * and registers them as WordPress custom post types.
 */
final class PostTypeDiscovery implements WpDiscovery
{
    use IsWpDiscovery;
    use CacheableDiscovery;

    /**
     * Discover post type attributes on classes.
     *
     * @param DiscoveryLocation $location
     * @param ReflectionClass<object> $class
     */
    public function discover(DiscoveryLocation $location, ReflectionClass $class): void
    {
        $attributes = $class->getAttributes(AsPostType::class);

        if ($attributes === []) {
            return;
        }

        // Verify the class extends Timber\Post
        if (!$class->isSubclassOf(Post::class)) {
            throw new InvalidArgumentException(sprintf(
                'Class %s must extend %s to use #[AsPostType]',
                $class->getName(),
                Post::class,
            ));
        }

        $attribute = $attributes[0]->newInstance();

        $this->addItem($location, [
            'attribute' => $attribute,
            'className' => $class->getName(),
            'implementsConfig' => $class->implementsInterface(ConfiguresPostType::class),
        ]);
    }

    /**
     * Apply discovered post types by registering them with WordPress.
     */
    public function apply(): void
    {
        foreach ($this->getItems() as $item) {
            $this->registerPostType($item);
        }
    }

    /**
     * Register a single post type with WordPress.
     *
     * @param array<string, mixed> $item
     */
    private function registerPostType(array $item): void
    {
        $className = $item['className'];
        $implementsConfig = $item['implementsConfig'];
        $attribute = $this->resolveAttribute($item);
        $builder = PostTypeBuilder::fromAttribute($attribute);

        // Allow class to customize the builder
        if ($implementsConfig) {
            /** @var ConfiguresPostType $className */
            $builder = $className::configurePostType($builder);
        }

        // Register the post type
        $builder->register();

        // Register Timber class map
        $this->registerTimberClassMap($attribute->name, $className);
    }

    /**
     * Resolve the AsPostType attribute from a discovered or cached item.
     *
     * @param array<string, mixed> $item
     */
    private function resolveAttribute(array $item): AsPostType
    {
        if (isset($item['attribute'])) {
            return $item['attribute'];
        }

        // Cached format - rebuild attribute
        return new AsPostType(
            name: $item['name'],
            singular: $item['singular'],
            plural: $item['plural'],
            public: $item['public'] ?? true,
            hasArchive: $item['hasArchive'] ?? false,
            showInRest: $item['showInRest'] ?? true,
            menuIcon: $item['menuIcon'] ?? null,
            supports: $item['supports'] ?? ['title', 'editor', 'thumbnail'],
            taxonomies: $item['taxonomies'] ?? [],
            rewriteSlug: $item['rewriteSlug'] ?? null,
            hierarchical: $item['hierarchical'] ?? false,
            menuPosition: $item['menuPosition'] ?? null,
            labels: $item['labels'] ?? [],
            rewrite: $item['rewrite'] ?? null,
        );
    }

    /**
     * Register the Timber class map for this post type.
     *
     * @param string $postType
     * @param class-string $className
     */
    private function registerTimberClassMap(string $postType, string $className): void
    {
        add_filter('timber/post/classmap', static function (array $map) use ($postType, $className): array {
            $map[$postType] = $className;

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
        /** @var AsPostType $attribute */
        $attribute = $item['attribute'];

        return [
            'name' => $attribute->name,
            'singular' => $attribute->singular,
            'plural' => $attribute->plural,
            'public' => $attribute->public,
            'hasArchive' => $attribute->hasArchive,
            'showInRest' => $attribute->showInRest,
            'menuIcon' => $attribute->menuIcon,
            'supports' => $attribute->supports,
            'taxonomies' => $attribute->taxonomies,
            'rewriteSlug' => $attribute->rewriteSlug,
            'hierarchical' => $attribute->hierarchical,
            'menuPosition' => $attribute->menuPosition,
            'labels' => $attribute->labels,
            'rewrite' => $attribute->rewrite,
            'className' => $item['className'],
            'implementsConfig' => $item['implementsConfig'],
        ];
    }
}
