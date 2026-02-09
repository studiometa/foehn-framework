<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Hooks;

use Studiometa\Foehn\Attributes\AsAction;
use Studiometa\Foehn\Rest\RenderApi;

/**
 * Opt-in hook to register the Render API REST endpoint.
 *
 * 1. Add this class to your hooks configuration:
 *
 * ```php
 * // functions.php
 * Kernel::boot(__DIR__, [
 *     'hooks' => [
 *         RenderApiHook::class,
 *     ],
 * ]);
 * ```
 *
 * 2. Create a config file to define allowed templates:
 *
 * ```php
 * // app/render-api.config.php
 * use Studiometa\Foehn\Config\RenderApiConfig;
 *
 * return new RenderApiConfig(
 *     templates: ['partials/*', 'components/*'],
 * );
 * ```
 */
final readonly class RenderApiHook
{
    public function __construct(
        private RenderApi $renderApi,
    ) {}

    #[AsAction('rest_api_init')]
    public function register(): void
    {
        $this->renderApi->register();
    }
}
