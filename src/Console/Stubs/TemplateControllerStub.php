<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Stubs;

use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Contracts\TemplateControllerInterface;
use Tempest\Discovery\SkipDiscovery;
use Timber\Timber;

#[SkipDiscovery]
#[AsTemplateController(templates: 'dummy-template')]
final class TemplateControllerStub implements TemplateControllerInterface
{
    /**
     * Handle the template request.
     *
     * @return string|null Rendered HTML or null to pass through
     */
    public function handle(): ?string
    {
        $context = Timber::context();

        // Add your custom context data here
        // $context['post'] = Timber::get_post();
        // $context['custom_data'] = $this->loadCustomData();

        $result = Timber::compile('dummy-template.twig', $context);

        return is_string($result) ? $result : null;
    }
}
