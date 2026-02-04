<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Contracts;

/**
 * Interface for template controllers.
 *
 * Template controllers handle the rendering of specific WordPress templates.
 * They provide full control over template resolution and context building.
 */
interface TemplateControllerInterface
{
    /**
     * Handle the template request.
     *
     * This method is called when WordPress would render a matching template.
     * It should return the rendered HTML or null to let WordPress handle it.
     *
     * @return string|null Rendered HTML or null to pass through
     */
    public function handle(): ?string;
}
