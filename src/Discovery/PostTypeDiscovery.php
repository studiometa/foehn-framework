<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Discovery;

use InvalidArgumentException;
use Studiometa\WPTempest\Attributes\AsPostType;
use Studiometa\WPTempest\Contracts\ConfiguresPostType;
use Studiometa\WPTempest\PostTypes\PostTypeBuilder;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Discovery\IsDiscovery;
use Tempest\Reflection\ClassReflector;
use Timber\Post;

/**
 * Discovers classes marked with #[AsPostType] attribute
 * and registers them as WordPress custom post types.
 */
final class PostTypeDiscovery implements Discovery
{
    use IsDiscovery;

    /**
     * Discover post type attributes on classes.
     */
    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
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

        $this->discoveryItems->add($location, [
            'attribute' => $attribute,
            'className' => $class->getName(),
            'implementsConfig' => $class->getReflection()->implementsInterface(ConfiguresPostType::class),
        ]);
    }

    /**
     * Apply discovered post types by registering them with WordPress.
     */
    public function apply(): void
    {
        foreach ($this->discoveryItems as $item) {
            $this->registerPostType($item);
        }
    }

    /**
     * Register a single post type with WordPress.
     *
     * @param array{attribute: AsPostType, className: class-string, implementsConfig: bool} $item
     */
    private function registerPostType(array $item): void
    {
        $attribute = $item['attribute'];
        $className = $item['className'];
        $implementsConfig = $item['implementsConfig'];

        // Build the post type configuration
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
