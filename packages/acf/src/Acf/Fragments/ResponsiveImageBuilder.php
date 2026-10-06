<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Acf\Fragments;

use StoutLogic\AcfBuilder\FieldsBuilder;

/**
 * Responsive image fragment with desktop/mobile variants.
 *
 * Creates the following fields:
 * - desktop (image)
 * - mobile (image)
 *
 * The name does not prefix them: add the fragment inside a group, for example
 * `addGroup('image')->addFields(new ResponsiveImageBuilder())`.
 */
final class ResponsiveImageBuilder extends FieldsBuilder
{
    /**
     * @param string $name Name of the fragment's own builder. It does not prefix the field names
     * @param string $label Field group label
     * @param bool $required Whether the desktop image is required
     * @param string $desktopInstructions Instructions for desktop image
     * @param string $mobileInstructions Instructions for mobile image
     */
    public function __construct(
        string $name = 'image',
        string $label = 'Image',
        bool $required = false,
        string $desktopInstructions = 'Recommended: 1920×1080px',
        string $mobileInstructions = 'Leave empty to use desktop image.',
    ) {
        parent::__construct($name, ['label' => $label]);

        $this->addImage('desktop', [
            'label' => 'Desktop',
            'instructions' => $desktopInstructions,
            'required' => $required,
            'return_format' => 'id',
            'preview_size' => 'medium',
        ])->addImage('mobile', [
            'label' => 'Mobile',
            'instructions' => $mobileInstructions,
            'return_format' => 'id',
            'preview_size' => 'medium',
        ]);
    }
}
