<?php

declare(strict_types=1);

use Demo\Controllers\SingleController;
use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Contracts\TemplateControllerInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Studiometa\Foehn\Views\TemplateContext;
use Timber\Site;

describe('SingleController', function () {
    afterEach(function () {
        wp_stub_reset();
    });

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

    it('renders the password template while the post needs a password', function () {
        wp_stub_set_conditional('post_password_required', true);

        $rendered = [];
        $controller = new SingleController(createFakeViewEngine(function (string $template) use (&$rendered): string {
            $rendered[] = $template;

            return '';
        }));

        $controller->handle(new TemplateContext(post: createFakePost(42), posts: null, site: new Site(), user: null));

        expect($rendered)->toBe(['pages/password']);
    });

    // The demo had no `pages/password`: a password-protected post threw
    // "Failed to render template: pages/password" and answered 500.
    it('ships the template it renders for a password-protected post', function () {
        expect(dirname(__DIR__, 4) . '/theme/templates/pages/password.twig')->toBeFile();
    });
});
