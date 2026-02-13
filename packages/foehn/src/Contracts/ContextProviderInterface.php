<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Contracts;

use Studiometa\Foehn\Views\TemplateContext;

/**
 * Interface for context providers.
 *
 * Context providers allow adding data to specific templates automatically.
 * They are called before each matching template is rendered.
 */
interface ContextProviderInterface
{
    /**
     * Provide additional data for the view context.
     *
     * @param TemplateContext $context Current template context
     * @return TemplateContext Modified context with additional data
     */
    public function provide(TemplateContext $context): TemplateContext;
}
