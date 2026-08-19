<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use StoutLogic\AcfBuilder\FieldsBuilder;
use Studiometa\Foehn\Attributes\AsAcfBlock;
use Studiometa\Foehn\Contracts\AcfBlockInterface;

#[AsAcfBlock(name: 'slide', title: 'Slide', postTypes: ['page', 'product'], parent: 'acf/slider')]
final class ConstrainedAcfBlockFixture implements AcfBlockInterface
{
    public static function fields(): FieldsBuilder
    {
        return new FieldsBuilder('slide');
    }

    public function compose(array $block, array $fields): array
    {
        return $fields;
    }

    public function render(array $context, bool $isPreview = false): string
    {
        return '<div>Slide</div>';
    }
}
