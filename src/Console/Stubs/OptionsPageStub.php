<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Stubs;

use StoutLogic\AcfBuilder\FieldsBuilder;
use Studiometa\Foehn\Attributes\AsOptionsPage;
use Tempest\Discovery\SkipDiscovery;

#[SkipDiscovery]
#[AsOptionsPage(
    pageTitle: 'Dummy Options',
    menuTitle: 'Dummy Options',
    menuSlug: 'dummy-options',
    capability: 'edit_posts',
    parentSlug: '',
    iconUrl: 'dashicons-admin-generic',
)]
final class OptionsPageStub
{
    /**
     * Define the options page fields using ACF Builder.
     */
    public static function fields(): FieldsBuilder
    {
        $fields = new FieldsBuilder('dummy_options');

        $fields
            ->addTab('general', ['label' => 'General'])
            ->addText('company_name', [
                'label' => 'Company Name',
                'instructions' => 'Enter the company name.',
            ])
            ->addEmail('contact_email', [
                'label' => 'Contact Email',
                'instructions' => 'Enter the main contact email address.',
            ])
            ->addTab('social', ['label' => 'Social Media'])
            ->addUrl('facebook_url', [
                'label' => 'Facebook URL',
            ])
            ->addUrl('twitter_url', [
                'label' => 'Twitter URL',
            ])
            ->addUrl('instagram_url', [
                'label' => 'Instagram URL',
            ]);

        return $fields;
    }

    /**
     * Helper to get an option value.
     *
     * @template T
     * @param string $key The option field name
     * @param T $default Default value if option not found
     * @return T|mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = get_field($key, 'option');

        return $value !== null && $value !== false ? $value : $default;
    }
}
