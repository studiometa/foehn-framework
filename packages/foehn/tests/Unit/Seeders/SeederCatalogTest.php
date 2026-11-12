<?php

declare(strict_types=1);

use Studiometa\Foehn\Seeders\SeederCatalog;
use Tests\Fixtures\Seeders\Archive\PagesSeeder as ArchivedPagesSeeder;
use Tests\Fixtures\Seeders\MediaSeeder;
use Tests\Fixtures\Seeders\MenusSeeder;
use Tests\Fixtures\Seeders\PagesSeeder;

/**
 * A catalog over tests/Fixtures/Seeders.
 */
function seederCatalog(): SeederCatalog
{
    return new SeederCatalog(testDiscoveryRunner(bootTestContainer(), testFixturePath('Seeders')));
}

afterEach(fn() => tearDownTestContainer());

describe('SeederCatalog', function () {
    it('lists the seeders by order, then by class name', function () {
        expect(array_column(seederCatalog()->all(), 'className'))->toBe([
            MediaSeeder::class,
            MenusSeeder::class,
            PagesSeeder::class,
            ArchivedPagesSeeder::class,
        ]);
    });

    it('keeps the attribute of each seeder', function () {
        $first = seederCatalog()->all()[0];

        expect($first['attribute']->order)->toBe(10);
        expect($first['attribute']->description)->toBe('Photographs');
    });

    it('finds a seeder by its full class name, with or without a leading backslash', function () {
        expect(array_column(seederCatalog()->find(MediaSeeder::class), 'className'))->toBe([MediaSeeder::class]);
        expect(array_column(seederCatalog()->find('\\' . MediaSeeder::class), 'className'))->toBe([MediaSeeder::class]);
    });

    it('finds a seeder by its short class name', function () {
        expect(array_column(seederCatalog()->find('MenusSeeder'), 'className'))->toBe([MenusSeeder::class]);
    });

    it('finds every seeder that has a short class name', function () {
        expect(array_column(seederCatalog()->find('PagesSeeder'), 'className'))->toBe([
            PagesSeeder::class,
            ArchivedPagesSeeder::class,
        ]);
    });

    it('finds nothing for an unknown name', function () {
        expect(seederCatalog()->find('EventSeeder'))->toBe([]);
    });

    it('is empty when the project has no seeder', function () {
        $catalog = new SeederCatalog(testDiscoveryRunner(bootTestContainer(), testAppPath()));

        expect($catalog->all())->toBe([]);
    });
});
