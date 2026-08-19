<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use InvalidArgumentException;
use Studiometa\Foehn\Attributes\AsDiscovery;
use Studiometa\Foehn\Attributes\AsPostType;
use Studiometa\Foehn\Contracts\ConfiguresPostType;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Studiometa\Foehn\PostTypes\PostTypeBuilder;
use Studiometa\Foehn\PostTypes\PostTypeRegistry;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;
use Timber\Post;

/**
 * Discovers classes marked with #[AsPostType] attribute
 * and registers them as WordPress custom post types.
 */
#[AsDiscovery(phase: DiscoveryPhase::Main)]
final class PostTypeDiscovery implements Discovery
{
    use IsWpDiscovery;

    /**
     * Discover post type attributes on classes.
     *
     * @param DiscoveryLocation $location
     * @param ClassReflector $class
     */
    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        if (!$this->isConcrete($class)) {
            return;
        }

        $attribute = $class->getAttribute(AsPostType::class);

        if ($attribute === null) {
            return;
        }

        // Verify the class extends Timber\Post
        if (!$class->getReflection()->isSubclassOf(Post::class)) {
            throw new InvalidArgumentException(sprintf(
                'Class %s must extend %s to use #[AsPostType]',
                $class->getName(),
                Post::class,
            ));
        }

        $this->addItem($location, [
            'attribute' => $attribute,
            'className' => $class->getName(),
            'implementsConfig' => $class->implements(ConfiguresPostType::class),
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
