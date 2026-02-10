<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Stubs;

use Studiometa\Foehn\Attributes\AsContextProvider;
use Studiometa\Foehn\Contracts\ContextProviderInterface;
use Tempest\Discovery\SkipDiscovery;

#[SkipDiscovery]
#[AsContextProvider(templates: ['dummy-template', 'dummy-template-*'])]
final class ContextProviderStub implements ContextProviderInterface
{
    /**
     * Provide additional data for the view context.
     *
     * @param array<string, mixed> $context Current Timber context
     * @return array<string, mixed> Modified context
     */
    public function provide(array $context): array
    {
        // Add your custom data to the context
        $context['custom_data'] = [
            'site_name' => get_bloginfo('name'),
            'current_year' => date('Y'),
        ];

        // Example: Add recent posts
        // $context['recent_posts'] = Timber::get_posts([
        //     'post_type' => 'post',
        //     'posts_per_page' => 5,
        // ]);

        return $context;
    }
}
