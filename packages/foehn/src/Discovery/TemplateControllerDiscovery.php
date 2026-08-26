<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use InvalidArgumentException;
use Studiometa\Foehn\Attributes\AsDiscovery;
use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Contracts\TemplateControllerInterface;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Studiometa\Foehn\Views\Sections\SectionCollector;
use Studiometa\Foehn\Views\Sections\SectionNotFoundException;
use Studiometa\Foehn\Views\Sections\SectionRenderer;
use Studiometa\Foehn\Views\Sections\SectionRequest;
use Studiometa\Foehn\Views\TemplateContext;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;
use Timber\Timber;

use function Tempest\Container\get;

/**
 * Discovers classes marked with #[AsTemplateController] attribute
 * and registers them to intercept WordPress template rendering.
 */
#[AsDiscovery(phase: DiscoveryPhase::Late)]
final class TemplateControllerDiscovery implements Discovery
{
    use IsWpDiscovery;

    /**
     * @var array<string, array{className: class-string, priority: int}>
     */
    private array $controllers = [];

    /**
     * @var array<string, array{className: class-string, priority: int}>
     */
    private array $wildcardControllers = [];

    public function __construct(
        private readonly SectionRequest $sectionRequest,
        private readonly SectionCollector $sectionCollector,
    ) {}

    /**
     * Discover template controller attributes on classes.
     *
     * @param DiscoveryLocation $location
     * @param ClassReflector $class
     */
    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        if (!$this->isConcrete($class)) {
            return;
        }

        $attribute = $class->getAttribute(AsTemplateController::class);

        if ($attribute === null) {
            return;
        }

        // Verify the class implements TemplateControllerInterface
        if (!$class->implements(TemplateControllerInterface::class)) {
            throw new InvalidArgumentException(sprintf(
                'Class %s must implement %s to use #[AsTemplateController]',
                $class->getName(),
                TemplateControllerInterface::class,
            ));
        }

        $this->addItem($location, [
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
        foreach ($this->getItems() as $item) {
            /** @var AsTemplateController $attribute */
            $attribute = $item['attribute'];

            $this->addController($attribute->getTemplates(), $item['className'], $attribute->priority);
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
        if ($this->sectionRequest->isSelected() && !$this->sectionRequest->isValid()) {
            return $this->emitSectionError($this->sectionRequest->errorStatus());
        }

        $templateType = $this->getTemplateType();

        if ($templateType === null) {
            return $this->sectionRequest->isSelected() ? $this->emitSectionError(404) : $template;
        }

        $controller = $this->findController($templateType);

        if ($controller === null) {
            return $this->sectionRequest->isSelected() ? $this->emitSectionError(404) : $template;
        }

        /** @var TemplateControllerInterface $instance */
        $instance = get($controller['className']);

        if ($this->sectionRequest->isSelected()) {
            return $this->handleSectionRequest($instance);
        }

        $context = TemplateContext::fromTimberContext(Timber::context());
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
     * Run the normal controller and page template, then emit only its selected sections.
     */
    private function handleSectionRequest(TemplateControllerInterface $controller): string
    {
        $bufferLevel = ob_get_level();
        ob_start();

        try {
            $context = TemplateContext::fromTimberContext(Timber::context());
            $page = $controller->handle($context);

            if ($page === null) {
                $this->discardBuffersSince($bufferLevel);

                return $this->emitSectionError(404);
            }

            /** @var SectionRenderer $renderer */
            $renderer = get(SectionRenderer::class);
            $html = $renderer->renderSelected($this->sectionRequest->names(), $this->sectionCollector);
        } catch (SectionNotFoundException) {
            $this->discardBuffersSince($bufferLevel);

            return $this->emitSectionError(404);
        } catch (\Throwable $throwable) {
            $this->discardBuffersSince($bufferLevel);
            error_log('[foehn] section rendering: ' . $throwable->getMessage());

            return $this->emitSectionError(500);
        }

        $this->discardBuffersSince($bufferLevel);
        $this->emitSectionResponse($html, 200);

        return '';
    }

    private function discardBuffersSince(int $level): void
    {
        while (ob_get_level() > $level) {
            ob_end_clean();
        }
    }

    private function emitSectionError(int $status): string
    {
        $title = match ($status) {
            400 => 'Invalid section request',
            405 => 'Method not allowed',
            404 => 'Section not found',
            default => 'Unable to render sections',
        };
        $body = sprintf(
            '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>%1$s</title></head><body><h1>%1$s</h1></body></html>',
            $title,
        );

        $this->emitSectionResponse($body, $status);

        return '';
    }

    private function emitSectionResponse(string $body, int $status): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: text/html; charset=UTF-8', true);
            header('Cache-Control: private, no-store', true);
            header('X-Robots-Tag: noindex, nofollow', true);

            if ($status === 405) {
                header('Allow: GET, HEAD', true);
            }
        }

        if (!$this->sectionRequest->isHead()) {
            echo $body;
        }
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
        if (($this->controllers[$template] ?? null) !== null) {
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
}
