<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\QueryFiltersConfig;

describe('QueryFiltersConfig', function () {
    it('can be instantiated with defaults', function () {
        $config = new QueryFiltersConfig();

        expect($config->taxonomies)->toBe([]);
        expect($config->publicVars)->toBe([]);
    });

    it('can be instantiated with taxonomies', function () {
        $config = new QueryFiltersConfig(taxonomies: [
            'genre' => ['in', 'not_in', 'and'],
            'product_cat' => ['in'],
        ]);

        expect($config->taxonomies)->toBe([
            'genre' => ['in', 'not_in', 'and'],
            'product_cat' => ['in'],
        ]);
    });

    it('can be instantiated with public vars', function () {
        $config = new QueryFiltersConfig(publicVars: [
            'posts_per_page' => [12, 24, 48],
            'custom_var' => true,
        ]);

        expect($config->publicVars)->toBe([
            'posts_per_page' => [12, 24, 48],
            'custom_var' => true,
        ]);
    });

    describe('getQueryVars', function () {
        it('returns empty array for empty config', function () {
            $config = new QueryFiltersConfig();

            expect($config->getQueryVars())->toBe([]);
        });

        it('returns base taxonomy var for in operator', function () {
            $config = new QueryFiltersConfig(taxonomies: ['genre' => ['in']]);

            expect($config->getQueryVars())->toBe(['genre']);
        });

        it('returns suffixed vars for other operators', function () {
            $config = new QueryFiltersConfig(taxonomies: ['genre' => ['in', 'not_in', 'and', 'exists']]);

            expect($config->getQueryVars())->toBe([
                'genre',
                'genre__not_in',
                'genre__and',
                'genre__exists',
            ]);
        });

        it('includes public vars', function () {
            $config = new QueryFiltersConfig(publicVars: ['posts_per_page' => [12, 24, 48]]);

            expect($config->getQueryVars())->toBe(['posts_per_page']);
        });

        it('combines taxonomy and public vars', function () {
            $config = new QueryFiltersConfig(taxonomies: ['genre' => ['in', 'not_in']], publicVars: ['posts_per_page' =>
                [12, 24, 48]]);

            expect($config->getQueryVars())->toBe([
                'genre',
                'genre__not_in',
                'posts_per_page',
            ]);
        });
    });

    describe('hasTaxonomy', function () {
        it('returns true for registered taxonomy', function () {
            $config = new QueryFiltersConfig(taxonomies: ['genre' => ['in']]);

            expect($config->hasTaxonomy('genre'))->toBeTrue();
        });

        it('returns false for unregistered taxonomy', function () {
            $config = new QueryFiltersConfig(taxonomies: ['genre' => ['in']]);

            expect($config->hasTaxonomy('category'))->toBeFalse();
        });
    });

    describe('hasOperator', function () {
        it('returns true for allowed operator', function () {
            $config = new QueryFiltersConfig(taxonomies: ['genre' => ['in', 'not_in']]);

            expect($config->hasOperator('genre', 'in'))->toBeTrue();
            expect($config->hasOperator('genre', 'not_in'))->toBeTrue();
        });

        it('returns false for disallowed operator', function () {
            $config = new QueryFiltersConfig(taxonomies: ['genre' => ['in']]);

            expect($config->hasOperator('genre', 'not_in'))->toBeFalse();
            expect($config->hasOperator('genre', 'and'))->toBeFalse();
        });

        it('returns false for unregistered taxonomy', function () {
            $config = new QueryFiltersConfig(taxonomies: ['genre' => ['in']]);

            expect($config->hasOperator('category', 'in'))->toBeFalse();
        });
    });

    describe('validatePublicVar', function () {
        it('returns true for allowed value', function () {
            $config = new QueryFiltersConfig(publicVars: ['posts_per_page' => [12, 24, 48]]);

            expect($config->validatePublicVar('posts_per_page', 12))->toBeTrue();
            expect($config->validatePublicVar('posts_per_page', 24))->toBeTrue();
            expect($config->validatePublicVar('posts_per_page', 48))->toBeTrue();
        });

        it('returns false for disallowed value', function () {
            $config = new QueryFiltersConfig(publicVars: ['posts_per_page' => [12, 24, 48]]);

            expect($config->validatePublicVar('posts_per_page', 100))->toBeFalse();
            expect($config->validatePublicVar('posts_per_page', 'all'))->toBeFalse();
        });

        it('returns false for unregistered var', function () {
            $config = new QueryFiltersConfig();

            expect($config->validatePublicVar('posts_per_page', 12))->toBeFalse();
        });

        it('returns true for any value when set to true', function () {
            $config = new QueryFiltersConfig(publicVars: ['custom_var' => true]);

            expect($config->validatePublicVar('custom_var', 'anything'))->toBeTrue();
            expect($config->validatePublicVar('custom_var', 123))->toBeTrue();
            expect($config->validatePublicVar('custom_var', true))->toBeTrue();
        });

        it('uses loose comparison for string/int flexibility', function () {
            $config = new QueryFiltersConfig(publicVars: ['posts_per_page' => [12, 24, 48]]);

            // String '12' should match int 12
            expect($config->validatePublicVar('posts_per_page', '12'))->toBeTrue();
            expect($config->validatePublicVar('posts_per_page', '24'))->toBeTrue();
        });
    });
});
