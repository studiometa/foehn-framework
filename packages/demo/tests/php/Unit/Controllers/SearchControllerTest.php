<?php

declare(strict_types=1);

use Demo\Controllers\SearchController;
use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Contracts\TemplateControllerInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Studiometa\Foehn\Search\Query\SearchGroups;

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

    it('requires the view engine and the search groups via constructor', function () {
        $ref = new ReflectionClass(SearchController::class);
        $params = $ref->getConstructor()->getParameters();

        expect(array_map(static fn(ReflectionParameter $param): string => $param
            ->getType()
            ->getName(), $params))->toBe([ViewEngineInterface::class, SearchGroups::class]);
    });
});
