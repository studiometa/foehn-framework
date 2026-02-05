<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use StoutLogic\AcfBuilder\FieldsBuilder;
use Studiometa\Foehn\Attributes\AsAcfFieldGroup;
use Studiometa\Foehn\Contracts\AcfFieldGroupInterface;

#[AsAcfFieldGroup(
    name: 'property_fields',
    title: 'Property Details',
    location: ['post_type' => 'property'],
    position: 'acf_after_title',
    menuOrder: 0,
    style: 'seamless',
    labelPlacement: 'left',
    instructionPlacement: 'field',
    hideOnScreen: ['the_content', 'excerpt'],
)]
final class AcfFieldGroupFixture implements AcfFieldGroupInterface
{
    public static function fields(): FieldsBuilder
    {
        $builder = new FieldsBuilder('property_fields');
        $builder
            ->addText('external_id', ['label' => 'External ID'])
            ->addWysiwyg('description', ['label' => 'Description']);

        return $builder;
    }
}
