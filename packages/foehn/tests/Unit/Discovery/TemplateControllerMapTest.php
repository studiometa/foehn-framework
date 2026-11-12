<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Discovery\TemplateControllerMap;

/**
 * A discovery item, as TemplateControllerDiscovery stores it.
 *
 * @param string|list<string> $templates
 * @return array{attribute: AsTemplateController, className: string}
 */
function controllerItem(string $className, string|array $templates, int $priority = 10): array
{
    return ['attribute' => new AsTemplateController($templates, $priority), 'className' => $className];
}

describe('TemplateControllerMap', function () {
    it('finds the controller of an exact template', function () {
        $map = TemplateControllerMap::fromItems([controllerItem('App\\Single', ['single', 'page'])]);

        expect($map->find('page'))->toBe(['className' => 'App\\Single', 'priority' => 10]);
        expect($map->find('404'))->toBeNull();
    });

    it('prefers an exact template to a wildcard', function () {
        $map = TemplateControllerMap::fromItems([
            controllerItem('App\\Any', 'single-*', priority: 1),
            controllerItem('App\\Product', 'single-product'),
        ]);

        expect($map->find('single-product')['className'])->toBe('App\\Product');
        expect($map->find('single-event')['className'])->toBe('App\\Any');
    });

    it('gives a template claimed twice to the lowest priority', function () {
        $map = TemplateControllerMap::fromItems([
            controllerItem('App\\Low', 'single', priority: 5),
            controllerItem('App\\High', 'single', priority: 20),
        ]);

        expect($map->find('single')['className'])->toBe('App\\Low');
        expect($map->claims())->toBe([
            [
                'template' => 'single',
                'controller' => ['className' => 'App\\Low', 'priority' => 5],
                'overridden' => ['App\\High'],
            ],
        ]);
    });

    it('gives a tie to the controller discovered last', function () {
        $map = TemplateControllerMap::fromItems([
            controllerItem('App\\First', 'single'),
            controllerItem('App\\Last', 'single'),
        ]);

        expect($map->find('single')['className'])->toBe('App\\Last');
        expect($map->claims()[0]['overridden'])->toBe(['App\\First']);
    });

    it('tries wildcards in priority order, then in discovery order', function () {
        $map = TemplateControllerMap::fromItems([
            controllerItem('App\\Broad', '*'),
            controllerItem('App\\Archives', 'archive-*', priority: 5),
            controllerItem('App\\Late', 'archive-*-x'),
        ]);

        expect($map->find('archive-event')['className'])->toBe('App\\Archives');
        expect($map->find('archive-event-x')['className'])->toBe('App\\Archives');
        expect($map->find('search')['className'])->toBe('App\\Broad');
    });

    it('keeps a numeric template name a string, and passes over it for a wildcard lookup', function () {
        $map = TemplateControllerMap::fromItems([
            controllerItem('App\\NotFound', '404'),
            controllerItem('App\\Singles', 'single-*'),
        ]);

        expect($map->claims()[0]['template'])->toBe('404');
        expect($map->find('404')['className'])->toBe('App\\NotFound');
        expect($map->find('single-post')['className'])->toBe('App\\Singles');
    });

    it('matches wildcards against the whole name', function () {
        expect(TemplateControllerMap::matches('single-product', 'single-*'))->toBeTrue();
        expect(TemplateControllerMap::matches('single', 'single-*'))->toBeFalse();
        expect(TemplateControllerMap::matches('page.single', 'page*single'))->toBeTrue();
        expect(TemplateControllerMap::matches('xsingle-a', 'single-*'))->toBeFalse();
    });
});
