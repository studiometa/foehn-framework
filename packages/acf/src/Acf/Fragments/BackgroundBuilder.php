<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Acf\Fragments;

use StoutLogic\AcfBuilder\FieldsBuilder;

/**
 * Background settings fragment with color, image, and overlay options.
 *
 * Creates the following fields:
 * - {$name}_type (button_group)
 * - {$name}_color (color_picker) - conditional on type=color
 * - {$name}_image (image) - conditional on type=image
 * - {$name}_overlay (true_false) - conditional on type=image
 * - {$name}_overlay_opacity (range) - conditional on overlay=true
 */
final class BackgroundBuilder extends FieldsBuilder
{
    /**
     * @param string $name Field name prefix
     * @param string $label Field group label
     * @param array<string, string> $types Available background types
     * @param string $default Default background type
     * @param int $defaultOpacity Default overlay opacity (0-100)
     */
    public function __construct(
        string $name = 'background',
        string $label = 'Background',
        array $types = [
            'none' => 'None',
            'color' => 'Color',
            'image' => 'Image',
        ],
        string $default = 'none',
        int $defaultOpacity = 50,
    ) {
        parent::__construct($name, ['label' => $label]);

        $this
            ->addButtonGroup('type', [
                'label' => 'Background Type',
                'choices' => $types,
                'default_value' => $default,
            ])
            ->addColorPicker('color', [
                'label' => 'Background Color',
            ])
            ->conditional('type', '==', 'color')
            ->addImage('image', [
                'label' => 'Background Image',
                'return_format' => 'id',
                'preview_size' => 'medium',
            ])
            ->conditional('type', '==', 'image')
            ->addTrueFalse('overlay', [
                'label' => 'Add Overlay',
                'default_value' => true,
            ])
            ->conditional('type', '==', 'image')
            ->addRange('overlay_opacity', [
                'label' => 'Overlay Opacity',
                'min' => 0,
                'max' => 100,
                'step' => 5,
                'default_value' => $defaultOpacity,
            ])
            ->conditional('type', '==', 'image')
            ->and('overlay', '==', 1);
    }
}
