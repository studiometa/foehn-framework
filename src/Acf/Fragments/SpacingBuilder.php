<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Acf\Fragments;

use StoutLogic\AcfBuilder\FieldsBuilder;

/**
 * Spacing controls fragment for consistent padding/margin.
 *
 * Creates the following fields:
 * - {$name}_top (select)
 * - {$name}_bottom (select)
 */
final class SpacingBuilder extends FieldsBuilder
{
    /**
     * @param string $name Field name prefix
     * @param string $label Field group label
     * @param array<string, string> $sizes Available spacing sizes
     * @param string $default Default spacing value
     * @param string $topLabel Label for top spacing
     * @param string $bottomLabel Label for bottom spacing
     */
    public function __construct(
        string $name = 'spacing',
        string $label = 'Spacing',
        array $sizes = [
            'none' => 'None',
            'small' => 'Small',
            'medium' => 'Medium',
            'large' => 'Large',
            'xlarge' => 'Extra Large',
        ],
        string $default = 'medium',
        string $topLabel = 'Padding Top',
        string $bottomLabel = 'Padding Bottom',
    ) {
        parent::__construct($name, ['label' => $label]);

        $this->addSelect('top', [
            'label' => $topLabel,
            'choices' => $sizes,
            'default_value' => $default,
        ])->addSelect('bottom', [
            'label' => $bottomLabel,
            'choices' => $sizes,
            'default_value' => $default,
        ]);
    }
}
