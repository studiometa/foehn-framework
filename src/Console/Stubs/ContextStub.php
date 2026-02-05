<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Stubs;

use Studiometa\Foehn\Attributes\AsViewComposer;
use Studiometa\Foehn\Contracts\ViewComposerInterface;
use Tempest\Discovery\SkipDiscovery;

/**
 * DummyContext - Context provider for templates.
 *
 * Context providers (view composers) add data to matching templates.
 * Use template patterns to target specific templates:
 * - 'single' - Exact match
 * - 'single-*' - Wildcard match
 * - '*' - All templates (global context)
 */
#[SkipDiscovery]
#[AsViewComposer(templates: ['dummy-template', 'dummy-template-*'])]
final class ContextStub implements ViewComposerInterface
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
        // This data will be available in your Twig templates

        // Example: Add site information
        $context['site_info'] = [
            'name' => get_bloginfo('name'),
            'description' => get_bloginfo('description'),
            'url' => home_url(),
        ];

        // Example: Add current year for copyright
        $context['current_year'] = date('Y');

        // Example: Add navigation menu
        // $context['menu'] = Timber::get_menu('primary');

        // Example: Add options from ACF
        // $context['options'] = [
        //     'phone' => get_field('phone_number', 'option'),
        //     'email' => get_field('contact_email', 'option'),
        // ];

        return $context;
    }
}
