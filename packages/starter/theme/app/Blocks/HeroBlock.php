<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Data\HeroContext;
use StoutLogic\AcfBuilder\FieldsBuilder;
use Studiometa\Foehn\Acf\Fragments\ButtonLinkBuilder;
use Studiometa\Foehn\Attributes\AsAcfBlock;
use Studiometa\Foehn\Contracts\AcfBlockInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Studiometa\Foehn\Data\ImageData;
use Studiometa\Foehn\Data\LinkData;

/**
 * Hero banner block with typed DTO context.
 *
 * Demonstrates how to use Arrayable DTOs instead of plain arrays
 * for type-safe, autocompletable block composition.
 */
#[AsAcfBlock(
    name: 'hero',
    title: 'Hero Banner',
    description: 'A full-width hero banner with title, background image and CTA.',
    category: 'layout',
    icon: 'cover-image',
    keywords: ['hero', 'banner', 'header'],
)]
final readonly class HeroBlock implements AcfBlockInterface
{
    public function __construct(
        private ViewEngineInterface $view,
    ) {}

    public static function fields(): FieldsBuilder
    {
        $builder = new FieldsBuilder('hero');

        $builder
            ->addText('title', [
                'label' => 'Title',
                'required' => true,
            ])
            ->addTextarea('subtitle', [
                'label' => 'Subtitle',
                'rows' => 2,
            ])
            ->addImage('background', [
                'label' => 'Background Image',
                'return_format' => 'id',
                'preview_size' => 'medium',
            ])
            ->addSelect('height', [
                'label' => 'Height',
                'choices' => [
                    'auto' => 'Auto',
                    'small' => 'Small (50vh)',
                    'medium' => 'Medium (75vh)',
                    'full' => 'Full screen',
                ],
                'default_value' => 'medium',
            ])
            ->addFields(new ButtonLinkBuilder('cta', 'Call to Action'));

        return $builder;
    }

    public function compose(array $block, array $fields): HeroContext
    {
        return new HeroContext(
            title: $fields['title'] ?? '',
            subtitle: $fields['subtitle'] ?? null,
            background: ImageData::fromAttachmentId($fields['background'] ?? null),
            cta: LinkData::fromAcf($fields['cta_link'] ?? null),
            height: $fields['height'] ?? 'medium',
        );
    }

    public function render(array $context, bool $isPreview = false): string
    {
        return $this->view->render('blocks/hero', $context);
    }
}
