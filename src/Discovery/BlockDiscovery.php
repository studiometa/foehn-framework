<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Discovery;

use InvalidArgumentException;
use Studiometa\WPTempest\Attributes\AsBlock;
use Studiometa\WPTempest\Blocks\BlockRenderer;
use Studiometa\WPTempest\Contracts\BlockInterface;
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
            foreach ($this->discoveryItems as $item) {
                $this->registerBlock($item['attribute'], $item['className']);
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
        $args = [
            'title' => $attribute->title,
            'category' => $attribute->category,
            'render_callback' => $this->createRenderCallback($attribute, $className),
        ];

        // Add optional configuration
        if ($attribute->icon !== null) {
            $args['icon'] = $attribute->icon;
        }

        if ($attribute->description !== null) {
            $args['description'] = $attribute->description;
        }

        if (!empty($attribute->keywords)) {
            $args['keywords'] = $attribute->keywords;
        }

        if (!empty($attribute->supports)) {
            $supports = $attribute->supports;

            // Add interactivity support
            if ($attribute->interactivity) {
                $supports['interactivity'] = true;
            }

            $args['supports'] = $supports;
        } elseif ($attribute->interactivity) {
            $args['supports'] = ['interactivity' => true];
        }

        if ($attribute->parent !== null) {
            $args['parent'] = [$attribute->parent];
        }

        if (!empty($attribute->ancestor)) {
            $args['ancestor'] = $attribute->ancestor;
        }

        // Add attributes from class
        if (method_exists($className, 'attributes')) {
            $args['attributes'] = $className::attributes();
        }

        // Register the block
        register_block_type($attribute->name, $args);
    }

    /**
     * Create the render callback for the block.
     *
     * @param AsBlock $attribute
     * @param class-string<BlockInterface> $className
     * @return callable
     */
    private function createRenderCallback(AsBlock $attribute, string $className): callable
    {
        return static function (array $attributes, string $content, WP_Block $block) use (
            $attribute,
            $className,
        ): string {
            /** @var BlockInterface $instance */
            $instance = get($className);

            /** @var BlockRenderer $renderer */
            $renderer = get(BlockRenderer::class);

            $interactivityNamespace = $attribute->interactivity ? $attribute->getInteractivityNamespace() : null;

            return $renderer->render($instance, $attributes, $content, $block, $interactivityNamespace);
        };
    }
}
