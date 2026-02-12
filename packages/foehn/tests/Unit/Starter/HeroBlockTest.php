<?php

declare(strict_types=1);

use App\Blocks\HeroBlock;
use StoutLogic\AcfBuilder\FieldsBuilder;

describe('Starter HeroBlock', function () {
    it('returns a FieldsBuilder from fields()', function () {
        $builder = HeroBlock::fields();

        expect($builder)->toBeInstanceOf(FieldsBuilder::class);
    });

    it('defines the expected fields', function () {
        $builder = HeroBlock::fields();
        $config = $builder->build();
        $fieldNames = array_column($config['fields'], 'name');

        expect($fieldNames)->toContain('title');
        expect($fieldNames)->toContain('subtitle');
        expect($fieldNames)->toContain('background');
        expect($fieldNames)->toContain('height');
    });

    it('marks title as required', function () {
        $builder = HeroBlock::fields();
        $config = $builder->build();

        $titleField = current(array_filter($config['fields'], fn($f) => $f['name'] === 'title'));

        expect($titleField['required'])->toBeTrue();
    });

    it('has correct height choices', function () {
        $builder = HeroBlock::fields();
        $config = $builder->build();

        $heightField = current(array_filter($config['fields'], fn($f) => $f['name'] === 'height'));

        expect($heightField['choices'])->toBe([
            'auto' => 'Auto',
            'small' => 'Small (50vh)',
            'medium' => 'Medium (75vh)',
            'full' => 'Full screen',
        ]);
        expect($heightField['default_value'])->toBe('medium');
    });
});
