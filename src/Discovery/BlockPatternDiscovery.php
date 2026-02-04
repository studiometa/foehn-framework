<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Discovery;

use Studiometa\WPTempest\Attributes\AsBlockPattern;
use Studiometa\WPTempest\Contracts\BlockPatternInterface;
use Studiometa\WPTempest\Contracts\ViewEngineInterface;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Discovery\IsDiscovery;
use Tempest\Reflection\ClassReflector;

use function Tempest\get;

/**
 * Discovers classes marked with #[AsBlockPattern] attribute
 * and registers them as WordPress block patterns.
 */
final class BlockPatternDiscovery implements Discovery
{
    use IsDiscovery;

    /**
     * Discover block pattern attributes on classes.
     */
    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        $attribute = $class->getAttribute(AsBlockPattern::class);

        if ($attribute === null) {
            return;
        }

        $this->discoveryItems->add($location, [
            'attribute' => $attribute,
            'className' => $class->getName(),
            'implementsInterface' => $class->getReflection()->implementsInterface(BlockPatternInterface::class),
        ]);
    }

    /**
     * Apply discovered block patterns by registering them.
     */
    public function apply(): void
    {
        add_action('init', function (): void {
            foreach ($this->discoveryItems as $item) {
                $this->registerPattern($item['attribute'], $item['className'], $item['implementsInterface']);
            }
        });
    }

    /**
     * Register a single block pattern.
     *
     * @param AsBlockPattern $attribute
     * @param class-string $className
     * @param bool $implementsInterface
     */
    private function registerPattern(AsBlockPattern $attribute, string $className, bool $implementsInterface): void
    {
        // Get pattern content
        $content = $this->renderPatternContent($attribute, $className, $implementsInterface);

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
     * @param AsBlockPattern $attribute
     * @param class-string $className
     * @param bool $implementsInterface
     * @return string
     */
    private function renderPatternContent(
        AsBlockPattern $attribute,
        string $className,
        bool $implementsInterface,
    ): string {
        /** @var ViewEngineInterface $view */
        $view = get(ViewEngineInterface::class);

        $template = $attribute->getTemplatePath();
        $context = [];

        // Get composed data if class implements interface
        if ($implementsInterface) {
            /** @var BlockPatternInterface $instance */
            $instance = get($className);
            $context = $instance->compose();
        }

        return $view->render($template, $context);
    }
}
