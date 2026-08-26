<?php

declare(strict_types=1);

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

    it('throws when class does not implement TemplateControllerInterface', function () {
        expect(fn() => $this->discovery->discover(
            $this->location,
            new \Tempest\Reflection\ClassReflector(InvalidTemplateControllerFixture::class),
        ))
            ->toThrow(InvalidArgumentException::class, 'must implement');
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
