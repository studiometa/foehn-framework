<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\TemplateControllerDiscovery;
use Tests\Fixtures\InvalidTemplateControllerFixture;
use Tests\Fixtures\NoAttributeFixture;
use Tests\Fixtures\TemplateControllerFixture;
use Studiometa\Foehn\Discovery\DiscoveryLocation;

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    $this->discovery = new TemplateControllerDiscovery();
});

describe('TemplateControllerDiscovery', function () {
    it('discovers template controller attributes on classes', function () {
        $this->discovery->discover($this->location, new ReflectionClass(TemplateControllerFixture::class));

        $items = $this->discovery->getItems()->all();

        expect($items)->toHaveCount(1);
        expect($items[0]['className'])->toBe(TemplateControllerFixture::class);
        expect($items[0]['templates'])->toBe(['single', 'page']);
        expect($items[0]['priority'])->toBe(10);
    });

    it('ignores classes without template controller attribute', function () {
        $this->discovery->discover($this->location, new ReflectionClass(NoAttributeFixture::class));

        expect($this->discovery->getItems()->isEmpty())->toBeTrue();
    });

    it('throws when class does not implement TemplateControllerInterface', function () {
        expect(fn() => $this->discovery->discover($this->location, new ReflectionClass(InvalidTemplateControllerFixture::class)))
            ->toThrow(InvalidArgumentException::class, 'must implement');
    });

    it('reports hasItems correctly', function () {
        expect($this->discovery->hasItems())->toBeFalse();

        $this->discovery->discover($this->location, new ReflectionClass(TemplateControllerFixture::class));

        expect($this->discovery->hasItems())->toBeTrue();
    });
});
