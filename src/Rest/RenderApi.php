<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Rest;

use Studiometa\Foehn\Config\RenderApiConfig;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST API endpoint for rendering Twig templates.
 *
 * Provides a cacheable endpoint for AJAX partial loading.
 * Only scalar values are passed to the template context.
 * Use ContextProviders to resolve IDs to objects.
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
            ],
        ]);
    }

    /**
     * Handle the render request.
     */
    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        /** @var string|null $template */
        $template = $request->get_param('template');
        /** @var mixed $templates */
        $templates = $request->get_param('templates');

        // Must have either template or templates
        if ($template === null && $templates === null) {
            return $this->errorResponse('missing_template', 'Either template or templates parameter is required', 400);
        }

        // Validate templates format if provided
        if ($templates !== null && !$this->isValidTemplatesParam($templates)) {
            return $this->errorResponse(
                'invalid_templates',
                'The templates parameter must be an object with string values',
                400,
            );
        }

        // Build context from request parameters (scalar values only)
        $context = $this->buildContext($request);

        // Single template mode
        if ($template !== null) {
            return $this->handleSingleTemplate($template, $context);
        }

        // Multiple templates mode
        /** @var array<string, string> $templates */
        return $this->handleMultipleTemplates($templates, $context);
    }

    /**
     * Validate that templates parameter is an associative array of strings.
     */
    private function isValidTemplatesParam(mixed $templates): bool
    {
        if (!is_array($templates)) {
            return false;
        }

        foreach ($templates as $key => $value) {
            if (!(!is_string($key) || !is_string($value))) {
                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * Handle single template rendering.
     *
     * @param array<string, mixed> $context
     */
    private function handleSingleTemplate(string $template, array $context): WP_REST_Response
    {
        if (!$this->config->isTemplateAllowed($template)) {
            return $this->errorResponse('template_not_allowed', 'Template not allowed', 403);
        }

        try {
            $html = $this->view->render($template, $context);
        } catch (\Throwable $e) {
            return $this->renderErrorResponse('Template rendering failed', $e);
        }

        return $this->successResponse(['html' => $html]);
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
                return $this->errorResponse('template_not_allowed', "Template '{$key}' not allowed", 403);
            }

            try {
                $results[$key] = $this->view->render($template, $context);
            } catch (\Throwable $e) {
                return $this->renderErrorResponse("Template '{$key}' rendering failed", $e);
            }
        }

        return $this->successResponse($results);
    }

    /**
     * Build context from request parameters.
     *
     * Only scalar values are included. Use ContextProviders to resolve
     * IDs (post_id, term_id, etc.) to Timber objects.
     *
     * @return array<string, scalar>
     */
    private function buildContext(WP_REST_Request $request): array
    {
        $context = [];

        foreach ($request->get_params() as $key => $value) {
            // Skip reserved parameters
            if (in_array($key, ['template', 'templates'], true)) {
                continue;
            }

            // Only allow scalar values
            if (is_scalar($value)) {
                $context[$key] = $value;
            }
        }

        return $context;
    }

    /**
     * Create a success response with cache headers.
     *
     * @param array<string, mixed> $data
     */
    private function successResponse(array $data): WP_REST_Response
    {
        $response = new WP_REST_Response($data);

        if ($this->config->cacheMaxAge > 0) {
            $response->header('Cache-Control', "public, max-age={$this->config->cacheMaxAge}");
        }

        return $response;
    }

    /**
     * Create an error response.
     */
    private function errorResponse(string $code, string $message, int $status): WP_REST_Response
    {
        return new WP_REST_Response(['code' => $code, 'message' => $message], $status);
    }

    /**
     * Create a render error response, optionally with debug details.
     */
    private function renderErrorResponse(string $message, \Throwable $exception): WP_REST_Response
    {
        if ($this->config->debug) {
            $message .= ': ' . $exception->getMessage();
        }

        return $this->errorResponse('render_error', $message, 500);
    }
}
