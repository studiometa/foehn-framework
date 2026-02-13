<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Stubs;

use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Contracts\TemplateControllerInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Studiometa\Foehn\Views\TemplateContext;
use Tempest\Discovery\SkipDiscovery;

#[SkipDiscovery]
#[AsTemplateController(templates: 'dummy-template')]
final readonly class TemplateControllerStub implements TemplateControllerInterface
{
    public function __construct(
        private ViewEngineInterface $view,
    ) {}

    /**
     * Handle the template request.
     *
     * @param TemplateContext $context Typed Timber context
     * @return string|null Rendered HTML or null to pass through
     */
    public function handle(TemplateContext $context): ?string
    {
        // Access typed properties
        // $post = $context->post;
        // $site = $context->site;

        // Cast to specific post type
        // $product = $context->post(Product::class);

        // Add custom data (immutable)
        // $context = $context->with('custom_data', $this->loadCustomData());

        return $this->view->render('dummy-template', $context);
    }
}
