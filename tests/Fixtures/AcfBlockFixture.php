<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use StoutLogic\AcfBuilder\FieldsBuilder;
use Studiometa\WPTempest\Attributes\AsAcfBlock;
use Studiometa\WPTempest\Contracts\AcfBlockInterface;

#[AsAcfBlock(
    name: 'testimonial',
    title: 'Testimonial',
    description: 'A testimonial block.',
    category: 'formatting',
    icon: 'format-quote',
    keywords: ['quote', 'testimonial'],
)]
final class AcfBlockFixture implements AcfBlockInterface
{
    public static function fields(): FieldsBuilder
    {
        $builder = new FieldsBuilder('testimonial');
        $builder->addText('quote');

        return $builder;
    }

    public function compose(array $block, array $fields): array
    {
        return $fields;
    }

    public function render(array $context, bool $isPreview = false): string
    {
        return '<blockquote>Testimonial</blockquote>';
    }
}
