<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use InvalidArgumentException;
use Studiometa\Foehn\Attributes\AsBlock;
use Studiometa\Foehn\Attributes\AsDiscovery;
use Studiometa\Foehn\Blocks\BlockAssets;
use Studiometa\Foehn\Blocks\BlockAttributeSchema;
use Studiometa\Foehn\Blocks\BlockRenderer;
use Studiometa\Foehn\Contracts\BlockInterface;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;
use WP_Block;

use function Tempest\Container\get;

/**
 * Discovers classes marked with #[AsBlock] attribute
 * and registers them as native Gutenberg blocks.
 */
#[AsDiscovery(phase: DiscoveryPhase::Main)]
final class BlockDiscovery implements Discovery
{
    use IsWpDiscovery;

    /**
     * Discover block attributes on classes.
     *
     * @param DiscoveryLocation $location
     * @param ClassReflector $class
     */
    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        if (!$this->isConcrete($class)) {
            return;
        }

        $attribute = $class->getAttribute(AsBlock::class);

        if ($attribute === null) {
            return;
        }

        // Verify the class implements BlockInterface
        if (!$class->implements(BlockInterface::class)) {
            throw new InvalidArgumentException(sprintf(
                'Class %s must implement %s to use #[AsBlock]',
                $class->getName(),
                BlockInterface::class,
            ));
        }

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
                /** @var AsBlock $attribute */
                $attribute = $item['attribute'];

                $this->registerBlock($attribute, $item['className']);
            }
        });
    }

    /**
     * Register a single native block.
     *
     * Only `allowedBlocks` has a WP_Block_Type counterpart. The inner blocks template
     * and its lock are editor-side only, so they travel in the editor payload instead.
     *
     * @param class-string<BlockInterface> $className
     */
    private function registerBlock(AsBlock $attribute, string $className): void
    {
        $blockName = $attribute->name;
        $supports = $attribute->supports;

        if ($attribute->interactivity) {
            $supports['interactivity'] = true;
        }

        $interactivityNamespace = $attribute->interactivity ? $attribute->getInteractivityNamespace() : null;

        $args = [
            'api_version' => 3,
            'title' => $attribute->title,
            'category' => $attribute->category,
            'render_callback' => $this->createRenderCallback($className, $interactivityNamespace),
            // Every Foehn block is dynamic, so "Edit as HTML" can only ever invalidate it:
            // there is no static save output for the editor to validate the markup against.
            // Seeded rather than forced, so an author who really wants it can opt back in.
            'supports' => $supports + ['html' => false],
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

        if ($attribute->parent !== null) {
            $args['parent'] = [$attribute->parent];
        }

        if (!empty($attribute->ancestor)) {
            $args['ancestor'] = $attribute->ancestor;
        }

        if (!empty($attribute->allowedBlocks)) {
            $args['allowed_blocks'] = $attribute->allowedBlocks;
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
        /** @var AsBlock $attribute */
        $attribute = $item['attribute'];

        $allowedBlocks = $attribute->allowedBlocks;
        $template = $attribute->innerBlocksTemplate;
        $templateLock = $attribute->innerBlocksTemplateLock;

        return [
            'name' => $attribute->name,
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
