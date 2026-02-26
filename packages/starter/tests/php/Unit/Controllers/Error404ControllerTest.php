<?php

declare(strict_types=1);

use App\Controllers\Error404Controller;
use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Contracts\TemplateControllerInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;

describe('Error404Controller', function () {
    it('implements TemplateControllerInterface', function () {
        expect(is_subclass_of(Error404Controller::class, TemplateControllerInterface::class))->toBeTrue();
    });

    it('has AsTemplateController attribute for 404 template', function () {
        $ref = new ReflectionClass(Error404Controller::class);
        $attrs = $ref->getAttributes(AsTemplateController::class);

        expect($attrs)->toHaveCount(1);
        expect($attrs[0]->newInstance()->templates)->toBe('404');
    });

    it('requires ViewEngineInterface via constructor', function () {
        $ref = new ReflectionClass(Error404Controller::class);
        $constructor = $ref->getConstructor();
        $params = $constructor->getParameters();

        expect($params)->toHaveCount(1);
        expect($params[0]->getType()->getName())->toBe(ViewEngineInterface::class);
    });
});
