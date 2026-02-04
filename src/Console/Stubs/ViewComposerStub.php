<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Console\Stubs;

use Studiometa\WPTempest\Attributes\AsViewComposer;
use Studiometa\WPTempest\Contracts\ViewComposerInterface;
use Tempest\Discovery\SkipDiscovery;

#[SkipDiscovery]
#[AsViewComposer(templates: ['dummy-template', 'dummy-template-*'])]
final class ViewComposerStub implements ViewComposerInterface
{
    /**
     * Compose additional data for the view.
     *
     * @param array<string, mixed> $context Current Timber context
     * @return array<string, mixed> Modified context
     */
    public function compose(array $context): array
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
