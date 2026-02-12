<?php

declare(strict_types=1);

use Studiometa\Foehn\Contracts\Arrayable;
use Studiometa\Foehn\Data\SpacingData;

describe('SpacingData', function () {
    it('implements Arrayable', function () {
        $spacing = new SpacingData();
        expect($spacing)->toBeInstanceOf(Arrayable::class);
    });

    it('constructs with default values', function () {
        $spacing = new SpacingData();

        expect($spacing->top)->toBe('medium');
        expect($spacing->bottom)->toBe('medium');
    });

    it('constructs with custom values', function () {
        $spacing = new SpacingData(top: 'large', bottom: 'none');

        expect($spacing->top)->toBe('large');
        expect($spacing->bottom)->toBe('none');
    });

    it('converts to array', function () {
        $spacing = new SpacingData(top: 'small', bottom: 'xlarge');

        expect($spacing->toArray())->toBe([
            'top' => 'small',
            'bottom' => 'xlarge',
        ]);
    });

    describe('fromAcf', function () {
        it('creates from ACF fields with default prefix', function () {
            $spacing = SpacingData::fromAcf([
                'spacing_top' => 'large',
                'spacing_bottom' => 'small',
            ]);

            expect($spacing->top)->toBe('large');
            expect($spacing->bottom)->toBe('small');
        });

        it('creates from ACF fields with custom prefix', function () {
            $spacing = SpacingData::fromAcf([
                'padding_top' => 'none',
                'padding_bottom' => 'xlarge',
            ], 'padding');

            expect($spacing->top)->toBe('none');
            expect($spacing->bottom)->toBe('xlarge');
        });

        it('defaults to medium when fields are missing', function () {
            $spacing = SpacingData::fromAcf([]);

            expect($spacing->top)->toBe('medium');
            expect($spacing->bottom)->toBe('medium');
        });

        it('defaults to medium when input is null', function () {
            $spacing = SpacingData::fromAcf(null);

            expect($spacing->top)->toBe('medium');
            expect($spacing->bottom)->toBe('medium');
        });

        it('handles partial fields', function () {
            $spacing = SpacingData::fromAcf(['spacing_top' => 'large']);

            expect($spacing->top)->toBe('large');
            expect($spacing->bottom)->toBe('medium');
        });
    });
});
