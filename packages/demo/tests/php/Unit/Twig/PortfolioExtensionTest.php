<?php

declare(strict_types=1);

use Demo\Twig\PortfolioExtension;
use Studiometa\Foehn\Attributes\AsTwigExtension;

describe('PortfolioExtension', function () {
    it('is discovered as a Twig extension', function () {
        expect(new ReflectionClass(PortfolioExtension::class)->getAttributes(AsTwigExtension::class))->toHaveCount(1);
    });

    it('adds a plural filter', function () {
        $names = array_map(static fn($filter) => $filter->getName(), new PortfolioExtension()->getFilters());

        expect($names)->toBe(['plural']);
    });

    it('puts a count with its noun', function () {
        $extension = new PortfolioExtension();

        expect($extension->plural(1, 'image'))->toBe('1 image');
        expect($extension->plural(5, 'image'))->toBe('5 images');
        expect($extension->plural('6', 'series', 'series'))->toBe('6 series');
        expect($extension->plural(0, 'result'))->toBe('0 results');
    });
});
