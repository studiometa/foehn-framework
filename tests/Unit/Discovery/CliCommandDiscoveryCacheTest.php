<?php

declare(strict_types=1);

use Studiometa\WPTempest\Attributes\AsCliCommand;
use Studiometa\WPTempest\Discovery\CliCommandDiscovery;
use Tempest\Container\Container;
use Tempest\Container\GenericContainer;
use Tempest\Discovery\DiscoveryItems;
use Tempest\Discovery\DiscoveryLocation;

beforeEach(function () {
    // Use the actual GenericContainer
    $container = new GenericContainer();

    $this->discovery = new CliCommandDiscovery($container);
    $this->discovery->setItems(new DiscoveryItems());
    $this->location = new DiscoveryLocation(
        namespace: 'App\\Test',
        path: __DIR__,
    );
});

describe('CliCommandDiscovery caching', function () {
    it('converts items to cacheable format', function () {
        $attribute = new AsCliCommand(
            name: 'make:block',
            description: 'Create a new block',
            longDescription: 'Creates a new Gutenberg block with all necessary files.',
        );

        $this->discovery->getItems()->add($this->location, [
            'className' => 'App\\Console\\MakeBlockCommand',
            'attribute' => $attribute,
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData)->toHaveCount(1);
        expect($cacheableData[0])->toBe([
            'className' => 'App\\Console\\MakeBlockCommand',
            'name' => 'make:block',
            'description' => 'Create a new block',
            'longDescription' => 'Creates a new Gutenberg block with all necessary files.',
        ]);
    });

    it('handles minimal configuration', function () {
        $attribute = new AsCliCommand(
            name: 'cache:clear',
            description: 'Clear the cache',
        );

        $this->discovery->getItems()->add($this->location, [
            'className' => 'App\\Console\\CacheClearCommand',
            'attribute' => $attribute,
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData)->toHaveCount(1);
        expect($cacheableData[0]['name'])->toBe('cache:clear');
        expect($cacheableData[0]['description'])->toBe('Clear the cache');
        expect($cacheableData[0]['longDescription'])->toBeNull();
    });

    it('handles multiple commands', function () {
        $attribute1 = new AsCliCommand('make:post-type', 'Create a post type');
        $attribute2 = new AsCliCommand('make:taxonomy', 'Create a taxonomy');

        $this->discovery->getItems()->add($this->location, [
            'className' => 'App\\Console\\MakePostTypeCommand',
            'attribute' => $attribute1,
        ]);
        $this->discovery->getItems()->add($this->location, [
            'className' => 'App\\Console\\MakeTaxonomyCommand',
            'attribute' => $attribute2,
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData)->toHaveCount(2);
        expect($cacheableData[0]['name'])->toBe('make:post-type');
        expect($cacheableData[1]['name'])->toBe('make:taxonomy');
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

        $this->discovery->restoreFromCache($cachedData);

        expect($this->discovery->wasRestoredFromCache())->toBeTrue();
    });
});
