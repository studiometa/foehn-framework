<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\BlockDiscovery;
use Studiometa\Foehn\Discovery\DiscoveryLocation;
use Tests\Fixtures\BlockFixture;
use Tests\Fixtures\ContainerBlockFixture;
use Tests\Fixtures\InvalidBlockFixture;
use Tests\Fixtures\NoAttributeFixture;

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    $this->discovery = new BlockDiscovery();
});

describe('BlockDiscovery', function () {
    it('discovers block attributes on classes', function () {
        $this->discovery->discover($this->location, new ReflectionClass(BlockFixture::class));

        $items = $this->discovery->getItems()->all();

        expect($items)->toHaveCount(1);
        expect($items[0]['className'])->toBe(BlockFixture::class);
        expect($items[0]['attribute']->name)->toBe('test/hero');
        expect($items[0]['attribute']->title)->toBe('Hero Block');
        expect($items[0]['attribute']->category)->toBe('design');
        expect($items[0]['attribute']->icon)->toBe('cover-image');
        expect($items[0]['attribute']->description)->toBe('A hero block.');
        expect($items[0]['attribute']->keywords)->toBe(['hero', 'banner']);
    });

    it('ignores classes without block attribute', function () {
        $this->discovery->discover($this->location, new ReflectionClass(NoAttributeFixture::class));

        expect($this->discovery->getItems()->isEmpty())->toBeTrue();
    });

    it('throws when class does not implement BlockInterface', function () {
        expect(fn() => $this->discovery->discover($this->location, new ReflectionClass(InvalidBlockFixture::class)))
            ->toThrow(InvalidArgumentException::class, 'must implement');
    });

    it('reports hasItems correctly', function () {
        expect($this->discovery->hasItems())->toBeFalse();

        $this->discovery->discover($this->location, new ReflectionClass(BlockFixture::class));

        expect($this->discovery->hasItems())->toBeTrue();
    });

    it('discovers the inner blocks configuration of a container block', function () {
        $this->discovery->discover($this->location, new ReflectionClass(ContainerBlockFixture::class));

        $items = $this->discovery->getItems()->all();

        expect($items[0]['attribute']->allowedBlocks)->toBe(['core/heading', 'core/paragraph']);
        expect($items[0]['attribute']->innerBlocksTemplate)->toBe([['core/heading', ['level' => 2]]]);
        expect($items[0]['attribute']->innerBlocksTemplateLock)->toBe('insert');
    });
});

describe('BlockDiscovery::getEditorDefinitions', function () {
    it('returns an empty payload when nothing was discovered', function () {
        expect($this->discovery->getEditorDefinitions())->toBe([]);
    });

    it('describes a non container block', function () {
        $this->discovery->discover($this->location, new ReflectionClass(BlockFixture::class));

        $definitions = $this->discovery->getEditorDefinitions();

        expect($definitions)->toHaveCount(1);
        expect($definitions[0]['name'])->toBe('test/hero');
        expect($definitions[0]['innerBlocks'])->toBeNull();
        expect($definitions[0]['attributes']['title'])->toBe([
            'control' => 'text',
            'type' => 'string',
            'label' => 'Title',
            'help' => null,
            'options' => null,
        ]);
    });

    it('describes a container block', function () {
        $this->discovery->discover($this->location, new ReflectionClass(ContainerBlockFixture::class));

        $definitions = $this->discovery->getEditorDefinitions();

        expect($definitions[0]['name'])->toBe('test/section');
        expect($definitions[0]['innerBlocks'])->toBe([
            'allowedBlocks' => ['core/heading', 'core/paragraph'],
            'template' => [['core/heading', ['level' => 2]]],
            'templateLock' => 'insert',
        ]);
        expect($definitions[0]['attributes']['ctaLabel'])->toBe([
            'control' => 'text',
            'type' => 'string',
            'label' => 'Button text',
            'help' => 'Keep it short',
            'options' => null,
        ]);
        expect($definitions[0]['attributes']['variant']['control'])->toBe('select');
        expect($definitions[0]['attributes']['variant']['label'])->toBe('Variant');
        expect($definitions[0]['attributes']['variant']['options'])->toBe([
            ['label' => 'Light', 'value' => 'light'],
            ['label' => 'Dark', 'value' => 'dark'],
        ]);
        expect($definitions[0]['attributes']['image_id']['control'])->toBe('image');
        expect($definitions[0]['attributes']['image_id']['type'])->toBe('integer');
        expect($definitions[0]['attributes']['image_id']['label'])->toBe('Image id');
    });

    it('returns the same payload for a discovery restored from cache', function () {
        $this->discovery->discover($this->location, new ReflectionClass(ContainerBlockFixture::class));
        $this->discovery->discover($this->location, new ReflectionClass(BlockFixture::class));

        $live = $this->discovery->getEditorDefinitions();

        $restored = new BlockDiscovery();
        $restored->restoreFromCache($this->discovery->getCacheableData());

        expect($restored->getEditorDefinitions())->toBe($live);
    });
});
