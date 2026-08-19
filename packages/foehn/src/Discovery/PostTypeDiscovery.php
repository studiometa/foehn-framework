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
use Studiometa\Foehn\PostTypes\PostTypeRegistry;
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
        /** @var AsPostType $attribute */
        $attribute = $item['attribute'];
        $builder = PostTypeBuilder::fromAttribute($attribute);

        // Allow class to customize the builder
        if ($implementsConfig) {
            /** @var ConfiguresPostType $className */
            $builder = $className::configurePostType($builder);
        }

        // Register the post type
        $builder->register();

        // Register in PostTypeRegistry for QueriesPostType trait
        PostTypeRegistry::register($className, $attribute->name);

        // Register Timber class map
        $this->registerTimberClassMap($attribute->name, $className);
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
}
