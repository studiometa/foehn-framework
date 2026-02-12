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
        private readonly ?AcfFieldTransformer $transformer = null,
    ) {}

    /**
     * Render an ACF block.
     *
     * @param AcfBlockInterface $block The block instance
     * @param array<string, mixed> $blockData Block data from ACF
     * @param bool $isPreview Whether rendering in editor preview
     * @return string Rendered HTML
     */
    public function render(AcfBlockInterface $block, array $blockData, bool $isPreview = false): string
    {
        // Get field values
        $fields = $this->getFields($blockData);

        // Transform fields if enabled
        if ($this->shouldTransformFields()) {
            $blockId = $blockData['id'] ?? null;

            if ($blockId !== null) {
                $transformer = $this->transformer ?? new AcfFieldTransformer();
                $fields = $transformer->transformFields($fields, $blockId);
            }
        }

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
     * Get field values for the block.
     *
     * @param array<string, mixed> $blockData Block data from ACF
     * @return array<string, mixed> Field values
     */
    private function getFields(array $blockData): array
    {
        // In ACF, fields are stored in the block data
        if (isset($blockData['data']) && is_array($blockData['data'])) {
            return $this->parseAcfData($blockData['data']);
        }

        // Fallback to get_fields() if available
        if (function_exists('get_fields') && !empty($blockData['id'])) {
            $fields = get_fields($blockData['id']);

            return is_array($fields) ? $fields : [];
        }

        return [];
    }

    /**
     * Parse ACF block data format to clean field values.
     *
     * ACF stores field values with prefixed keys (e.g., 'field_xxx' => 'value').
     * This method extracts the clean field names and values.
     *
     * @param array<string, mixed> $data Raw ACF data
     * @return array<string, mixed> Clean field values
     */
    private function parseAcfData(array $data): array
    {
        $fields = [];

        foreach ($data as $key => $value) {
            // Skip field key references (start with '_')
            if (str_starts_with($key, '_')) {
                continue;
            }

            // Skip field_xxx keys
            if (str_starts_with($key, 'field_')) {
                continue;
            }

            $fields[$key] = $value;
        }

        return $fields;
    }

    /**
     * Check if field transformation is enabled.
     */
    private function shouldTransformFields(): bool
    {
        if ($this->config === null) {
            return true;
        }

        return $this->config->transformFields;
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
        if (isset($blockData['name']) && is_string($blockData['name'])) {
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
