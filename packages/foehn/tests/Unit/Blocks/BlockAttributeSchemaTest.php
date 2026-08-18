<?php

declare(strict_types=1);

use Studiometa\Foehn\Blocks\BlockAttributeSchema;

describe('BlockAttributeSchema::toRegistration', function () {
    it('strips the editor only keys', function () {
        $registration = BlockAttributeSchema::toRegistration([
            'title' => [
                'type' => 'string',
                'default' => 'Hello',
                'control' => 'textarea',
                'label' => 'The title',
                'help' => 'Shown as a heading',
                'options' => ['a' => 'A'],
            ],
        ]);

        expect($registration['title'])->toBe([
            'type' => 'string',
            'default' => 'Hello',
        ]);
    });

    it('passes every other key through untouched', function () {
        $registration = BlockAttributeSchema::toRegistration([
            'align' => [
                'type' => 'string',
                'enum' => ['left', 'right'],
                'source' => 'attribute',
                'selector' => 'img',
                'default' => 'left',
            ],
        ]);

        expect($registration['align'])->toBe([
            'type' => 'string',
            'enum' => ['left', 'right'],
            'source' => 'attribute',
            'selector' => 'img',
            'default' => 'left',
        ]);
    });

    it('keeps the attribute order and handles an empty schema', function () {
        expect(BlockAttributeSchema::toRegistration([]))->toBe([]);

        $registration = BlockAttributeSchema::toRegistration([
            'b' => ['type' => 'string'],
            'a' => ['type' => 'number'],
        ]);

        expect(array_keys($registration))->toBe(['b', 'a']);
    });
});

describe('BlockAttributeSchema::toEditorFields', function () {
    it('derives the control from the attribute type', function () {
        $fields = BlockAttributeSchema::toEditorFields([
            'text' => ['type' => 'string'],
            'flag' => ['type' => 'boolean'],
            'ratio' => ['type' => 'number'],
            'count' => ['type' => 'integer'],
        ]);

        expect($fields['text']['control'])->toBe('text');
        expect($fields['flag']['control'])->toBe('toggle');
        expect($fields['ratio']['control'])->toBe('number');
        expect($fields['count']['control'])->toBe('number');
    });

    it('resolves no control for unsupported types', function () {
        $fields = BlockAttributeSchema::toEditorFields([
            'items' => ['type' => 'array'],
            'meta' => ['type' => 'object'],
            'nothing' => ['type' => null],
            'untyped' => ['default' => 1],
        ]);

        expect($fields['items']['control'])->toBeNull();
        expect($fields['meta']['control'])->toBeNull();
        expect($fields['nothing']['control'])->toBeNull();
        expect($fields['untyped']['control'])->toBeNull();
    });

    it('honours an explicit supported control', function () {
        $fields = BlockAttributeSchema::toEditorFields([
            'body' => ['type' => 'string', 'control' => 'textarea'],
            'imageId' => ['type' => 'number', 'control' => 'image'],
        ]);

        expect($fields['body']['control'])->toBe('textarea');
        expect($fields['imageId']['control'])->toBe('image');
    });

    it('falls back to the type derived control for an unsupported explicit control', function () {
        $fields = BlockAttributeSchema::toEditorFields([
            'color' => ['type' => 'string', 'control' => 'color-picker'],
            'ratio' => ['type' => 'number', 'control' => 'range'],
            'meta' => ['type' => 'object', 'control' => 'repeater'],
        ]);

        // A dropped field would be invisible in the sidebar with nothing to grep for.
        expect($fields['color']['control'])->toBe('text');
        expect($fields['ratio']['control'])->toBe('number');
        expect($fields['meta']['control'])->toBeNull();
    });

    it('keeps the schema type in the field descriptor', function () {
        $fields = BlockAttributeSchema::toEditorFields([
            'ratio' => ['type' => 'number'],
            'count' => ['type' => 'integer'],
            'title' => ['type' => 'string'],
            'untyped' => ['default' => 1],
        ]);

        // `number` and `integer` share one control, so only the type tells the editor
        // to round: a float on an integer attribute is rejected by WordPress.
        expect($fields['ratio']['type'])->toBe('number');
        expect($fields['count']['type'])->toBe('integer');
        expect($fields['count']['control'])->toBe('number');
        expect($fields['title']['type'])->toBe('string');
        expect($fields['untyped']['type'])->toBeNull();
    });

    it('reads a list of options as raw values, not as array indices', function () {
        $fields = BlockAttributeSchema::toEditorFields([
            'size' => [
                'type' => 'string',
                'default' => 'small',
                'control' => 'select',
                'options' => ['small', 'large'],
            ],
        ]);

        // Taking the array key here would store 0 or 1 in a string attribute, which
        // WordPress drops on render in favour of the default.
        expect($fields['size']['options'])->toBe([
            ['label' => 'small', 'value' => 'small'],
            ['label' => 'large', 'value' => 'large'],
        ]);
    });

    it('coerces option values to the declared attribute type', function () {
        $fields = BlockAttributeSchema::toEditorFields([
            'level' => ['type' => 'integer', 'control' => 'select', 'options' => ['2' => 'H2', '3' => 'H3']],
            'label' => ['type' => 'string', 'control' => 'select', 'options' => [2 => 'Two', 3 => 'Three']],
        ]);

        expect($fields['level']['options'])->toBe([
            ['label' => 'H2', 'value' => 2],
            ['label' => 'H3', 'value' => 3],
        ]);
        expect($fields['label']['options'])->toBe([
            ['label' => 'Two', 'value' => '2'],
            ['label' => 'Three', 'value' => '3'],
        ]);
    });

    it('coerces enum values to the declared attribute type', function () {
        $fields = BlockAttributeSchema::toEditorFields([
            'columns' => ['type' => 'integer', 'control' => 'select', 'enum' => ['2', 3]],
        ]);

        expect($fields['columns']['options'])->toBe([
            ['label' => '2', 'value' => 2],
            ['label' => '3', 'value' => 3],
        ]);
    });

    it('resolves a string attribute with options to a select', function () {
        $fields = BlockAttributeSchema::toEditorFields([
            'variant' => [
                'type' => 'string',
                'options' => ['light' => 'Light', 'dark' => 'Dark'],
            ],
        ]);

        expect($fields['variant']['control'])->toBe('select');
        expect($fields['variant']['options'])->toBe([
            ['label' => 'Light', 'value' => 'light'],
            ['label' => 'Dark', 'value' => 'dark'],
        ]);
    });

    it('resolves a string attribute with an enum to a select', function () {
        $fields = BlockAttributeSchema::toEditorFields([
            'align' => ['type' => 'string', 'enum' => ['left', 'right']],
        ]);

        expect($fields['align']['control'])->toBe('select');
        expect($fields['align']['options'])->toBe([
            ['label' => 'left', 'value' => 'left'],
            ['label' => 'right', 'value' => 'right'],
        ]);
    });

    it('keeps an already normalized options list', function () {
        $fields = BlockAttributeSchema::toEditorFields([
            'size' => [
                'type' => 'string',
                'options' => [
                    ['label' => 'Small', 'value' => 'sm'],
                    ['label' => 'Large', 'value' => 'lg'],
                ],
            ],
        ]);

        expect($fields['size']['options'])->toBe([
            ['label' => 'Small', 'value' => 'sm'],
            ['label' => 'Large', 'value' => 'lg'],
        ]);
    });

    it('does not resolve a select for a non string attribute with options', function () {
        $fields = BlockAttributeSchema::toEditorFields([
            'level' => ['type' => 'number', 'options' => [2 => 'H2', 3 => 'H3']],
        ]);

        expect($fields['level']['control'])->toBe('number');
        expect($fields['level']['options'])->toBeNull();
    });

    it('humanizes the attribute key when no label is given', function () {
        $fields = BlockAttributeSchema::toEditorFields([
            'ctaLabel' => ['type' => 'string'],
            'image_id' => ['type' => 'number'],
            'title' => ['type' => 'string'],
            'backgroundImageUrl' => ['type' => 'string'],
        ]);

        expect($fields['ctaLabel']['label'])->toBe('Cta label');
        expect($fields['image_id']['label'])->toBe('Image id');
        expect($fields['title']['label'])->toBe('Title');
        expect($fields['backgroundImageUrl']['label'])->toBe('Background image url');
    });

    it('uses the explicit label and help when given', function () {
        $fields = BlockAttributeSchema::toEditorFields([
            'ctaLabel' => ['type' => 'string', 'label' => 'Button text', 'help' => 'Keep it short'],
        ]);

        expect($fields['ctaLabel']['label'])->toBe('Button text');
        expect($fields['ctaLabel']['help'])->toBe('Keep it short');
    });

    it('falls back to a null help', function () {
        $fields = BlockAttributeSchema::toEditorFields([
            'title' => ['type' => 'string'],
        ]);

        expect($fields['title']['help'])->toBeNull();
    });

    it('returns one entry per attribute in the same order', function () {
        $fields = BlockAttributeSchema::toEditorFields([
            'zeta' => ['type' => 'string'],
            'alpha' => ['type' => 'boolean'],
        ]);

        expect(array_keys($fields))->toBe(['zeta', 'alpha']);
        expect(array_keys($fields['zeta']))->toBe(['control', 'type', 'label', 'help', 'options']);
    });

    it('returns an empty array for an empty schema', function () {
        expect(BlockAttributeSchema::toEditorFields([]))->toBe([]);
    });
});
