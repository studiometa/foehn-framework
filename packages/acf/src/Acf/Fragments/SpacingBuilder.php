<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Acf\Fragments;

use StoutLogic\AcfBuilder\FieldsBuilder;

/**
 * Spacing controls fragment for consistent padding/margin.
 *
 * Creates the following fields:
 * - top (select)
 * - bottom (select)
 *
 * The name does not prefix them: add the fragment inside a group, for example
 * `addGroup('spacing')->addFields(new SpacingBuilder())`.
 */
final class SpacingBuilder extends FieldsBuilder
{
    /**
     * @param string $name Name of the fragment's own builder. It does not prefix the field names
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
