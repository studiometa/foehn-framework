<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use InvalidArgumentException;
use ReflectionClass;
use Studiometa\Foehn\Attributes\AsAcfBlock;
use Studiometa\Foehn\Blocks\AcfBlockRenderer;
use Studiometa\Foehn\Contracts\AcfBlockInterface;
use Studiometa\Foehn\Discovery\Concerns\CacheableDiscovery;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;

use function Tempest\get;

/**
 * Discovers classes marked with #[AsAcfBlock] attribute
 * and registers them as ACF blocks.
 */
final class AcfBlockDiscovery implements WpDiscovery
{
    use IsWpDiscovery;
    use CacheableDiscovery;

    /**
     * Discover ACF block attributes on classes.
     *
     * @param ReflectionClass<object> $class
     */
    public function discover(ReflectionClass $class): void
    {
        $attributes = $class->getAttributes(AsAcfBlock::class);

        if ($attributes === []) {
            return;
        }

        // Verify the class implements AcfBlockInterface
        if (!$class->implementsInterface(AcfBlockInterface::class)) {
            throw new InvalidArgumentException(sprintf(
                'Class %s must implement %s to use #[AsAcfBlock]',
                $class->getName(),
                AcfBlockInterface::class,
            ));
        }

        $attribute = $attributes[0]->newInstance();

        $this->addItem([
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
            foreach ($this->getAllItems() as $item) {
                // Handle cached format
                if (isset($item['name'])) {
                    $this->registerBlockFromCache($item);

                    continue;
                }

                $this->registerBlock($item['attribute'], $item['className']);
            }
        });
    }

    /**
     * Register a single ACF block.
     *
     * @param AsAcfBlock $attribute
     * @param class-string<AcfBlockInterface> $className
     */
    private function registerBlock(AsAcfBlock $attribute, string $className): void
    {
        $this->doRegisterBlock(
            $className,
            $attribute->name,
            $attribute->title,
            $attribute->description,
            $attribute->category,
            $attribute->icon,
            $attribute->keywords,
            $attribute->mode,
            $this->buildSupports($attribute),
            $attribute->postTypes,
            $attribute->parent,
        );
    }

    /**
     * Register ACF block from cached data.
     *
     * @param array<string, mixed> $item
     */
    private function registerBlockFromCache(array $item): void
    {
        $this->doRegisterBlock(
            $item['className'],
            $item['name'],
            $item['title'],
            $item['description'],
            $item['category'],
            $item['icon'],
            $item['keywords'],
            $item['mode'],
            $item['supports'],
            $item['postTypes'],
            $item['parent'],
        );
    }

    /**
     * Actually register the ACF block.
     *
     * @param class-string<AcfBlockInterface> $className
     * @param array<string> $keywords
     * @param array<string, mixed> $supports
     * @param array<string> $postTypes
     */
    private function doRegisterBlock(
        string $className,
        string $name,
        string $title,
        ?string $description,
        string $category,
        ?string $icon,
        array $keywords,
        string $mode,
        array $supports,
        array $postTypes,
        ?string $parent,
    ): void {
        // Build block configuration
        $config = [
            'name' => $name,
            'title' => $title,
            'description' => $description ?? '',
            'category' => $category,
            'icon' => $icon ?? 'block-default',
            'keywords' => $keywords,
            'mode' => $mode,
            'supports' => $supports,
            'render_callback' => $this->createRenderCallback($className),
        ];

        // Add optional configuration
        if (!empty($postTypes)) {
            $config['post_types'] = $postTypes;
        }

        if ($parent !== null) {
            $config['parent'] = [$parent];
        }

        // Register the block type
        if (function_exists('acf_register_block_type')) {
            acf_register_block_type($config);
        }

        // Register fields if the class defines them
        $this->registerFields($name, $className);
    }

    /**
     * Build supports configuration with defaults.
     *
     * @param AsAcfBlock $attribute
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

    /**
     * Convert a discovered item to a cacheable format.
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    protected function itemToCacheable(array $item): array
    {
        /** @var AsAcfBlock $attribute */
        $attribute = $item['attribute'];

        return [
            'className' => $item['className'],
            'name' => $attribute->name,
            'title' => $attribute->title,
            'description' => $attribute->description,
            'category' => $attribute->category,
            'icon' => $attribute->icon,
            'keywords' => $attribute->keywords,
            'mode' => $attribute->mode,
            'supports' => $this->buildSupports($attribute),
            'postTypes' => $attribute->postTypes,
            'parent' => $attribute->parent,
        ];
    }
}
