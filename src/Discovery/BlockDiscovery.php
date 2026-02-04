<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Discovery;

use InvalidArgumentException;
use Studiometa\WPTempest\Attributes\AsBlock;
use Studiometa\WPTempest\Blocks\BlockRenderer;
use Studiometa\WPTempest\Contracts\BlockInterface;
use Studiometa\WPTempest\Discovery\Concerns\CacheableDiscovery;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Discovery\IsDiscovery;
use Tempest\Reflection\ClassReflector;
use WP_Block;

use function Tempest\get;

/**
 * Discovers classes marked with #[AsBlock] attribute
 * and registers them as native Gutenberg blocks.
 */
final class BlockDiscovery implements Discovery
{
    use IsDiscovery;
    use CacheableDiscovery;

    /**
     * Discover block attributes on classes.
     */
    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        $attribute = $class->getAttribute(AsBlock::class);

        if ($attribute === null) {
            return;
        }

        // Verify the class implements BlockInterface
        if (!$class->getReflection()->implementsInterface(BlockInterface::class)) {
            throw new InvalidArgumentException(sprintf(
                'Class %s must implement %s to use #[AsBlock]',
                $class->getName(),
                BlockInterface::class,
            ));
        }

        $this->discoveryItems->add($location, [
            'attribute' => $attribute,
            'className' => $class->getName(),
        ]);
    }

    /**
     * Apply discovered blocks by registering them.
     */
    public function apply(): void
    {
        add_action('init', function (): void {
            foreach ($this->getAllItems() as $item) {
                // Handle cached format
                if (isset($item['blockName'])) {
                    $this->registerBlockFromCache($item);
                } else {
                    $this->registerBlock($item['attribute'], $item['className']);
                }
            }
        });
    }

    /**
     * Register a single native block.
     *
     * @param AsBlock $attribute
     * @param class-string<BlockInterface> $className
     */
    private function registerBlock(AsBlock $attribute, string $className): void
    {
        $supports = $attribute->supports;

        if ($attribute->interactivity) {
            $supports['interactivity'] = true;
        }

        $this->doRegisterBlock(
            $className,
            $attribute->name,
            $attribute->title,
            $attribute->category,
            $attribute->icon,
            $attribute->description,
            $attribute->keywords,
            $supports,
            $attribute->parent,
            $attribute->ancestor,
            $attribute->interactivity,
            $attribute->interactivity ? $attribute->getInteractivityNamespace() : null,
        );
    }

    /**
     * Register block from cached data.
     *
     * @param array{className: string, blockName: string, title: string, category: string, icon: string|null, description: string|null, keywords: array<string>, supports: array<string, mixed>, parent: string|null, ancestor: array<string>, interactivity: bool, interactivityNamespace: string|null} $item
     */
    private function registerBlockFromCache(array $item): void
    {
        $this->doRegisterBlock(
            $item['className'],
            $item['blockName'],
            $item['title'],
            $item['category'],
            $item['icon'],
            $item['description'],
            $item['keywords'],
            $item['supports'],
            $item['parent'],
            $item['ancestor'],
            $item['interactivity'],
            $item['interactivityNamespace'],
        );
    }

    /**
     * Actually register the block.
     *
     * @param class-string<BlockInterface> $className
     * @param array<string> $keywords
     * @param array<string, mixed> $supports
     * @param array<string> $ancestor
     */
    private function doRegisterBlock(
        string $className,
        string $blockName,
        string $title,
        string $category,
        ?string $icon,
        ?string $description,
        array $keywords,
        array $supports,
        ?string $parent,
        array $ancestor,
        bool $interactivity,
        ?string $interactivityNamespace,
    ): void {
        $args = [
            'title' => $title,
            'category' => $category,
            'render_callback' => $this->createRenderCallback($className, $interactivityNamespace),
        ];

        // Add optional configuration
        if ($icon !== null) {
            $args['icon'] = $icon;
        }

        if ($description !== null) {
            $args['description'] = $description;
        }

        if (!empty($keywords)) {
            $args['keywords'] = $keywords;
        }

        if (!empty($supports)) {
            $args['supports'] = $supports;
        }

        if ($parent !== null) {
            $args['parent'] = [$parent];
        }

        if (!empty($ancestor)) {
            $args['ancestor'] = $ancestor;
        }

        // Add attributes from class
        if (method_exists($className, 'attributes')) {
            $args['attributes'] = $className::attributes();
        }

        // Register the block
        register_block_type($blockName, $args);
    }

    /**
     * Create the render callback for the block.
     *
     * @param class-string<BlockInterface> $className
     * @return callable
     */
    private function createRenderCallback(string $className, ?string $interactivityNamespace): callable
    {
        return static function (array $attributes, string $content, WP_Block $block) use (
            $className,
            $interactivityNamespace,
        ): string {
            /** @var BlockInterface $instance */
            $instance = get($className);

            /** @var BlockRenderer $renderer */
            $renderer = get(BlockRenderer::class);

            return $renderer->render($instance, $attributes, $content, $block, $interactivityNamespace);
        };
    }

    /**
     * Convert a discovered item to a cacheable format.
     *
     * @param array{attribute: AsBlock, className: class-string} $item
     * @return array{className: class-string, blockName: string, title: string, category: string, icon: string|null, description: string|null, keywords: array<string>, supports: array<string, mixed>, parent: string|null, ancestor: array<string>, interactivity: bool, interactivityNamespace: string|null}
     */
    protected function itemToCacheable(array $item): array
    {
        /** @var AsBlock $attribute */
        $attribute = $item['attribute'];

        $supports = $attribute->supports;

        if ($attribute->interactivity) {
            $supports['interactivity'] = true;
        }

        return [
            'className' => $item['className'],
            'blockName' => $attribute->name,
            'title' => $attribute->title,
            'category' => $attribute->category,
            'icon' => $attribute->icon,
            'description' => $attribute->description,
            'keywords' => $attribute->keywords,
            'supports' => $supports,
            'parent' => $attribute->parent,
            'ancestor' => $attribute->ancestor,
            'interactivity' => $attribute->interactivity,
            'interactivityNamespace' => $attribute->interactivity ? $attribute->getInteractivityNamespace() : null,
        ];
    }
}
