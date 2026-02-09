<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Rest;

use Studiometa\Foehn\Config\RenderApiConfig;
use Studiometa\Foehn\Contracts\ContentResolverInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST API endpoint for rendering Twig templates.
 *
 * Provides a cacheable endpoint for AJAX partial loading.
 */
final readonly class RenderApi
{
    public const NAMESPACE = 'foehn/v1';
    public const ROUTE = '/render';

    public function __construct(
        private ViewEngineInterface $view,
        private RenderApiConfig $config,
        private ContentResolverInterface $contentResolver,
    ) {}

    /**
     * Register the REST route.
     */
    public function register(): void
    {
        if (!$this->config->enabled) {
            return;
        }

        add_action('rest_api_init', function (): void {
            register_rest_route(self::NAMESPACE, self::ROUTE, [
                'methods' => 'GET',
                'callback' => $this->handle(...),
                'permission_callback' => '__return_true',
                'args' => [
                    'template' => [
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'templates' => [
                        'type' => 'object',
                    ],
                    'post_id' => [
                        'type' => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                    'term_id' => [
                        'type' => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                    'taxonomy' => [
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_key',
                    ],
                ],
            ]);
        });
    }

    /**
     * Handle the render request.
     */
    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        /** @var string|null $template */
        $template = $request->get_param('template');
        /** @var array<string, string>|null $templates */
        $templates = $request->get_param('templates');

        // Must have either template or templates
        if ($template === null && $templates === null) {
            return new WP_REST_Response([
                'code' => 'missing_template',
                'message' => 'Either template or templates parameter is required',
            ], 400);
        }

        // Build context from request parameters
        $context = $this->buildContext($request);

        if ($context === null) {
            return new WP_REST_Response(['code' => 'invalid_context', 'message' => 'Resource not found'], 404);
        }

        // Single template mode
        if ($template !== null) {
            return $this->handleSingleTemplate($template, $context);
        }

        // Multiple templates mode
        return $this->handleMultipleTemplates($templates, $context);
    }

    /**
     * Handle single template rendering.
     *
     * @param array<string, mixed> $context
     */
    private function handleSingleTemplate(string $template, array $context): WP_REST_Response
    {
        if (!$this->config->isTemplateAllowed($template)) {
            return new WP_REST_Response(['code' => 'template_not_allowed', 'message' => 'Template not found'], 404);
        }

        try {
            $html = $this->view->render($template, $context);
        } catch (\Throwable) {
            return new WP_REST_Response(['code' => 'render_error', 'message' => 'Template not found'], 404);
        }

        return new WP_REST_Response(['html' => $html]);
    }

    /**
     * Handle multiple templates rendering.
     *
     * @param array<string, string> $templates
     * @param array<string, mixed> $context
     */
    private function handleMultipleTemplates(array $templates, array $context): WP_REST_Response
    {
        $results = [];

        foreach ($templates as $key => $template) {
            // Sanitize template path
            $template = sanitize_text_field($template);

            if (!$this->config->isTemplateAllowed($template)) {
                return new WP_REST_Response([
                    'code' => 'template_not_allowed',
                    'message' => "Template '{$key}' not found",
                ], 404);
            }

            try {
                $results[$key] = $this->view->render($template, $context);
            } catch (\Throwable) {
                return new WP_REST_Response([
                    'code' => 'render_error',
                    'message' => "Template '{$key}' rendering failed",
                ], 404);
            }
        }

        return new WP_REST_Response($results);
    }

    /**
     * Build context from request parameters.
     *
     * @return array<string, mixed>|null Null if a referenced resource doesn't exist
     */
    private function buildContext(WP_REST_Request $request): ?array
    {
        $context = [];

        // Resolve post_id to Timber Post
        /** @var int|null $postId */
        $postId = $request->get_param('post_id');
        if ($postId !== null) {
            $post = $this->contentResolver->resolvePost($postId);

            if ($post === null) {
                return null;
            }

            $context['post'] = $post;
        }

        // Resolve term_id to Timber Term
        /** @var int|null $termId */
        $termId = $request->get_param('term_id');
        if ($termId !== null) {
            /** @var string|null $taxonomy */
            $taxonomy = $request->get_param('taxonomy');
            $term = $this->contentResolver->resolveTerm($termId, $taxonomy ?? 'category');

            if ($term === null) {
                return null;
            }

            $context['term'] = $term;
        }

        // Add remaining scalar parameters to context
        foreach ($request->get_params() as $key => $value) {
            // Skip reserved parameters
            if (in_array($key, ['template', 'templates', 'post_id', 'term_id', 'taxonomy'], true)) {
                continue;
            }

            // Only allow scalar values
            if (is_scalar($value)) {
                $context[$key] = $value;
            }
        }

        return $context;
    }
}
