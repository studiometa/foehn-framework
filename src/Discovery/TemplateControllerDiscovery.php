<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Discovery;

use InvalidArgumentException;
use Studiometa\WPTempest\Attributes\AsTemplateController;
use Studiometa\WPTempest\Contracts\TemplateControllerInterface;
use Studiometa\WPTempest\Discovery\Concerns\CacheableDiscovery;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Discovery\IsDiscovery;
use Tempest\Reflection\ClassReflector;

use function Tempest\get;

/**
 * Discovers classes marked with #[AsTemplateController] attribute
 * and registers them to intercept WordPress template rendering.
 */
final class TemplateControllerDiscovery implements Discovery
{
    use IsDiscovery;
    use CacheableDiscovery;

    /**
     * @var array<string, array{className: class-string, priority: int}>
     */
    private array $controllers = [];

    /**
     * @var array<string, array{className: class-string, priority: int}>
     */
    private array $wildcardControllers = [];

    /**
     * Discover template controller attributes on classes.
     */
    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        $attribute = $class->getAttribute(AsTemplateController::class);

        if ($attribute === null) {
            return;
        }

        // Verify the class implements TemplateControllerInterface
        if (!$class->getReflection()->implementsInterface(TemplateControllerInterface::class)) {
            throw new InvalidArgumentException(sprintf(
                'Class %s must implement %s to use #[AsTemplateController]',
                $class->getName(),
                TemplateControllerInterface::class,
            ));
        }

        $this->discoveryItems->add($location, [
            'attribute' => $attribute,
            'className' => $class->getName(),
        ]);
    }

    /**
     * Apply discovered template controllers by registering them.
     */
    public function apply(): void
    {
        // Build controller maps
        foreach ($this->getAllItems() as $item) {
            // Handle cached format
            if (isset($item['templates'])) {
                $this->registerControllerFromCache($item);
            } else {
                $this->registerController($item);
            }
        }

        // Hook into WordPress template_include filter
        add_filter('template_include', [$this, 'handleTemplateInclude'], 5);
    }

    /**
     * Register a template controller.
     *
     * @param array<string, mixed> $item
     */
    private function registerController(array $item): void
    {
        $attribute = $item['attribute'];
        $className = $item['className'];

        $this->addController($attribute->getTemplates(), $className, $attribute->priority);
    }

    /**
     * Register a template controller from cached data.
     *
     * @param array<string, mixed> $item
     */
    private function registerControllerFromCache(array $item): void
    {
        $this->addController($item['templates'], $item['className'], $item['priority']);
    }

    /**
     * Add a controller to the maps.
     *
     * @param array<string> $templates
     * @param class-string $className
     */
    private function addController(array $templates, string $className, int $priority): void
    {
        foreach ($templates as $template) {
            $entry = ['className' => $className, 'priority' => $priority];

            if (str_contains($template, '*')) {
                $this->wildcardControllers[$template] = $entry;
            } else {
                $this->controllers[$template] = $entry;
            }
        }
    }

    /**
     * Handle the template_include filter.
     *
     * @param string $template WordPress template path
     * @return string Modified template path
     */
    public function handleTemplateInclude(string $template): string
    {
        $templateType = $this->getTemplateType();

        if ($templateType === null) {
            return $template;
        }

        $controller = $this->findController($templateType);

        if ($controller === null) {
            return $template;
        }

        /** @var TemplateControllerInterface $instance */
        $instance = get($controller['className']);

        $result = $instance->handle();

        if ($result === null) {
            return $template;
        }

        // Output the result and prevent WordPress from loading the template
        echo $result;

        // Return empty string to prevent WordPress from including the template
        return '';
    }

    /**
     * Get the current WordPress template type.
     *
     * Maps WordPress conditionals to template names.
     *
     * @return string|null Template type or null if not determinable
     */
    private function getTemplateType(): ?string
    {
        // Specific templates first (most specific to least specific)
        if (is_404()) {
            return '404';
        }

        if (is_search()) {
            return 'search';
        }

        if (is_front_page()) {
            return 'front-page';
        }

        if (is_home()) {
            return 'home';
        }

        if (is_singular()) {
            $postType = get_post_type();

            if (is_single()) {
                // Check for specific post slug template
                $post = get_queried_object();

                if ($post instanceof \WP_Post) {
                    // single-{post-type}-{slug}
                    $specificTemplate = "single-{$postType}-{$post->post_name}";

                    if ($this->hasController($specificTemplate)) {
                        return $specificTemplate;
                    }
                }

                // single-{post-type}
                if ($postType !== 'post') {
                    $cptTemplate = "single-{$postType}";

                    if ($this->hasController($cptTemplate)) {
                        return $cptTemplate;
                    }
                }

                return 'single';
            }

            if (is_page()) {
                $post = get_queried_object();

                if ($post instanceof \WP_Post) {
                    // page-{slug}
                    $slugTemplate = "page-{$post->post_name}";

                    if ($this->hasController($slugTemplate)) {
                        return $slugTemplate;
                    }

                    // page-{id}
                    $idTemplate = "page-{$post->ID}";

                    if ($this->hasController($idTemplate)) {
                        return $idTemplate;
                    }
                }

                return 'page';
            }

            if (is_attachment()) {
                return 'attachment';
            }

            return 'singular';
        }

        if (is_archive()) {
            if (is_post_type_archive()) {
                $postType = get_query_var('post_type');

                if (is_array($postType)) {
                    $postType = reset($postType);
                }

                return "archive-{$postType}";
            }

            if (is_category()) {
                $category = get_queried_object();

                if ($category instanceof \WP_Term) {
                    $slugTemplate = "category-{$category->slug}";

                    if ($this->hasController($slugTemplate)) {
                        return $slugTemplate;
                    }
                }

                return 'category';
            }

            if (is_tag()) {
                $tag = get_queried_object();

                if ($tag instanceof \WP_Term) {
                    $slugTemplate = "tag-{$tag->slug}";

                    if ($this->hasController($slugTemplate)) {
                        return $slugTemplate;
                    }
                }

                return 'tag';
            }

            if (is_tax()) {
                $term = get_queried_object();

                if ($term instanceof \WP_Term) {
                    // taxonomy-{taxonomy}-{term}
                    $termTemplate = "taxonomy-{$term->taxonomy}-{$term->slug}";

                    if ($this->hasController($termTemplate)) {
                        return $termTemplate;
                    }

                    // taxonomy-{taxonomy}
                    $taxTemplate = "taxonomy-{$term->taxonomy}";

                    if ($this->hasController($taxTemplate)) {
                        return $taxTemplate;
                    }
                }

                return 'taxonomy';
            }

            if (is_author()) {
                return 'author';
            }

            if (is_date()) {
                return 'date';
            }

            return 'archive';
        }

        return 'index';
    }

    /**
     * Check if a controller exists for a template.
     *
     * @param string $template Template name
     * @return bool
     */
    private function hasController(string $template): bool
    {
        return $this->findController($template) !== null;
    }

    /**
     * Find the controller for a template.
     *
     * @param string $template Template name
     * @return array<string, mixed>|null
     */
    private function findController(string $template): ?array
    {
        // Exact match
        if (isset($this->controllers[$template])) {
            return $this->controllers[$template];
        }

        // Wildcard matches
        foreach ($this->wildcardControllers as $pattern => $controller) {
            if (!$this->matchesPattern($template, $pattern)) {
                continue;
            }

            return $controller;
        }

        return null;
    }

    /**
     * Check if a template matches a wildcard pattern.
     *
     * @param string $template Template name
     * @param string $pattern Pattern with * wildcards
     * @return bool
     */
    private function matchesPattern(string $template, string $pattern): bool
    {
        $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/';

        return (bool) preg_match($regex, $template);
    }

    /**
     * Convert a discovered item to a cacheable format.
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    protected function itemToCacheable(array $item): array
    {
        /** @var AsTemplateController $attribute */
        $attribute = $item['attribute'];

        return [
            'templates' => $attribute->getTemplates(),
            'className' => $item['className'],
            'priority' => $attribute->priority,
        ];
    }
}
