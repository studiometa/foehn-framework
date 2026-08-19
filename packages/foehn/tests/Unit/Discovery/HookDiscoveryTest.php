<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsAction;
use Studiometa\Foehn\Attributes\AsFilter;
use Studiometa\Foehn\Discovery\HookDiscovery;
use Tempest\Container\GenericContainer;
use Tests\Fixtures\HookFixture;
use Tests\Fixtures\NoAttributeFixture;

beforeEach(function () {
    $this->location = testDiscoveryLocation();
    $this->discovery = new HookDiscovery(new GenericContainer());
});

describe('HookDiscovery', function () {
    it('discovers action attributes on methods', function () {
        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(HookFixture::class));

        $items = iterator_to_array($this->discovery->getItems());
        $actions = array_values(array_filter($items, fn($item) => $item['attribute'] instanceof AsAction));

        expect($actions)->toHaveCount(2);

        // init action
        expect($actions[0]['attribute']->hook)->toBe('init');
        expect($actions[0]['className'])->toBe(HookFixture::class);
        expect($actions[0]['methodName'])->toBe('onInit');
        expect($actions[0]['attribute']->priority)->toBe(10);
        expect($actions[0]['attribute']->acceptedArgs)->toBe(1);

        // wp_head action with custom priority
        expect($actions[1]['attribute']->hook)->toBe('wp_head');
        expect($actions[1]['attribute']->priority)->toBe(5);
        expect($actions[1]['attribute']->acceptedArgs)->toBe(0);
    });

    it('discovers filter attributes on methods', function () {
        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(HookFixture::class));

        $items = iterator_to_array($this->discovery->getItems());
        $filters = array_values(array_filter($items, fn($item) => $item['attribute'] instanceof AsFilter));

        expect($filters)->toHaveCount(2);

        // the_content filter
        expect($filters[0]['attribute']->hook)->toBe('the_content');
        expect($filters[0]['className'])->toBe(HookFixture::class);
        expect($filters[0]['methodName'])->toBe('filterContent');
        expect($filters[0]['attribute']->priority)->toBe(10);
        expect($filters[0]['attribute']->acceptedArgs)->toBe(1);

        // the_title filter with custom priority
        expect($filters[1]['attribute']->hook)->toBe('the_title');
        expect($filters[1]['attribute']->priority)->toBe(20);
        expect($filters[1]['attribute']->acceptedArgs)->toBe(2);
    });

    it('ignores classes without hook attributes', function () {
        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(NoAttributeFixture::class));

        expect($this->discovery->getItems())->toHaveCount(0);
        expect($this->discovery->getItems())->toHaveCount(0);
    });

    it('reports hasItems correctly', function () {
        expect($this->discovery->getItems())->toHaveCount(0);

        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(HookFixture::class));

        expect($this->discovery->getItems())->not->toHaveCount(0);
    });
});
