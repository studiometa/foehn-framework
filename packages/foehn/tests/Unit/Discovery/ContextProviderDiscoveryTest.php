<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\ContextProviderDiscovery;
use Studiometa\Foehn\Discovery\DiscoveryProblems;
use Tests\Fixtures\ContextProviderFixture;
use Tests\Fixtures\InvalidContextProviderFixture;
use Tests\Fixtures\NoAttributeFixture;

beforeEach(function () {
    $this->location = testDiscoveryLocation();
    $this->discovery = new ContextProviderDiscovery(new DiscoveryProblems());
});

describe('ContextProviderDiscovery', function () {
    it('discovers context provider attributes on classes', function () {
        $this->discovery->discover(
            $this->location,
            new \Tempest\Reflection\ClassReflector(ContextProviderFixture::class),
        );

        $items = iterator_to_array($this->discovery->getItems());

        expect($items)->toHaveCount(1);
        expect($items[0]['className'])->toBe(ContextProviderFixture::class);
        expect($items[0]['attribute']->getTemplates())->toBe(['single', 'page']);
        expect($items[0]['attribute']->priority)->toBe(5);
    });

    it('ignores classes without context provider attribute', function () {
        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(NoAttributeFixture::class));

        expect($this->discovery->getItems())->toHaveCount(0);
    });

    it('reports a class that does not implement ContextProviderInterface, and skips it', function () {
        expect(rejectedProblems(
            fn(DiscoveryProblems $problems) => new ContextProviderDiscovery($problems),
            InvalidContextProviderFixture::class,
        ))->toBe([[
            'class' => InvalidContextProviderFixture::class,
            'attribute' => '#[AsContextProvider]',
            'reason' =>
                InvalidContextProviderFixture::class
                    . ' has #[AsContextProvider] but does not implement Studiometa\\Foehn\\Contracts\\ContextProviderInterface, so it provides no context.',
            'fix' =>
                'Declare '
                    . InvalidContextProviderFixture::class
                    . ' with `implements \\Studiometa\\Foehn\\Contracts\\ContextProviderInterface`.',
        ]]);
    });

    it('reports hasItems correctly', function () {
        expect($this->discovery->getItems())->toHaveCount(0);

        $this->discovery->discover(
            $this->location,
            new \Tempest\Reflection\ClassReflector(ContextProviderFixture::class),
        );

        expect($this->discovery->getItems())->not->toHaveCount(0);
    });
});
