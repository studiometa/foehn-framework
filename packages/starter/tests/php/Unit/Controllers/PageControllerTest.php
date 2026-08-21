<?php

declare(strict_types=1);

use App\Controllers\PageController;
use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Contracts\TemplateControllerInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;

describe('PageController', function () {
    it('implements TemplateControllerInterface', function () {
        expect(is_subclass_of(PageController::class, TemplateControllerInterface::class))->toBeTrue();
    });

    // Sans ce contrôleur, les pages retombaient sur le rendu par défaut de
    // WordPress et `pages/page.twig` n'était jamais lu.
    it('has AsTemplateController attribute for page templates', function () {
        $ref = new ReflectionClass(PageController::class);
        $attrs = $ref->getAttributes(AsTemplateController::class);

        expect($attrs)->toHaveCount(1);

        $templates = $attrs[0]->newInstance()->templates;

        expect($templates)->toContain('page');
        expect($templates)->toContain('page-*');
    });

    it('requires ViewEngineInterface via constructor', function () {
        $ref = new ReflectionClass(PageController::class);
        $params = $ref->getConstructor()->getParameters();

        expect($params)->toHaveCount(1);
        expect($params[0]->getType()->getName())->toBe(ViewEngineInterface::class);
    });
});
