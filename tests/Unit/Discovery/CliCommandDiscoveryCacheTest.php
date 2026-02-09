<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\CliCommandDiscovery;
use Tempest\Container\GenericContainer;
use Studiometa\Foehn\Discovery\DiscoveryLocation;

beforeEach(function () {
    $container = new GenericContainer();

    $this->discovery = new CliCommandDiscovery($container);
});

describe('CliCommandDiscovery caching', function () {
    it('converts items to cacheable format', function () {
        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, $this->location, [
            'className' => 'App\\Console\\MakeBlockCommand',
            'name' => 'make:block',
            'description' => 'Create a new block',
            'longDescription' => 'Creates a new Gutenberg block with all necessary files.',
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData['App\\'])->toHaveCount(1);
        expect($cacheableData['App\\'][0])->toBe([
            'className' => 'App\\Console\\MakeBlockCommand',
            'name' => 'make:block',
            'description' => 'Create a new block',
            'longDescription' => 'Creates a new Gutenberg block with all necessary files.',
        ]);
    });

    it('handles minimal configuration', function () {
        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, $this->location, [
            'className' => 'App\\Console\\CacheClearCommand',
            'name' => 'cache:clear',
            'description' => 'Clear the cache',
            'longDescription' => null,
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData['App\\'])->toHaveCount(1);
        expect($cacheableData['App\\'][0]['name'])->toBe('cache:clear');
        expect($cacheableData['App\\'][0]['description'])->toBe('Clear the cache');
        expect($cacheableData['App\\'][0]['longDescription'])->toBeNull();
    });

    it('handles multiple commands', function () {
        $ref = new ReflectionMethod($this->discovery, 'addItem');

        $ref->invoke($this->discovery, $this->location, [
            'className' => 'App\\Console\\MakePostTypeCommand',
            'name' => 'make:post-type',
            'description' => 'Create a post type',
            'longDescription' => null,
        ]);
        $ref->invoke($this->discovery, $this->location, [
            'className' => 'App\\Console\\MakeTaxonomyCommand',
            'name' => 'make:taxonomy',
            'description' => 'Create a taxonomy',
            'longDescription' => null,
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData['App\\'])->toHaveCount(2);
        expect($cacheableData['App\\'][0]['name'])->toBe('make:post-type');
        expect($cacheableData['App\\'][1]['name'])->toBe('make:taxonomy');
    });

    it('can restore from cache', function () {
        $cachedData = [
            [
                'className' => 'App\\Console\\TestCommand',
                'name' => 'test:run',
                'description' => 'Run tests',
                'longDescription' => 'Runs all test suites.',
            ],
        ];

        $this->discovery->restoreFromCache(['App\\' => $cachedData]);

        expect($this->discovery->wasRestoredFromCache())->toBeTrue();
    });
});
