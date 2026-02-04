<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\HookDiscovery;
use Tests\Fixtures\HookFixture;
use Tests\Fixtures\NoAttributeFixture;

beforeEach(function () {
    $this->discovery = new HookDiscovery();
});

describe('HookDiscovery', function () {
    it('discovers action attributes on methods', function () {
        $this->discovery->discover(new ReflectionClass(HookFixture::class));

        $items = $this->discovery->getItems();
        $actions = array_values(array_filter($items, fn($item) => $item['type'] === 'action'));

        expect($actions)->toHaveCount(2);

        // init action
        expect($actions[0]['hook'])->toBe('init');
        expect($actions[0]['className'])->toBe(HookFixture::class);
        expect($actions[0]['methodName'])->toBe('onInit');
        expect($actions[0]['priority'])->toBe(10);
        expect($actions[0]['acceptedArgs'])->toBe(1);

        // wp_head action with custom priority
        expect($actions[1]['hook'])->toBe('wp_head');
        expect($actions[1]['priority'])->toBe(5);
        expect($actions[1]['acceptedArgs'])->toBe(0);
    });

    it('discovers filter attributes on methods', function () {
        $this->discovery->discover(new ReflectionClass(HookFixture::class));

        $items = $this->discovery->getItems();
        $filters = array_values(array_filter($items, fn($item) => $item['type'] === 'filter'));

        expect($filters)->toHaveCount(2);

        // the_content filter
        expect($filters[0]['hook'])->toBe('the_content');
        expect($filters[0]['className'])->toBe(HookFixture::class);
        expect($filters[0]['methodName'])->toBe('filterContent');
        expect($filters[0]['priority'])->toBe(10);
        expect($filters[0]['acceptedArgs'])->toBe(1);

        // the_title filter with custom priority
        expect($filters[1]['hook'])->toBe('the_title');
        expect($filters[1]['priority'])->toBe(20);
        expect($filters[1]['acceptedArgs'])->toBe(2);
    });

    it('ignores classes without hook attributes', function () {
        $this->discovery->discover(new ReflectionClass(NoAttributeFixture::class));

        expect($this->discovery->getItems())->toBeEmpty();
        expect($this->discovery->hasItems())->toBeFalse();
    });

    it('reports hasItems correctly', function () {
        expect($this->discovery->hasItems())->toBeFalse();

        $this->discovery->discover(new ReflectionClass(HookFixture::class));

        expect($this->discovery->hasItems())->toBeTrue();
    });
});
