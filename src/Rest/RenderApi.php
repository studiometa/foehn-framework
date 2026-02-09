<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Rest;

use Studiometa\Foehn\Config\RenderApiConfig;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Timber\Post;
use Timber\Term;
use Timber\Timber;
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
                        'required' => true,
                        'sanitize_callback' => 'sanitize_text_field',
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
        $template = $request->get_param('template');

        // Validate template against allowlist
        if (!$this->config->isTemplateAllowed($template)) {
            return new WP_REST_Response(['code' => 'template_not_allowed', 'message' => 'Template not found'], 404);
        }

        // Build context from request parameters
        $context = $this->buildContext($request);

        if ($context === null) {
            return new WP_REST_Response(['code' => 'invalid_context', 'message' => 'Resource not found'], 404);
        }

        // Render the template
        try {
            $html = $this->view->render($template, $context);
        } catch (\Throwable) {
            return new WP_REST_Response(['code' => 'render_error', 'message' => 'Template not found'], 404);
        }

        return new WP_REST_Response(['html' => $html]);
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
        $postId = $request->get_param('post_id');
        if ($postId !== null) {
            $post = Timber::get_post($postId);

            // Only allow published/public posts
            if (!$post instanceof Post || $post->post_status !== 'publish') {
                return null;
            }

            $context['post'] = $post;
        }

        // Resolve term_id to Timber Term
        $termId = $request->get_param('term_id');
        if ($termId !== null) {
            $taxonomy = $request->get_param('taxonomy') ?? 'category';
            $term = Timber::get_term($termId, $taxonomy);

            if (!$term instanceof Term) {
                return null;
            }

            $context['term'] = $term;
        }

        // Add remaining scalar parameters to context
        foreach ($request->get_params() as $key => $value) {
            // Skip reserved parameters
            if (in_array($key, ['template', 'post_id', 'term_id', 'taxonomy'], true)) {
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
