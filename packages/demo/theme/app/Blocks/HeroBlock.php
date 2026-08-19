<?php

declare(strict_types=1);

namespace Demo\Blocks;

use Demo\Data\HeroContext;
use Studiometa\Foehn\Attributes\AsBlock;
use Studiometa\Foehn\Contracts\BlockInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Studiometa\Foehn\Data\ImageData;
use Studiometa\Foehn\Data\LinkData;
use WP_Block;

/**
 * Hero banner — a native block with a typed DTO context.
 *
 * It was an ACF block, and it was the only thing here that needed a
 * paid plugin to run at all: ACF Pro is not installed in CI, so that path was
 * never exercised end to end. Everything it did is here without one — the
 * sidebar controls come from the attribute schema alone.
 *
 * `compose()` returns an Arrayable DTO rather than a plain array, which is the
 * pattern worth copying: the template's variables are a class you can read.
 */
#[AsBlock(
    name: 'theme/hero',
    title: 'Hero Banner',
    category: 'layout',
    icon: 'cover-image',
    description: 'A full-width hero banner with title, background image and CTA.',
    keywords: ['hero', 'banner', 'header'],
    supports: [
        'align' => ['wide', 'full'],
    ],
)]
final readonly class HeroBlock implements BlockInterface
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
            'title' => [
                'type' => 'string',
                'default' => '',
                'label' => 'Title',
                'help' => 'Shown as the page heading.',
            ],
            'subtitle' => [
                'type' => 'string',
                'default' => '',
                'control' => 'textarea',
                'label' => 'Subtitle',
            ],
            'backgroundId' => [
                'type' => 'integer',
                'control' => 'image',
                'label' => 'Background image',
            ],
            'height' => [
                'type' => 'string',
                'default' => 'medium',
                'label' => 'Height',
                'options' => [
                    'auto' => 'Auto',
                    'small' => 'Small (50vh)',
                    'medium' => 'Medium (75vh)',
                    'full' => 'Full screen',
                ],
            ],
            'ctaLabel' => [
                'type' => 'string',
                'default' => '',
                'label' => 'Call to action',
                'help' => 'Leave empty for no button.',
            ],
            'ctaUrl' => [
                'type' => 'string',
                'default' => '',
                'label' => 'Call to action URL',
            ],
            'ctaTarget' => [
                'type' => 'string',
                'default' => '',
                'label' => 'Opens in',
                'options' => [
                    '' => 'The same tab',
                    '_blank' => 'A new tab',
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function compose(array $attributes, string $content, WP_Block $block): HeroContext
    {
        // Spelled out rather than written as a chain of ?? and ?:, whose
        // precedence is not what it reads like.
        $subtitle = (string) ($attributes['subtitle'] ?? '');

        return new HeroContext(
            title: (string) ($attributes['title'] ?? ''),
            subtitle: $subtitle === '' ? null : $subtitle,
            background: ImageData::fromAttachmentId($attributes['backgroundId'] ?? null),
            cta: self::cta($attributes),
            height: (string) ($attributes['height'] ?? 'medium'),
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function render(array $attributes, string $content, WP_Block $block): string
    {
        return $this->view->render('blocks/hero', $this->compose($attributes, $content, $block));
    }

    /**
     * The call to action, or nothing when no URL was given.
     *
     * ACF's link field was one control returning one array. Three attributes
     * replace it, which is more to fill in and needs no plugin to store.
     *
     * @param array<string, mixed> $attributes
     */
    private static function cta(array $attributes): ?LinkData
    {
        $url = (string) ($attributes['ctaUrl'] ?? '');

        if ($url === '') {
            return null;
        }

        return new LinkData(
            url: $url,
            title: (string) ($attributes['ctaLabel'] ?? ''),
            target: (string) ($attributes['ctaTarget'] ?? ''),
        );
    }
}
