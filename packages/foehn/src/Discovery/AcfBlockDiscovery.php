<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use InvalidArgumentException;
use Studiometa\Foehn\Attributes\AsAcfBlock;
use Studiometa\Foehn\Attributes\AsDiscovery;
use Studiometa\Foehn\Blocks\AcfBlockRenderer;
use Studiometa\Foehn\Contracts\AcfBlockInterface;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;

use function Tempest\Container\get;

/**
 * Discovers classes marked with #[AsAcfBlock] attribute
 * and registers them as ACF blocks.
 */
#[AsDiscovery(phase: DiscoveryPhase::Main)]
final class AcfBlockDiscovery implements Discovery
{
    use IsWpDiscovery;

    /**
     * Discover ACF block attributes on classes.
     *
     * @param DiscoveryLocation $location
     * @param ClassReflector $class
     */
    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        if (!$this->isConcrete($class)) {
            return;
        }

        $attribute = $class->getAttribute(AsAcfBlock::class);

        if ($attribute === null) {
            return;
        }

        // Verify the class implements AcfBlockInterface
        if (!$class->implements(AcfBlockInterface::class)) {
            throw new InvalidArgumentException(sprintf(
                'Class %s must implement %s to use #[AsAcfBlock]',
                $class->getName(),
                AcfBlockInterface::class,
            ));
        }

        $this->addItem($location, [
            'attribute' => $attribute,
            'className' => $class->getName(),
        ]);
    }

    /**
     * Apply discovered ACF blocks by registering them.
     */
    public function apply(): void
    {
        // ACF blocks must be registered on acf/init
        add_action('acf/init', function (): void {
            foreach ($this->getItems() as $item) {
                /** @var AsAcfBlock $attribute */
                $attribute = $item['attribute'];

                $this->registerBlock($attribute, $item['className']);
            }
        });
    }

    /**
     * Register a single ACF block.
     *
     * @param class-string<AcfBlockInterface> $className
     */
    private function registerBlock(AsAcfBlock $attribute, string $className): void
    {
        // Build block configuration
        $config = [
            'name' => $attribute->name,
            'title' => $attribute->title,
            'description' => $attribute->description ?? '',
            'category' => $attribute->category,
            'icon' => $attribute->icon ?? 'block-default',
            'keywords' => $attribute->keywords,
            'mode' => $attribute->mode,
            'supports' => $this->buildSupports($attribute),
            'render_callback' => $this->createRenderCallback($className),
        ];

        // Add optional configuration
        if (!empty($attribute->postTypes)) {
            $config['post_types'] = $attribute->postTypes;
        }

        if ($attribute->parent !== null) {
            $config['parent'] = [$attribute->parent];
        }

        // Register the block type
        if (function_exists('acf_register_block_type')) {
            acf_register_block_type($config);
        }

        // Register fields if the class defines them
        $this->registerFields($attribute->name, $className);
    }

    /**
     * Build supports configuration with defaults.
     *
     * @return array<string, mixed>
     */
    private function buildSupports(AsAcfBlock $attribute): array
    {
        $defaults = [
            'align' => false,
            'mode' => true,
            'multiple' => true,
        ];

        return array_merge($defaults, $attribute->supports);
    }

    /**
     * Create the render callback for the block.
     *
     * @param class-string<AcfBlockInterface> $className
     * @return callable
     */
    private function createRenderCallback(string $className): callable
    {
        return static function (array $block, string $content, bool $isPreview, int $postId) use ($className): void {
            /** @var AcfBlockInterface $instance */
            $instance = get($className);

            /** @var AcfBlockRenderer $renderer */
            $renderer = get(AcfBlockRenderer::class);

            echo $renderer->render($instance, $block, $isPreview);
        };
    }

    /**
     * Register ACF fields for the block.
     *
     * @param string $blockName
     * @param class-string<AcfBlockInterface> $className
     */
    private function registerFields(string $blockName, string $className): void
    {
        if (!method_exists($className, 'fields')) {
            return;
        }

        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        $fields = $className::fields();

        // Set the location to this block
        $fullName = 'acf/' . $blockName;
        $fields->setLocation('block', '==', $fullName);

        // Register the field group
        acf_add_local_field_group($fields->build());
    }
}
