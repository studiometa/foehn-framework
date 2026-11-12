<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\DiscoveryProblems;
use Studiometa\Foehn\Discovery\TimberModelDiscovery;
use Tests\Fixtures\InvalidTimberModelFixture;
use Tests\Fixtures\NoAttributeFixture;
use Tests\Fixtures\TimberModelPostFixture;
use Tests\Fixtures\TimberModelTermFixture;

beforeEach(function () {
    $this->location = testDiscoveryLocation();
    $this->discovery = new TimberModelDiscovery(new DiscoveryProblems());
});

describe('TimberModelDiscovery', function () {
    it('discovers post timber model attributes on classes', function () {
        $this->discovery->discover(
            $this->location,
            new \Tempest\Reflection\ClassReflector(TimberModelPostFixture::class),
        );

        $items = iterator_to_array($this->discovery->getItems());

        expect($items)->toHaveCount(1);
        expect($items[0]['className'])->toBe(TimberModelPostFixture::class);
        expect($items[0]['attribute']->name)->toBe('post');
        expect($items[0]['type'])->toBe('post');
    });

    it('discovers term timber model attributes on classes', function () {
        $this->discovery->discover(
            $this->location,
            new \Tempest\Reflection\ClassReflector(TimberModelTermFixture::class),
        );

        $items = iterator_to_array($this->discovery->getItems());

        expect($items)->toHaveCount(1);
        expect($items[0]['className'])->toBe(TimberModelTermFixture::class);
        expect($items[0]['attribute']->name)->toBe('category');
        expect($items[0]['type'])->toBe('term');
    });

    it('ignores classes without timber model attribute', function () {
        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(NoAttributeFixture::class));

        expect($this->discovery->getItems())->toHaveCount(0);
    });

    it('reports a class that extends neither Timber Post nor Term, and skips it', function () {
        expect(rejectedProblems(
            fn(DiscoveryProblems $problems) => new TimberModelDiscovery($problems),
            InvalidTimberModelFixture::class,
        ))->toBe([[
            'class' => InvalidTimberModelFixture::class,
            'attribute' => '#[AsTimberModel]',
            'reason' =>
                InvalidTimberModelFixture::class
                    . ' has #[AsTimberModel] but extends neither Timber\\Post nor Timber\\Term, so Timber does not map it.',
            'fix' =>
                'Declare '
                    . InvalidTimberModelFixture::class
                    . ' with `extends \\Timber\\Post` for a post type or `extends \\Timber\\Term` for a taxonomy.',
        ]]);
    });

    it('reports hasItems correctly', function () {
        expect($this->discovery->getItems())->toHaveCount(0);

        $this->discovery->discover(
            $this->location,
            new \Tempest\Reflection\ClassReflector(TimberModelPostFixture::class),
        );

        expect($this->discovery->getItems())->not->toHaveCount(0);
    });
});
