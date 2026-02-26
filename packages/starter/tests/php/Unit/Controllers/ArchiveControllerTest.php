<?php

declare(strict_types=1);

use App\Controllers\ArchiveController;
use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Contracts\TemplateControllerInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;

describe('ArchiveController', function () {
    it('implements TemplateControllerInterface', function () {
        expect(is_subclass_of(ArchiveController::class, TemplateControllerInterface::class))->toBeTrue();
    });

    it('has AsTemplateController attribute for archive templates', function () {
        $ref = new ReflectionClass(ArchiveController::class);
        $attrs = $ref->getAttributes(AsTemplateController::class);

        expect($attrs)->toHaveCount(1);

        $templates = $attrs[0]->newInstance()->templates;

        expect($templates)->toContain('archive');
        expect($templates)->toContain('archive-*');
        expect($templates)->toContain('front-page');
        expect($templates)->toContain('home');
        expect($templates)->toContain('category');
        expect($templates)->toContain('tag');
        expect($templates)->toContain('tax-*');
    });

    it('requires ViewEngineInterface via constructor', function () {
        $ref = new ReflectionClass(ArchiveController::class);
        $constructor = $ref->getConstructor();
        $params = $constructor->getParameters();

        expect($params)->toHaveCount(1);
        expect($params[0]->getType()->getName())->toBe(ViewEngineInterface::class);
    });
});
