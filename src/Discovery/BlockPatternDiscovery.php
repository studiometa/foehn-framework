<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Discovery;

use Studiometa\WPTempest\Attributes\AsBlockPattern;
use Studiometa\WPTempest\Contracts\BlockPatternInterface;
use Studiometa\WPTempest\Contracts\ViewEngineInterface;
use Studiometa\WPTempest\Discovery\Concerns\CacheableDiscovery;
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
    use CacheableDiscovery;

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
            foreach ($this->getAllItems() as $item) {
                // Handle cached format
                if (isset($item['patternName'])) {
                    $this->registerPatternFromCache($item);
                } else {
                    $this->registerPattern($item['attribute'], $item['className'], $item['implementsInterface']);
                }
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
        $content = $this->renderPatternContent($attribute->getTemplatePath(), $className, $implementsInterface);

        $this->doRegisterPattern(
            $attribute->name,
            $attribute->title,
            $content,
            $attribute->viewportWidth,
            $attribute->inserter,
            $attribute->categories,
            $attribute->keywords,
            $attribute->blockTypes,
            $attribute->description,
        );
    }

    /**
     * Register pattern from cached data.
     *
     * @param array<string, mixed> $item
     */
    private function registerPatternFromCache(array $item): void
    {
        // Render content at runtime (patterns may have dynamic data)
        $content = $this->renderPatternContent($item['templatePath'], $item['className'], $item['implementsInterface']);

        $this->doRegisterPattern(
            $item['patternName'],
            $item['title'],
            $content,
            $item['viewportWidth'],
            $item['inserter'],
            $item['categories'],
            $item['keywords'],
            $item['blockTypes'],
            $item['description'],
        );
    }

    /**
     * Actually register the block pattern.
     *
     * @param array<string> $categories
     * @param array<string> $keywords
     * @param array<string> $blockTypes
     */
    private function doRegisterPattern(
        string $name,
        string $title,
        string $content,
        int $viewportWidth,
        bool $inserter,
        array $categories,
        array $keywords,
        array $blockTypes,
        ?string $description,
    ): void {
        // Build pattern configuration
        $config = [
            'title' => $title,
            'content' => $content,
            'viewportWidth' => $viewportWidth,
            'inserter' => $inserter,
        ];

        if (!empty($categories)) {
            $config['categories'] = $categories;
        }

        if (!empty($keywords)) {
            $config['keywords'] = $keywords;
        }

        if (!empty($blockTypes)) {
            $config['blockTypes'] = $blockTypes;
        }

        if ($description !== null) {
            $config['description'] = $description;
        }

        // Register the pattern
        register_block_pattern($name, $config);
    }

    /**
     * Render pattern content using ViewEngine.
     *
     * @param string $templatePath
     * @param class-string $className
     * @param bool $implementsInterface
     * @return string
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
        }

        return $view->render($templatePath, $context);
    }

    /**
     * Convert a discovered item to a cacheable format.
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    protected function itemToCacheable(array $item): array
    {
        /** @var AsBlockPattern $attribute */
        $attribute = $item['attribute'];

        return [
            'patternName' => $attribute->name,
            'title' => $attribute->title,
            'templatePath' => $attribute->getTemplatePath(),
            'className' => $item['className'],
            'implementsInterface' => $item['implementsInterface'],
            'viewportWidth' => $attribute->viewportWidth,
            'inserter' => $attribute->inserter,
            'categories' => $attribute->categories,
            'keywords' => $attribute->keywords,
            'blockTypes' => $attribute->blockTypes,
            'description' => $attribute->description,
        ];
    }
}
