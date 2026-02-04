<?php

declare(strict_types=1);

use Studiometa\Foehn\Hooks\Security\DisableFileEditor;

describe('DisableFileEditor', function () {
    it('has AsAction attribute on init with priority 1', function () {
        $method = new ReflectionMethod(DisableFileEditor::class, 'disableFileEditor');
        $attributes = $method->getAttributes(\Studiometa\Foehn\Attributes\AsAction::class);

        expect($attributes)->toHaveCount(1);

        $instance = $attributes[0]->newInstance();

        expect($instance->hook)->toBe('init');
        expect($instance->priority)->toBe(1);
    });

    it('is a final class', function () {
        expect(new ReflectionClass(DisableFileEditor::class))->isFinal()->toBeTrue();
    });

    // Note: We can't test the actual define() behavior in unit tests
    // because constants persist across test runs. The method logic
    // is straightforward and tested via attribute verification.
});
