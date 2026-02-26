<?php

declare(strict_types=1);

use App\Controllers\SingleController;
use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Contracts\TemplateControllerInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;

describe('SingleController', function () {
    it('implements TemplateControllerInterface', function () {
        expect(is_subclass_of(SingleController::class, TemplateControllerInterface::class))->toBeTrue();
    });

    it('has AsTemplateController attribute for single templates', function () {
        $ref = new ReflectionClass(SingleController::class);
        $attrs = $ref->getAttributes(AsTemplateController::class);

        expect($attrs)->toHaveCount(1);

        $templates = $attrs[0]->newInstance()->templates;

        expect($templates)->toContain('single');
        expect($templates)->toContain('single-*');
    });

    it('requires ViewEngineInterface via constructor', function () {
        $ref = new ReflectionClass(SingleController::class);
        $constructor = $ref->getConstructor();
        $params = $constructor->getParameters();

        expect($params)->toHaveCount(1);
        expect($params[0]->getType()->getName())->toBe(ViewEngineInterface::class);
    });
});
