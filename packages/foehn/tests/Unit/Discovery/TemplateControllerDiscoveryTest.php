<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\DiscoveryProblems;
use Tests\Fixtures\InvalidTemplateControllerFixture;
use Tests\Fixtures\NoAttributeFixture;
use Tests\Fixtures\TemplateControllerFixture;

beforeEach(function () {
    $this->location = testDiscoveryLocation();
    $this->discovery = testTemplateControllerDiscovery();
});

describe('TemplateControllerDiscovery', function () {
    it('discovers template controller attributes on classes', function () {
        $this->discovery->discover(
            $this->location,
            new \Tempest\Reflection\ClassReflector(TemplateControllerFixture::class),
        );

        $items = iterator_to_array($this->discovery->getItems());

        expect($items)->toHaveCount(1);
        expect($items[0]['className'])->toBe(TemplateControllerFixture::class);
        expect($items[0]['attribute']->getTemplates())->toBe(['single', 'page']);
        expect($items[0]['attribute']->priority)->toBe(10);
    });

    it('ignores classes without template controller attribute', function () {
        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(NoAttributeFixture::class));

        expect($this->discovery->getItems())->toHaveCount(0);
    });

    it('reports a class that does not implement TemplateControllerInterface, and skips it', function () {
        expect(rejectedProblems(fn(DiscoveryProblems $problems) => testTemplateControllerDiscovery(
            problems: $problems,
        ), InvalidTemplateControllerFixture::class))->toBe([[
            'class' => InvalidTemplateControllerFixture::class,
            'attribute' => '#[AsTemplateController]',
            'reason' =>
                InvalidTemplateControllerFixture::class
                    . ' has #[AsTemplateController] but does not implement Studiometa\\Foehn\\Contracts\\TemplateControllerInterface, so it handles no template.',
            'fix' =>
                'Declare '
                    . InvalidTemplateControllerFixture::class
                    . ' with `implements \\Studiometa\\Foehn\\Contracts\\TemplateControllerInterface`.',
        ]]);
    });

    it('reports hasItems correctly', function () {
        expect($this->discovery->getItems())->toHaveCount(0);

        $this->discovery->discover(
            $this->location,
            new \Tempest\Reflection\ClassReflector(TemplateControllerFixture::class),
        );

        expect($this->discovery->getItems())->not->toHaveCount(0);
    });
});
