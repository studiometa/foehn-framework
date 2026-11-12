<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\DiscoveryProblems;
use Studiometa\Foehn\Discovery\SeederDiscovery;
use Tempest\Reflection\ClassReflector;
use Tests\Fixtures\NoAttributeFixture;
use Tests\Fixtures\Seeders\MediaSeeder;
use Tests\Fixtures\Seeders\NotASeeder;

beforeEach(function () {
    wp_stub_reset();
    $this->location = testDiscoveryLocation();
    $this->discovery = new SeederDiscovery($this->problems = new DiscoveryProblems());
});

describe('SeederDiscovery', function () {
    it('discovers a seeder with its attribute', function () {
        $this->discovery->discover($this->location, new ClassReflector(MediaSeeder::class));

        $items = iterator_to_array($this->discovery->getItems());

        expect($items)->toHaveCount(1);
        expect($items[0]['className'])->toBe(MediaSeeder::class);
        expect($items[0]['attribute']->order)->toBe(10);
        expect($items[0]['attribute']->description)->toBe('Photographs');
    });

    it('ignores classes without the attribute', function () {
        $this->discovery->discover($this->location, new ClassReflector(NoAttributeFixture::class));

        expect($this->discovery->getItems())->toHaveCount(0);
    });

    it('reports classes that do not implement SeederInterface, and skips them', function () {
        $this->discovery->discover($this->location, new ClassReflector(NotASeeder::class));

        expect($this->discovery->getItems())->toHaveCount(0);
        expect($this->problems->all())->toBe([[
            'class' => NotASeeder::class,
            'attribute' => '#[AsSeeder]',
            'reason' =>
                NotASeeder::class
                    . ' has #[AsSeeder] but does not implement Studiometa\\Foehn\\Contracts\\SeederInterface, so `wp foehn seed` does not run it.',
            'fix' =>
                'Declare ' . NotASeeder::class . ' with `implements \\Studiometa\\Foehn\\Contracts\\SeederInterface`.',
        ]]);
    });

    it('registers nothing and runs nothing on apply', function () {
        $this->discovery->discover($this->location, new ClassReflector(MediaSeeder::class));
        $this->discovery->apply();

        expect($GLOBALS['wp_stub_calls'])->toBe([]);
    });

    it('survives the discovery cache', function () {
        $source = new SeederDiscovery(new DiscoveryProblems());
        $location = discoverFixture($source, MediaSeeder::class);
        $restored = restoreThroughCacheFile($source, new SeederDiscovery(new DiscoveryProblems()), $location);

        $items = iterator_to_array($restored->getItems());

        expect($items)->toHaveCount(1);
        expect($items[0]['attribute']->description)->toBe('Photographs');
    });
});
