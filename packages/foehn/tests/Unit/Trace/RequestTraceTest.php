<?php

declare(strict_types=1);

use Studiometa\Foehn\Trace\RequestTrace;

describe('RequestTrace', function () {
    beforeEach(function () {
        wp_stub_reset();
        $this->trace = new RequestTrace();
    });

    it('keeps the template, the controller and the last view of a request that is not traced', function () {
        $this->trace->matchTemplate('single', 'App\\Controllers\\SingleController');
        $this->trace->renderView('pages/single.twig', ['post', 'site']);
        $this->trace->tryTemplates(['single.php', 'index.php']);
        $this->trace->renderBlock('core/paragraph');

        expect($this->trace->isTracing())->toBeFalse();
        expect($this->trace->errorFields())->toBe([
            'template' => 'single',
            'controller' => 'App\\Controllers\\SingleController',
            'view' => 'pages/single.twig',
            'context' => ['post', 'site'],
            'requestId' => null,
        ]);
        expect($this->trace->toArray()['hierarchy'])->toBe([]);
        expect($this->trace->toArray()['views'])->toBe([]);
        expect($this->trace->toArray()['blocks'])->toBe([]);
    });

    it('keeps every list once the request has an id', function () {
        $this->trace->begin('a1b2c3');
        $this->trace->tryTemplates(['single-post-hello.php', 'single-post.php', 'single.php']);
        $this->trace->tryTemplates(['singular.php', 'single.php']);
        $this->trace->useTemplateFile('theme/index.php');
        $this->trace->matchTemplate('single', 'App\\Controllers\\SingleController');
        $this->trace->renderFirst(['pages/single-post', 'pages/single'], 'pages/single');
        $this->trace->provideContext('pages/single', ['App\\Context\\Site', 'App\\Context\\Menus']);
        $this->trace->provideContext('pages/single', ['App\\Context\\Site']);
        $this->trace->renderView('pages/single.twig', ['post', 'site']);
        $this->trace->renderView('partials/card.twig', ['card']);
        $this->trace->renderView('partials/card.twig', ['card', 'index']);
        $this->trace->renderBlock('core/paragraph');
        $this->trace->renderBlock('core/paragraph');
        $this->trace->renderBlock('app/hero');

        expect($this->trace->errorFields()['requestId'])->toBe('a1b2c3');
        expect($this->trace->errorFields()['view'])->toBe('partials/card.twig');
        expect($this->trace->toArray())->toBe([
            'hierarchy' => ['single-post-hello.php', 'single-post.php', 'single.php', 'singular.php'],
            'templateFile' => 'theme/index.php',
            'template' => 'single',
            'controller' => 'App\\Controllers\\SingleController',
            'views' => [
                ['view' => 'pages/single.twig', 'count' => 1, 'keys' => ['post', 'site']],
                ['view' => 'partials/card.twig', 'count' => 2, 'keys' => ['card']],
            ],
            'candidates' => [['view' => 'pages/single', 'candidates' => ['pages/single-post', 'pages/single']]],
            'providers' => [
                ['view' => 'pages/single', 'class' => 'App\\Context\\Site'],
                ['view' => 'pages/single', 'class' => 'App\\Context\\Menus'],
            ],
            'blocks' => ['core/paragraph' => 2, 'app/hero' => 1],
        ]);
    });

    it('caps each list, and the context keys of a view', function () {
        $this->trace->begin('a1b2c3');

        for ($index = 0; $index < (RequestTrace::MAX_ITEMS + 5); $index++) {
            $this->trace->renderView("partials/item-{$index}.twig", []);
            $this->trace->renderBlock("app/block-{$index}");
            $this->trace->tryTemplates(["page-{$index}.php"]);
        }

        $this->trace->renderView('pages/long.twig', array_map(strval(...), range(1, RequestTrace::MAX_KEYS + 10)));
        $this->trace->renderBlock('app/block-0');

        expect($this->trace->toArray()['views'])->toHaveCount(RequestTrace::MAX_ITEMS);
        expect($this->trace->toArray()['blocks'])->toHaveCount(RequestTrace::MAX_ITEMS);
        expect($this->trace->toArray()['blocks']['app/block-0'])->toBe(2);
        expect($this->trace->toArray()['hierarchy'])->toHaveCount(RequestTrace::MAX_ITEMS);
        expect($this->trace->errorFields()['context'])->toHaveCount(RequestTrace::MAX_KEYS);
    });

    it('remembers the view Timber renders and its context keys, and leaves the data unchanged', function () {
        expect($this->trace->onRenderData(['post' => 1, 'site' => 2], 'pages/page.twig'))->toBe([
            'post' => 1,
            'site' => 2,
        ]);
        expect($this->trace->onRenderData('not an array', 'pages/page.twig'))->toBe('not an array');

        expect($this->trace->errorFields()['view'])->toBe('pages/page.twig');
        expect($this->trace->errorFields()['context'])->toBe(['post', 'site']);
    });

    it('listens to the views Timber renders once', function () {
        $this->trace->listen();
        $this->trace->listen();

        expect(wp_stub_get_calls('add_filter'))->toHaveCount(1);
        expect(wp_stub_get_calls('add_filter')[0]['args']['hook'])->toBe('timber/loader/render_data');
    });
});
