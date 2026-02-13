<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use InvalidArgumentException;
use ReflectionClass;
use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Contracts\TemplateControllerInterface;
use Studiometa\Foehn\Discovery\Concerns\CacheableDiscovery;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Studiometa\Foehn\Views\TemplateContext;
use Timber\Site;
use Timber\Timber;

use function Tempest\Container\get;

/**
 * Discovers classes marked with #[AsTemplateController] attribute
 * and registers them to intercept WordPress template rendering.
 */
final class TemplateControllerDiscovery implements WpDiscovery
{
    use IsWpDiscovery;
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
     *
     * @param DiscoveryLocation $location
     * @param ReflectionClass<object> $class
     */
    public function discover(DiscoveryLocation $location, ReflectionClass $class): void
    {
        $attributes = $class->getAttributes(AsTemplateController::class);

        if ($attributes === []) {
            return;
        }

        // Verify the class implements TemplateControllerInterface
        if (!$class->implementsInterface(TemplateControllerInterface::class)) {
            throw new InvalidArgumentException(sprintf(
                'Class %s must implement %s to use #[AsTemplateController]',
                $class->getName(),
                TemplateControllerInterface::class,
            ));
        }

        $attribute = $attributes[0]->newInstance();

        $this->addItem($location, [
            'templates' => $attribute->getTemplates(),
            'className' => $class->getName(),
            'priority' => $attribute->priority,
        ]);
    }

    /**
     * Apply discovered template controllers by registering them.
     */
    public function apply(): void
    {
        // Build controller maps
        foreach ($this->getItems() as $item) {
            $this->addController($item['templates'], $item['className'], $item['priority']);
        }

        // Hook into WordPress template_include filter
        add_filter('template_include', [$this, 'handleTemplateInclude'], 5);
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

                continue;
            }

            $this->controllers[$template] = $entry;
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

        $timberContext = Timber::context();
        $context = new TemplateContext(
            post: $timberContext['post'] ?? null,
            posts: $timberContext['posts'] ?? null,
            site: $timberContext['site'] ?? new Site(),
            user: $timberContext['user'] ?? null,
            extra: array_diff_key($timberContext, array_flip(['post', 'posts', 'site', 'user'])),
        );
        $result = $instance->handle($context);

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
        return [
            'templates' => $item['templates'],
            'className' => $item['className'],
            'priority' => $item['priority'],
        ];
    }
}
