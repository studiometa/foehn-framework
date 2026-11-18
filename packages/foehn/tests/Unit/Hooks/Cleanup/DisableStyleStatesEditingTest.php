<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsFilter;
use Studiometa\Foehn\Hooks\Cleanup\DisableStyleStatesEditing;

describe('DisableStyleStatesEditing', function () {
    it('turns off responsive and state style editing', function () {
        $hooks = new DisableStyleStatesEditing();

        $settings = $hooks->disableStyleStatesEditing([
            'responsiveEditingEnabled' => true,
            'blockStatesEditingEnabled' => true,
        ]);

        expect($settings['responsiveEditingEnabled'])->toBeFalse();
        expect($settings['blockStatesEditingEnabled'])->toBeFalse();
    });

    it('keeps the other editor settings', function () {
        $hooks = new DisableStyleStatesEditing();

        $settings = $hooks->disableStyleStatesEditing(['titlePlaceholder' => 'Title']);

        expect($settings)->toBe([
            'titlePlaceholder' => 'Title',
            'responsiveEditingEnabled' => false,
            'blockStatesEditingEnabled' => false,
        ]);
    });

    it('has AsFilter attribute on block_editor_settings_all hook', function () {
        $method = new ReflectionMethod(DisableStyleStatesEditing::class, 'disableStyleStatesEditing');
        $attributes = $method->getAttributes(AsFilter::class);

        expect($attributes)->toHaveCount(1);
        expect($attributes[0]->newInstance()->hook)->toBe('block_editor_settings_all');
    });
});
