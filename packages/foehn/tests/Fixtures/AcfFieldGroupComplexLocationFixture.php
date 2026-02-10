<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use StoutLogic\AcfBuilder\FieldsBuilder;
use Studiometa\Foehn\Attributes\AsAcfFieldGroup;
use Studiometa\Foehn\Contracts\AcfFieldGroupInterface;

#[AsAcfFieldGroup(
    name: 'complex_fields',
    title: 'Complex Fields',
    location: [
        [
            ['param' => 'post_type', 'operator' => '==', 'value' => 'product'],
            ['param' => 'post_status', 'operator' => '!=', 'value' => 'draft'],
        ],
        [
            ['param' => 'page_template', 'operator' => '==', 'value' => 'page-shop.php'],
        ],
    ],
)]
final class AcfFieldGroupComplexLocationFixture implements AcfFieldGroupInterface
{
    public static function fields(): FieldsBuilder
    {
        $builder = new FieldsBuilder('complex_fields');
        $builder->addText('test_field');

        return $builder;
    }
}
