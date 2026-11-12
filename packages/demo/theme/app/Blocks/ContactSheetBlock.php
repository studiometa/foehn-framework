<?php

declare(strict_types=1);

namespace Demo\Blocks;

use Studiometa\Foehn\Attributes\AsBlock;
use Studiometa\Foehn\Contracts\ImageTransformer;
use Studiometa\Foehn\Contracts\InteractiveBlockInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use WP_Block;

/**
 * Contact sheet — an interactive block on the Interactivity API.
 *
 * A grid of thumbnails, and a viewer that opens one at full size: a click opens it,
 * the arrows move through the sheet, and Escape or the close button shuts it. The
 * photographs come from a `gallery` control, which stores a list of attachment IDs.
 *
 * The state that changes lives in the per-instance context, so two sheets on one page
 * do not share an open photograph. The labels live in the namespace state, which
 * every sheet shares. WordPress renders the directives on the server first, so the
 * closed viewer is in the HTML before any JavaScript runs.
 */
#[AsBlock(
    name: 'theme/contact-sheet',
    title: 'Contact sheet',
    category: 'media',
    icon: 'images-alt2',
    description: 'A grid of photographs that opens each one at full size.',
    keywords: ['gallery', 'lightbox', 'photographs'],
    interactivity: true,
)]
final readonly class ContactSheetBlock implements InteractiveBlockInterface
{
    /** The width the viewer asks for, on the 100px grid the transformer snaps to. */
    private const int VIEWER_WIDTH = 1600;

    public function __construct(
        private ViewEngineInterface $view,
        private ImageTransformer $transformer,
    ) {}

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function attributes(): array
    {
        return [
            'imageIds' => [
                'type' => 'array',
                'items' => ['type' => 'integer'],
                'default' => [],
                'control' => 'gallery',
                'allowedTypes' => ['image'],
                'label' => 'Photographs',
            ],
            'caption' => [
                'type' => 'string',
                'default' => '',
                'label' => 'Caption',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function initialState(): array
    {
        return [
            'closeLabel' => __('Close', 'demo-theme'),
            'previousLabel' => __('Previous photograph', 'demo-theme'),
            'nextLabel' => __('Next photograph', 'demo-theme'),
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function initialContext(array $attributes): array
    {
        return [
            'isOpen' => false,
            'current' => 0,
            'photographs' => $this->photographs($attributes),
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function compose(array $attributes, string $content, WP_Block $block): array
    {
        $context = $this->initialContext($attributes);

        return [
            'context' => $context,
            'ids' => array_column($context['photographs'], 'id'),
            'caption' => (string) ($attributes['caption'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function render(array $attributes, string $content, WP_Block $block): string
    {
        return $this->view->render('blocks/contact-sheet', $this->compose($attributes, $content, $block));
    }

    /**
     * The photographs that still exist, with the URL and the text the viewer shows.
     *
     * @param array<string, mixed> $attributes
     * @return list<array{id: int, src: string, alt: string}>
     */
    private function photographs(array $attributes): array
    {
        $ids = $attributes['imageIds'] ?? [];
        $photographs = [];

        foreach (is_array($ids) ? $ids : [] as $id) {
            $src = wp_get_attachment_url((int) $id);

            if (!is_string($src) || $src === '') {
                continue;
            }

            $photographs[] = [
                'id' => (int) $id,
                'src' => $this->transformer->url($src, ['w' => self::VIEWER_WIDTH, 'fm' => 'webp']),
                'alt' => (string) get_post_meta((int) $id, '_wp_attachment_image_alt', true),
            ];
        }

        return $photographs;
    }
}
