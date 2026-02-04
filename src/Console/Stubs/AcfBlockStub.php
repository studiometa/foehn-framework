<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Console\Stubs;

use StoutLogic\AcfBuilder\FieldsBuilder;
use Studiometa\WPTempest\Attributes\AsAcfBlock;
use Studiometa\WPTempest\Contracts\AcfBlockInterface;
use Tempest\Discovery\SkipDiscovery;

#[SkipDiscovery]
#[AsAcfBlock(
    name: 'dummy-acf-block',
    title: 'Dummy ACF Block',
    category: 'common',
    icon: 'block-default',
    description: 'A custom ACF block.',
    keywords: ['custom', 'acf'],
    mode: 'preview',
    supports: [
        'align' => true,
        'mode' => true,
        'jsx' => true,
    ],
)]
final class AcfBlockStub implements AcfBlockInterface
{
    /**
     * Define the block fields using ACF Builder.
     */
    public static function fields(): FieldsBuilder
    {
        $fields = new FieldsBuilder('dummy_acf_block');

        $fields
            ->addText('title', [
                'label' => 'Title',
                'required' => true,
            ])
            ->addWysiwyg('content', [
                'label' => 'Content',
                'media_upload' => false,
                'tabs' => 'visual',
            ])
            ->addImage('image', [
                'label' => 'Image',
                'return_format' => 'id',
                'preview_size' => 'medium',
            ]);

        return $fields;
    }

    /**
     * Compose data for the block template.
     *
     * @param array<string, mixed> $block Block data from ACF
     * @param array<string, mixed> $fields Field values from get_fields()
     * @return array<string, mixed> Data passed to the Twig template
     */
    public function compose(array $block, array $fields): array
    {
        return [
            'block' => $block,
            'title' => $fields['title'] ?? '',
            'content' => $fields['content'] ?? '',
            'image' => $fields['image'] ?? null,
        ];
    }

    /**
     * Render the block.
     *
     * Return an empty string to use the default template rendering.
     *
     * @param array<string, mixed> $context Composed context
     * @param bool $isPreview Whether rendering in editor preview
     * @return string Rendered HTML (empty to use template)
     */
    public function render(array $context, bool $isPreview = false): string
    {
        // Return empty string to use the default Twig template rendering
        return '';
    }
}
