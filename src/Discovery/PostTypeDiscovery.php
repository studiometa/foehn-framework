<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Discovery;

use InvalidArgumentException;
use Studiometa\WPTempest\Attributes\AsPostType;
use Studiometa\WPTempest\Contracts\ConfiguresPostType;
use Studiometa\WPTempest\Discovery\Concerns\CacheableDiscovery;
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
    use CacheableDiscovery;

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
        foreach ($this->getAllItems() as $item) {
            $this->registerPostType($item);
        }
    }

    /**
     * Register a single post type with WordPress.
     *
     * @param array{attribute?: AsPostType, className: class-string, implementsConfig: bool, name?: string, singular?: string, plural?: string, args?: array<string, mixed>} $item
     */
    private function registerPostType(array $item): void
    {
        $className = $item['className'];
        $implementsConfig = $item['implementsConfig'];

        // Build from attribute or cached data
        if (isset($item['attribute'])) {
            $builder = PostTypeBuilder::fromAttribute($item['attribute']);
            $postTypeName = $item['attribute']->name;
        } else {
            // Cached format - rebuild attribute
            $attribute = new AsPostType(
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
            );
            $builder = PostTypeBuilder::fromAttribute($attribute);
            $postTypeName = $item['name'];
        }

        // Allow class to customize the builder
        if ($implementsConfig) {
            /** @var ConfiguresPostType $className */
            $builder = $className::configurePostType($builder);
        }

        // Register the post type
        $builder->register();

        // Register Timber class map
        $this->registerTimberClassMap($postTypeName, $className);
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
     * @param array{attribute: AsPostType, className: class-string, implementsConfig: bool} $item
     * @return array{name: string, singular: string, plural: string, args: array<string, mixed>, className: class-string, implementsConfig: bool}
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
            'className' => $item['className'],
            'implementsConfig' => $item['implementsConfig'],
        ];
    }
}
