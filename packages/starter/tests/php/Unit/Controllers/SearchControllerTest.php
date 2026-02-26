<?php

declare(strict_types=1);

use App\Controllers\SearchController;
use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Contracts\TemplateControllerInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;

describe('SearchController', function () {
    it('implements TemplateControllerInterface', function () {
        expect(is_subclass_of(SearchController::class, TemplateControllerInterface::class))->toBeTrue();
    });

    it('has AsTemplateController attribute for search template', function () {
        $ref = new ReflectionClass(SearchController::class);
        $attrs = $ref->getAttributes(AsTemplateController::class);

        expect($attrs)->toHaveCount(1);
        expect($attrs[0]->newInstance()->templates)->toBe('search');
    });

    it('requires ViewEngineInterface via constructor', function () {
        $ref = new ReflectionClass(SearchController::class);
        $constructor = $ref->getConstructor();
        $params = $constructor->getParameters();

        expect($params)->toHaveCount(1);
        expect($params[0]->getType()->getName())->toBe(ViewEngineInterface::class);
    });
});
