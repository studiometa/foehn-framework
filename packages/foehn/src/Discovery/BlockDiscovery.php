<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use InvalidArgumentException;
use ReflectionClass;
use Studiometa\Foehn\Attributes\AsBlock;
use Studiometa\Foehn\Blocks\BlockAssets;
use Studiometa\Foehn\Blocks\BlockAttributeSchema;
use Studiometa\Foehn\Blocks\BlockRenderer;
use Studiometa\Foehn\Contracts\BlockInterface;
use Studiometa\Foehn\Discovery\Concerns\CacheableDiscovery;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use WP_Block;

use function Tempest\Container\get;

/**
 * Discovers classes marked with #[AsBlock] attribute
 * and registers them as native Gutenberg blocks.
 */
final class BlockDiscovery implements WpDiscovery
{
    use IsWpDiscovery;
    use CacheableDiscovery;

    /**
     * Discover block attributes on classes.
     *
     * @param DiscoveryLocation $location
     * @param ReflectionClass<object> $class
     */
    public function discover(DiscoveryLocation $location, ReflectionClass $class): void
    {
        $attributes = $class->getAttributes(AsBlock::class);

        if ($attributes === []) {
            return;
        }

        // Verify the class implements BlockInterface
        if (!$class->implementsInterface(BlockInterface::class)) {
            throw new InvalidArgumentException(sprintf(
                'Class %s must implement %s to use #[AsBlock]',
                $class->getName(),
                BlockInterface::class,
            ));
        }

        $attribute = $attributes[0]->newInstance();

        $this->addItem($location, [
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
            foreach ($this->getItems() as $item) {
                // Handle cached format
                if (($item['blockName'] ?? null) !== null) {
                    $this->registerBlockFromCache($item);

                    continue;
                }

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
            $attribute->allowedBlocks,
        );
    }

    /**
     * Register block from cached data.
     *
     * @param array<string, mixed> $item
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
            $item['allowedBlocks'],
        );
    }

    /**
     * Actually register the block.
     *
     * Only `allowedBlocks` has a WP_Block_Type counterpart. The inner blocks template
     * and its lock are editor-side only, so they travel in the editor payload instead.
     *
     * @param class-string<BlockInterface> $className
     * @param array<string> $keywords
     * @param array<string, mixed> $supports
     * @param array<string> $ancestor
     * @param list<string> $allowedBlocks
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
        array $allowedBlocks,
    ): void {
        $args = [
            'api_version' => 3,
            'title' => $title,
            'category' => $category,
            'render_callback' => $this->createRenderCallback($className, $interactivityNamespace),
            // Every Foehn block is dynamic, so "Edit as HTML" can only ever invalidate it:
            // there is no static save output for the editor to validate the markup against.
            // Seeded rather than forced, so an author who really wants it can opt back in.
            'supports' => $supports + ['html' => false],
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

        if ($parent !== null) {
            $args['parent'] = [$parent];
        }

        if (!empty($ancestor)) {
            $args['ancestor'] = $ancestor;
        }

        if (!empty($allowedBlocks)) {
            $args['allowed_blocks'] = $allowedBlocks;
        }

        // Add attributes from class, without the editor-only keys
        if (method_exists($className, 'attributes')) {
            $args['attributes'] = BlockAttributeSchema::toRegistration($className::attributes());
        }

        // Per-block stylesheet and front-end script, found by convention
        $args += BlockAssets::register($blockName);

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
     * @param array<string, mixed> $item
     * @return array<string, mixed>
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
            'allowedBlocks' => $attribute->allowedBlocks,
            'innerBlocksTemplate' => $attribute->innerBlocksTemplate,
            'innerBlocksTemplateLock' => $attribute->innerBlocksTemplateLock,
        ];
    }

    /**
     * Get the editor payload for every discovered block.
     *
     * The payload is exposed to the block editor as `window.foehnBlocks` and
     * drives the generic block registrar: it describes the sidebar fields and
     * the inner blocks configuration of each block.
     *
     * @return list<array{name: string, attributes: array<string, mixed>, innerBlocks: array{allowedBlocks: list<string>, template: list<mixed>, templateLock: string|bool|null}|null}>
     */
    public function getEditorDefinitions(): array
    {
        $definitions = [];

        /** @var array<string, mixed> $item */
        foreach ($this->getItems() as $item) {
            $definitions[] = $this->itemToEditorDefinition($item);
        }

        return $definitions;
    }

    /**
     * Build the editor payload of a single discovered item.
     *
     * @param array<string, mixed> $item
     * @return array{name: string, attributes: array<string, mixed>, innerBlocks: array{allowedBlocks: list<string>, template: list<mixed>, templateLock: string|bool|null}|null}
     */
    private function itemToEditorDefinition(array $item): array
    {
        /** @var class-string<BlockInterface> $className */
        $className = $item['className'];

        // Cached items are flat arrays, live items carry the attribute instance.
        // A cache written by another Foehn version never reaches this point:
        // DiscoveryCache stamps its own schema version and rejects a stale file.
        if (($item['blockName'] ?? null) !== null) {
            return self::buildEditorDefinition(
                $className,
                $item['blockName'],
                $item['allowedBlocks'],
                $item['innerBlocksTemplate'],
                $item['innerBlocksTemplateLock'],
            );
        }

        /** @var AsBlock $attribute */
        $attribute = $item['attribute'];

        return self::buildEditorDefinition(
            $className,
            $attribute->name,
            $attribute->allowedBlocks,
            $attribute->innerBlocksTemplate,
            $attribute->innerBlocksTemplateLock,
        );
    }

    /**
     * Assemble one editor definition from already normalized values.
     *
     * @param class-string<BlockInterface> $className
     * @param list<string> $allowedBlocks
     * @param list<mixed> $template
     * @return array{name: string, attributes: array<string, mixed>, innerBlocks: array{allowedBlocks: list<string>, template: list<mixed>, templateLock: string|bool|null}|null}
     */
    private static function buildEditorDefinition(
        string $className,
        string $name,
        array $allowedBlocks,
        array $template,
        string|bool|null $templateLock,
    ): array {
        return [
            'name' => $name,
            'attributes' => method_exists($className, 'attributes')
                ? BlockAttributeSchema::toEditorFields($className::attributes())
                : [],
            // The payload keys are the InnerBlocks prop names, not the AsBlock
            // parameter names: this array is spread onto the component as is.
            'innerBlocks' => AsBlock::hasInnerBlocks($allowedBlocks, $template, $templateLock)
                ? [
                    'allowedBlocks' => $allowedBlocks,
                    'template' => $template,
                    'templateLock' => $templateLock,
                ]
                : null,
        ];
    }
}
