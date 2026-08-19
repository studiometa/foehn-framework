<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use ReflectionClass;
use Studiometa\Foehn\Attributes\AsBlockPattern;
use Studiometa\Foehn\Contracts\Arrayable;
use Studiometa\Foehn\Contracts\BlockPatternInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Studiometa\Foehn\Discovery\Concerns\CacheableDiscovery;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;

use function Tempest\Container\get;

/**
 * Discovers classes marked with #[AsBlockPattern] attribute
 * and registers them as WordPress block patterns.
 */
final class BlockPatternDiscovery implements WpDiscovery
{
    use IsWpDiscovery;
    use CacheableDiscovery;

    /**
     * Discover block pattern attributes on classes.
     *
     * @param DiscoveryLocation $location
     * @param ReflectionClass<object> $class
     */
    public function discover(DiscoveryLocation $location, ReflectionClass $class): void
    {
        $attributes = $class->getAttributes(AsBlockPattern::class);

        if ($attributes === []) {
            return;
        }

        $attribute = $attributes[0]->newInstance();

        $this->addItem($location, [
            'attribute' => $attribute,
            'className' => $class->getName(),
            'implementsInterface' => $class->implementsInterface(BlockPatternInterface::class),
        ]);
    }

    /**
     * Apply discovered block patterns by registering them.
     */
    public function apply(): void
    {
        add_action('init', function (): void {
            foreach ($this->getItems() as $item) {
                /** @var AsBlockPattern $attribute */
                $attribute = $item['attribute'];

                $this->registerPattern($attribute, $item['className'], $item['implementsInterface']);
            }
        });
    }

    /**
     * Register a single block pattern.
     *
     * @param class-string $className
     */
    private function registerPattern(AsBlockPattern $attribute, string $className, bool $implementsInterface): void
    {
        // Content is rendered at apply time: patterns may compose dynamic data
        $content = $this->renderPatternContent($attribute->getTemplatePath(), $className, $implementsInterface);

        // Build pattern configuration
        $config = [
            'title' => $attribute->title,
            'content' => $content,
            'viewportWidth' => $attribute->viewportWidth,
            'inserter' => $attribute->inserter,
        ];

        if (!empty($attribute->categories)) {
            $config['categories'] = $attribute->categories;
        }

        if (!empty($attribute->keywords)) {
            $config['keywords'] = $attribute->keywords;
        }

        if (!empty($attribute->blockTypes)) {
            $config['blockTypes'] = $attribute->blockTypes;
        }

        if ($attribute->description !== null) {
            $config['description'] = $attribute->description;
        }

        // Register the pattern
        register_block_pattern($attribute->name, $config);
    }

    /**
     * Render pattern content using ViewEngine.
     *
     * @param class-string $className
     */
    private function renderPatternContent(string $templatePath, string $className, bool $implementsInterface): string
    {
        /** @var ViewEngineInterface $view */
        $view = get(ViewEngineInterface::class);

        $context = [];

        // Get composed data if class implements interface
        if ($implementsInterface) {
            /** @var BlockPatternInterface $instance */
            $instance = get($className);
            $context = $instance->compose();

            if ($context instanceof Arrayable) {
                $context = $context->toArray();
            }
        }

        return $view->render($templatePath, $context);
    }
}
