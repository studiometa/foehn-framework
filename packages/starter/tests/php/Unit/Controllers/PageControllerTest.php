<?php

declare(strict_types=1);

use App\Controllers\PageController;
use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Contracts\TemplateControllerInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Studiometa\Foehn\Views\TemplateContext;
use Timber\Site;

describe('PageController', function () {
    afterEach(function () {
        wp_stub_reset();
    });

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

    it('renders the password template while the page needs a password', function () {
        wp_stub_set_conditional('post_password_required', true);

        $rendered = [];
        $controller = new PageController(createFakeViewEngine(function (string $template) use (&$rendered): string {
            $rendered[] = $template;

            return '';
        }));

        $controller->handle(new TemplateContext(post: createFakePost(42), posts: null, site: new Site(), user: null));

        expect($rendered)->toBe(['pages/password']);
    });

    // Sans `pages/password`, ce contrôleur retombait sur `pages/page`, qui affiche
    // `post.content` : Timber le rend en entier, mot de passe ou non.
    it('ships the template it renders for a password-protected page', function () {
        expect(dirname(__DIR__, 4) . '/theme/templates/pages/password.twig')->toBeFile();
    });
});
