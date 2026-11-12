<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use Studiometa\Foehn\Attributes\AsDiscovery;
use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Contracts\TemplateControllerInterface;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Studiometa\Foehn\Trace\RequestTrace;
use Studiometa\Foehn\Views\Sections\SectionCollector;
use Studiometa\Foehn\Views\Sections\SectionNotFoundException;
use Studiometa\Foehn\Views\Sections\SectionRenderer;
use Studiometa\Foehn\Views\Sections\SectionRequest;
use Studiometa\Foehn\Views\Sections\SectionResponse;
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

    private TemplateControllerMap $controllers;

    public function __construct(
        private readonly SectionRequest $sectionRequest,
        private readonly SectionCollector $sectionCollector,
        private readonly SectionResponse $sectionResponse,
        private readonly DiscoveryProblems $problems,
        private readonly RequestTrace $requestTrace,
    ) {
        $this->controllers = TemplateControllerMap::fromItems([]);
    }

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

        if (!$class->implements(TemplateControllerInterface::class)) {
            $this->problems->report(
                $location,
                $class->getName(),
                '#[AsTemplateController]',
                sprintf(
                    '%s has #[AsTemplateController] but does not implement %s, so it handles no template.',
                    $class->getName(),
                    TemplateControllerInterface::class,
                ),
                sprintf('Declare %s with `implements \\%s`.', $class->getName(), TemplateControllerInterface::class),
            );

            return;
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
        $this->controllers = TemplateControllerMap::fromItems($this->getItems());

        // Hook into WordPress template_include filter
        add_filter('template_include', [$this, 'handleTemplateInclude'], 5);
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
            return $this->sectionResponse->error($this->sectionRequest->errorStatus());
        }

        $templateType = $this->getTemplateType();
        $controller = $this->controllers->find($templateType);

        // What an error entry and the request trace say this request rendered.
        $this->requestTrace->matchTemplate($templateType, $controller['className'] ?? null);

        if ($controller === null) {
            return $this->sectionRequest->isSelected() ? $this->sectionResponse->error(404) : $template;
        }

        /** @var TemplateControllerInterface $instance */
        $instance = get($controller['className']);

        if ($this->sectionRequest->isSelected()) {
            // Only the rendering runs with the control parameter hidden. Sending the
            // response must not, because deciding whether it may be cached means asking
            // `Bypass`, and `Bypass` recognises a section by the request URI — the very
            // thing this hides. Inside the callback it saw a page, applied the page's
            // body rule and answered `no-store` for responses the recorder then stored.
            $rendered = $this->sectionRequest->withoutControlParameter(
                fn(): string|int => $this->renderSelectedSections($instance),
            );

            return is_int($rendered)
                ? $this->sectionResponse->error($rendered)
                : $this->sectionResponse->send($rendered);
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
     * Run the normal controller and page template and return the selected sections.
     *
     * An `int` is a status to answer with instead — the response itself is built by the
     * caller, outside the window where the control parameter is hidden.
     */
    private function renderSelectedSections(TemplateControllerInterface $controller): string|int
    {
        $bufferLevel = ob_get_level();
        ob_start();

        try {
            $context = TemplateContext::fromTimberContext(Timber::context());
            $page = $controller->handle($context);

            if ($page === null) {
                $this->discardBuffersSince($bufferLevel);

                return 404;
            }

            /** @var SectionRenderer $renderer */
            $renderer = get(SectionRenderer::class);
            $html = $renderer->renderSelected($this->sectionRequest->names(), $this->sectionCollector);
        } catch (SectionNotFoundException) {
            $this->discardBuffersSince($bufferLevel);

            return 404;
        } catch (\Throwable $throwable) {
            $this->discardBuffersSince($bufferLevel);
            error_log('[foehn] section rendering: ' . $throwable->getMessage());

            return 500;
        }

        $this->discardBuffersSince($bufferLevel);

        return $html;
    }

    private function discardBuffersSince(int $level): void
    {
        while (ob_get_level() > $level) {
            if (!ob_end_clean()) {
                break;
            }
        }
    }

    /**
     * Get the current WordPress template type.
     *
     * Maps WordPress conditionals to template names.
     *
     * @return string Template type
     */
    private function getTemplateType(): string
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
            $post = get_queried_object();
            $post = $post instanceof \WP_Post ? $post : null;

            if (is_single()) {
                return $this->controllers->resolve(TemplateNames::single((string) get_post_type(), $post?->post_name));
            }

            if (is_page()) {
                return $this->controllers->resolve(TemplateNames::page($post?->post_name, $post?->ID));
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

            $term = get_queried_object();
            $term = $term instanceof \WP_Term ? $term : null;

            if (is_category()) {
                return $this->controllers->resolve(TemplateNames::category($term?->slug));
            }

            if (is_tag()) {
                return $this->controllers->resolve(TemplateNames::tag($term?->slug));
            }

            if (is_tax()) {
                return $this->controllers->resolve(TemplateNames::taxonomy($term?->taxonomy, $term?->slug));
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
}
