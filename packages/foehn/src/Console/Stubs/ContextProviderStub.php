<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Stubs;

use Studiometa\Foehn\Attributes\AsContextProvider;
use Studiometa\Foehn\Contracts\ContextProviderInterface;
use Studiometa\Foehn\Views\TemplateContext;
use Tempest\Discovery\SkipDiscovery;

#[SkipDiscovery]
#[AsContextProvider(templates: ['dummy-template', 'dummy-template-*'])]
final class ContextProviderStub implements ContextProviderInterface
{
    /**
     * Provide additional data for the view context.
     *
     * @param TemplateContext $context Current template context
     * @return TemplateContext Modified context
     */
    public function provide(TemplateContext $context): TemplateContext
    {
        // Add your custom data to the context (immutable)
        return $context->with('custom_data', [
            'site_name' => get_bloginfo('name'),
            'current_year' => date('Y'),
        ]);

        // Example: Add recent posts
        // return $context->with('recent_posts', Timber::get_posts([
        //     'post_type' => 'post',
        //     'posts_per_page' => 5,
        // ]));
    }
}
