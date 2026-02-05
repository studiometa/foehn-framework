<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Stubs;

use StoutLogic\AcfBuilder\FieldsBuilder;
use Studiometa\Foehn\Attributes\AsAcfFieldGroup;
use Studiometa\Foehn\Contracts\AcfFieldGroupInterface;
use Tempest\Discovery\SkipDiscovery;

#[SkipDiscovery]
#[AsAcfFieldGroup(
    name: 'dummy_field_group',
    title: 'Dummy Field Group',
    location: ['post_type' => 'post'],
    position: 'normal',
    style: 'default',
)]
final class FieldGroupStub implements AcfFieldGroupInterface
{
    /**
     * Define the field group fields using ACF Builder.
     */
    public static function fields(): FieldsBuilder
    {
        $fields = new FieldsBuilder('dummy_field_group');

        $fields
            ->addText('subtitle', [
                'label' => 'Subtitle',
                'instructions' => 'Enter a subtitle for this content.',
            ])
            ->addWysiwyg('additional_content', [
                'label' => 'Additional Content',
                'media_upload' => false,
                'tabs' => 'visual',
            ])
            ->addImage('featured_image', [
                'label' => 'Featured Image',
                'return_format' => 'id',
                'preview_size' => 'medium',
            ]);

        return $fields;
    }
}
