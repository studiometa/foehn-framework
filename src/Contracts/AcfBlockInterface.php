<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Contracts;

use StoutLogic\AcfBuilder\FieldsBuilder;

/**
 * Interface for ACF blocks.
 *
 * Implement this interface to create ACF blocks with the #[AsAcfBlock] attribute.
 */
interface AcfBlockInterface
{
    /**
     * Define ACF fields for this block.
     *
     * @return FieldsBuilder The configured fields builder
     */
    public static function fields(): FieldsBuilder;

    /**
     * Compose data for the view.
     *
     * @param array<string, mixed> $block Block data from ACF
     * @param array<string, mixed> $fields Field values from get_fields()
     * @return array<string, mixed> Context for the template
     */
    public function compose(array $block, array $fields): array;

    /**
     * Render the block.
     *
     * @param array<string, mixed> $context Composed context
     * @param bool $isPreview Whether rendering in editor preview
     * @return string Rendered HTML
     */
    public function render(array $context, bool $isPreview = false): string;
}
