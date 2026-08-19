<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\TwigExtensionDiscovery;
use Tempest\Container\GenericContainer;
use Tests\Fixtures\InvalidTwigExtensionFixture;
use Tests\Fixtures\NoAttributeFixture;
use Tests\Fixtures\TwigExtensionFixture;
use Tests\Fixtures\TwigExtensionWithPriorityFixture;

beforeEach(function () {
    $this->location = testDiscoveryLocation();
    $this->discovery = new TwigExtensionDiscovery(new GenericContainer());
});

describe('TwigExtensionDiscovery', function () {
    it('discovers classes with AsTwigExtension attribute', function () {
        $this->discovery->discover(
            $this->location,
            new \Tempest\Reflection\ClassReflector(TwigExtensionFixture::class),
        );

        $items = iterator_to_array($this->discovery->getItems());

        expect($items)->toHaveCount(1);
        expect($items[0]['className'])->toBe(TwigExtensionFixture::class);
        expect($items[0]['attribute']->priority)->toBe(10);
    });

    it('discovers custom priority', function () {
        $this->discovery->discover(
            $this->location,
            new \Tempest\Reflection\ClassReflector(TwigExtensionWithPriorityFixture::class),
        );

        $items = iterator_to_array($this->discovery->getItems());

        expect($items)->toHaveCount(1);
        expect($items[0]['className'])->toBe(TwigExtensionWithPriorityFixture::class);
        expect($items[0]['attribute']->priority)->toBe(5);
    });

    it('ignores classes without the attribute', function () {
        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(NoAttributeFixture::class));

        expect($this->discovery->getItems())->toHaveCount(0);
        expect($this->discovery->getItems())->toHaveCount(0);
    });

    it('ignores classes that do not extend AbstractExtension', function () {
        $this->discovery->discover(
            $this->location,
            new \Tempest\Reflection\ClassReflector(InvalidTwigExtensionFixture::class),
        );

        expect($this->discovery->getItems())->toHaveCount(0);
        expect($this->discovery->getItems())->toHaveCount(0);
    });

    it('reports hasItems correctly', function () {
        expect($this->discovery->getItems())->toHaveCount(0);

        $this->discovery->discover(
            $this->location,
            new \Tempest\Reflection\ClassReflector(TwigExtensionFixture::class),
        );

        expect($this->discovery->getItems())->not->toHaveCount(0);
    });

    it('provides cacheable data', function () {
        $this->discovery->discover(
            $this->location,
            new \Tempest\Reflection\ClassReflector(TwigExtensionFixture::class),
        );
        $this->discovery->discover(
            $this->location,
            new \Tempest\Reflection\ClassReflector(TwigExtensionWithPriorityFixture::class),
        );

        $items = $this->discovery->getItems()->getForLocation($this->location);

        expect($items)->toHaveCount(2);
        expect($items[0]['className'])->toBe(TwigExtensionFixture::class);
        expect($items[1]['className'])->toBe(TwigExtensionWithPriorityFixture::class);
    });

    it('can restore from cache', function () {
        $this->discovery->discover(
            $this->location,
            new \Tempest\Reflection\ClassReflector(TwigExtensionFixture::class),
        );
        $this->discovery->discover(
            $this->location,
            new \Tempest\Reflection\ClassReflector(TwigExtensionWithPriorityFixture::class),
        );

        $restored = restoreThroughCacheFile($this->discovery, new TwigExtensionDiscovery(new GenericContainer()));

        expect(iterator_to_array($restored->getItems()))->toEqual(iterator_to_array($this->discovery->getItems()));
    });
});
