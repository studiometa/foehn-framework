<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Blocks;

use Studiometa\Foehn\Config\AcfConfig;
use Studiometa\Foehn\Contracts\AcfBlockInterface;
use Studiometa\Foehn\Contracts\Arrayable;

/**
 * Handles rendering of ACF blocks.
 */
final class AcfBlockRenderer
{
    public function __construct(
        private readonly ?AcfConfig $config = null,
    ) {}

    /**
     * Render an ACF block.
     *
     * Runs inside ACF's render callback, after ACF has loaded the block's values
     * as its meta.
     *
     * @param AcfBlockInterface $block The block instance
     * @param array<string, mixed> $blockData Block data from ACF
     * @param bool $isPreview Whether rendering in editor preview
     * @return string Rendered HTML
     */
    public function render(AcfBlockInterface $block, array $blockData, bool $isPreview = false): string
    {
        $blockId = $blockData['id'] ?? null;
        $fields = is_string($blockId) && $blockId !== ''
            ? new AcfBlockFields($this->config->transformFields ?? true)->get($blockId)
            : [];

        // Compose the context
        $context = $block->compose($blockData, $fields);

        if ($context instanceof Arrayable) {
            $context = $context->toArray();
        }

        // Add common block data to context
        $context = $this->enrichContext($context, $blockData, $isPreview);

        // Render the block
        return $block->render($context, $isPreview);
    }

    /**
     * Enrich context with common block data.
     *
     * @param array<string, mixed> $context Current context
     * @param array<string, mixed> $blockData Block data from ACF
     * @param bool $isPreview Whether rendering in editor preview
     * @return array<string, mixed> Enriched context
     */
    private function enrichContext(array $context, array $blockData, bool $isPreview): array
    {
        return array_merge($context, [
            'block' => $blockData,
            'block_id' => $blockData['id'] ?? uniqid('block-'),
            'block_name' => $blockData['name'] ?? '',
            'block_class' => $this->buildBlockClass($blockData),
            'is_preview' => $isPreview,
            'align' => $blockData['align'] ?? '',
            'anchor' => $blockData['anchor'] ?? '',
        ]);
    }

    /**
     * Build CSS class string for the block.
     *
     * @param array<string, mixed> $blockData Block data from ACF
     * @return string CSS class string
     */
    private function buildBlockClass(array $blockData): string
    {
        $classes = [];

        // Base class from block name
        if (is_string($blockData['name'] ?? null)) {
            $classes[] = 'wp-block-' . str_replace('/', '-', $blockData['name']);
        }

        // Alignment class
        if (!empty($blockData['align']) && is_string($blockData['align'])) {
            $classes[] = 'align' . $blockData['align'];
        }

        // Custom class from editor
        if (!empty($blockData['className'])) {
            $classes[] = $blockData['className'];
        }

        return implode(' ', $classes);
    }
}
