<?php

declare(strict_types=1);

namespace Demo\Blocks;

use Studiometa\Foehn\Attributes\AsBlock;
use Studiometa\Foehn\Contracts\BlockInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use WP_Block;

/**
 * Press kit — a block with a `file` control.
 *
 * The control stores one attachment ID and, with `allowedTypes`, offers PDF files
 * only. The block prints a download link with the file's size, which WordPress keeps
 * in the attachment metadata.
 */
#[AsBlock(
    name: 'theme/press-kit',
    title: 'Press kit',
    category: 'media',
    icon: 'media-document',
    description: 'A download link to a PDF from the media library.',
    keywords: ['download', 'pdf', 'file'],
)]
final readonly class PressKitBlock implements BlockInterface
{
    public function __construct(
        private ViewEngineInterface $view,
    ) {}

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function attributes(): array
    {
        return [
            'fileId' => [
                'type' => 'integer',
                'control' => 'file',
                'allowedTypes' => ['application/pdf'],
                'label' => 'PDF file',
            ],
            'label' => [
                'type' => 'string',
                'default' => 'Download the press kit',
                'label' => 'Link text',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function compose(array $attributes, string $content, WP_Block $block): array
    {
        $id = (int) ($attributes['fileId'] ?? 0);
        $url = $id > 0 ? wp_get_attachment_url($id) : false;
        $metadata = $id > 0 ? wp_get_attachment_metadata($id) : false;
        $bytes = is_array($metadata) ? (int) ($metadata['filesize'] ?? 0) : 0;

        return [
            'url' => is_string($url) ? $url : null,
            'label' => (string) ($attributes['label'] ?? ''),
            'size' => $bytes > 0 ? size_format($bytes) : null,
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function render(array $attributes, string $content, WP_Block $block): string
    {
        return $this->view->render('blocks/press-kit', $this->compose($attributes, $content, $block));
    }
}
