<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Acf\Fragments;

use StoutLogic\AcfBuilder\FieldsBuilder;

/**
 * Reusable button/link field fragment.
 *
 * Creates the following fields:
 * - {$name}_link (link)
 * - {$name}_style (select)
 * - {$name}_size (select) - optional
 */
final class ButtonLinkBuilder extends FieldsBuilder
{
    /**
     * @param string $name Field name prefix
     * @param string $label Field group label
     * @param array<string, string> $styles Available button styles
     * @param array<string, string>|null $sizes Available button sizes (null to disable)
     * @param bool $required Whether the link field is required
     */
    public function __construct(
        string $name = 'button',
        string $label = 'Button',
        array $styles = [
            'primary' => 'Primary',
            'secondary' => 'Secondary',
            'outline' => 'Outline',
        ],
        ?array $sizes = [
            'small' => 'Small',
            'medium' => 'Medium',
            'large' => 'Large',
        ],
        bool $required = false,
    ) {
        parent::__construct($name, ['label' => $label]);

        $this->addLink('link', [
            'label' => 'Link',
            'required' => $required,
            'return_format' => 'array',
        ])->addSelect('style', [
            'label' => 'Style',
            'choices' => $styles,
            'default_value' => array_key_first($styles),
        ]);

        if ($sizes !== null) {
            $this->addSelect('size', [
                'label' => 'Size',
                'choices' => $sizes,
                'default_value' => 'medium',
            ]);
        }
    }
}
