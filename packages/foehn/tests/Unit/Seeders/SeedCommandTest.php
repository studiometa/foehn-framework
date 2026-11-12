<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\Seeders\SeedCommand;
use Studiometa\Foehn\Seeders\SeederCatalog;
use Tests\Fixtures\Seeders\Archive\PagesSeeder as ArchivedPagesSeeder;
use Tests\Fixtures\Seeders\MediaSeeder;
use Tests\Fixtures\Seeders\MenusSeeder;
use Tests\Fixtures\Seeders\PagesSeeder;

/**
 * The command over tests/Fixtures/Seeders, or over an app with no seeder.
 */
function seedCommand(?string $appPath = null): SeedCommand
{
    $container = bootTestContainer();

    return new SeedCommand(
        new WpCli(),
        new SeederCatalog(testDiscoveryRunner($container, $appPath ?? testFixturePath('Seeders'))),
        $container,
    );
}

/**
 * The seeders that ran, in order.
 *
 * @return list<string>
 */
function seededClasses(): array
{
    return array_column(array_column(wp_stub_get_calls('seeder_run'), 'args'), 'class');
}

/**
 * @return list<string>
 */
function seedMessages(string $call): array
{
    return array_column(array_column(wp_stub_get_calls($call), 'args'), 'message');
}

beforeEach(fn() => wp_stub_reset());

afterEach(function () {
    unset($GLOBALS['seeder_failure']);
    tearDownTestContainer();
});

describe('wp foehn seed', function () {
    it('is registered as foehn seed', function () {
        $attribute = new ReflectionClass(SeedCommand::class)->getAttributes(AsCliCommand::class)[0]->newInstance();

        expect($attribute->name)->toBe('seed');
    });

    it('runs every seeder in order', function () {
        seedCommand()([], []);

        expect(seededClasses())->toBe([
            MediaSeeder::class,
            MenusSeeder::class,
            PagesSeeder::class,
            ArchivedPagesSeeder::class,
        ]);
        expect(seedMessages('wp_cli_success'))->toBe(['4 seeders run.']);
        expect(seedMessages('wp_cli_log')[0])->toBe('Seeding: 10  ' . MediaSeeder::class . ' — Photographs');
        expect(wp_stub_get_calls('wp_cli_error'))->toBe([]);
    });

    it('runs one seeder named by its short class name', function () {
        seedCommand()([], ['class' => 'MenusSeeder']);

        expect(seededClasses())->toBe([MenusSeeder::class]);
        expect(seedMessages('wp_cli_success'))->toBe(['1 seeder run.']);
    });

    it('runs one seeder named by its full class name', function () {
        seedCommand()([], ['class' => ArchivedPagesSeeder::class]);

        expect(seededClasses())->toBe([ArchivedPagesSeeder::class]);
    });

    it('refuses a short name that two seeders share', function () {
        seedCommand()([], ['class' => 'PagesSeeder']);

        expect(seededClasses())->toBe([]);
        expect(seedMessages('wp_cli_error')[0])
            ->toContain("More than one seeder is named 'PagesSeeder'")
            ->toContain(ArchivedPagesSeeder::class);
    });

    it('refuses an unknown seeder and lists the seeders', function () {
        seedCommand()([], ['class' => 'EventSeeder']);

        expect(seededClasses())->toBe([]);
        expect(wp_stub_get_calls('wp_cli_error')[0]['args']['exit'])->toBeTrue();
        expect(seedMessages('wp_cli_error')[0])
            ->toStartWith("Unknown seeder 'EventSeeder'. Available seeders: ")
            ->toContain(MediaSeeder::class);
    });

    it('lists the seeders in order and runs nothing on --dry-run', function () {
        seedCommand()([], ['dry-run' => true]);

        expect(seededClasses())->toBe([]);
        expect(wp_stub_get_calls('wp_cli_success'))->toBe([]);
        expect(seedMessages('wp_cli_log'))->toBe([
            'Would run, in order:',
            '  10  ' . MediaSeeder::class . ' — Photographs',
            '  20  ' . MenusSeeder::class,
            '  20  ' . PagesSeeder::class . ' — Pages',
            '  30  ' . ArchivedPagesSeeder::class . ' — Archived pages',
        ]);
    });

    it('lists only the named seeder on --dry-run', function () {
        seedCommand()([], ['dry-run' => true, 'class' => 'MediaSeeder']);

        expect(seedMessages('wp_cli_log'))->toBe([
            'Would run, in order:',
            '  10  ' . MediaSeeder::class . ' — Photographs',
        ]);
    });

    it('stops at the first seeder that throws, with an error that exits', function () {
        $GLOBALS['seeder_failure'] = 'the menu could not be written';

        seedCommand()([], []);

        expect(seededClasses())->toBe([MediaSeeder::class]);
        expect(wp_stub_get_calls('wp_cli_error')[0]['args']['exit'])->toBeTrue();
        expect(seedMessages('wp_cli_error'))->toBe([MenusSeeder::class . ' failed: the menu could not be written']);
        expect(wp_stub_get_calls('wp_cli_success'))->toBe([]);
    });

    it('says how to create a seeder when the project has none', function () {
        seedCommand(testAppPath())([], []);

        expect(seedMessages('wp_cli_log'))->toBe(['No seeders found. Create one with: wp foehn make:seeder <name>']);
        expect(wp_stub_get_calls('wp_cli_error'))->toBe([]);
    });
});
