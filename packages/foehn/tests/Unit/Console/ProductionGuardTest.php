<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\Commands\DiscoveryClearCommand;
use Studiometa\Foehn\Console\Commands\DiscoveryGenerateCommand;
use Studiometa\Foehn\Console\Commands\DiscoveryListCommand;
use Studiometa\Foehn\Console\Commands\DiscoveryStatusCommand;
use Studiometa\Foehn\Console\Commands\MakeBlockCommand;
use Studiometa\Foehn\Console\Commands\MakeContextCommand;
use Studiometa\Foehn\Console\Commands\MakeContextProviderCommand;
use Studiometa\Foehn\Console\Commands\MakeControllerCommand;
use Studiometa\Foehn\Console\Commands\MakeHooksCommand;
use Studiometa\Foehn\Console\Commands\MakeImageSizeCommand;
use Studiometa\Foehn\Console\Commands\MakeMenuCommand;
use Studiometa\Foehn\Console\Commands\MakeModelCommand;
use Studiometa\Foehn\Console\Commands\MakePatternCommand;
use Studiometa\Foehn\Console\Commands\MakePostTypeCommand;
use Studiometa\Foehn\Console\Commands\MakeSeederCommand;
use Studiometa\Foehn\Console\Commands\MakeShortcodeCommand;
use Studiometa\Foehn\Console\Commands\MakeTaxonomyCommand;
use Studiometa\Foehn\Console\Commands\PageCacheClearCommand;
use Studiometa\Foehn\Console\Commands\PageCacheConfigCommand;
use Studiometa\Foehn\Console\Commands\PageCacheStatusCommand;
use Studiometa\Foehn\Console\Commands\PageCacheWarmCommand;
use Studiometa\Foehn\Console\Commands\RewriteFlushCommand;
use Studiometa\Foehn\Console\Commands\SaltsGenerateCommand;
use Studiometa\Foehn\Console\ProductionGuard;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\DatabaseExport\DbExportCommand;
use Studiometa\Foehn\DebugLog\LogCommand;
use Studiometa\Foehn\Errors\ErrorsCommand;
use Studiometa\Foehn\Inspect\InspectCommand;
use Studiometa\Foehn\Seeders\SeedCommand;
use Studiometa\Foehn\Skills\SkillCommand;
use Studiometa\Foehn\Trace\TraceCommand;
use Studiometa\Foehn\Verification\VerifyCommand;

/**
 * Every Føhn command, and whether it changes content or files.
 *
 * A new command has to be added here, so whoever adds it decides whether production
 * stops it. The cache and discovery commands are deploy steps and stay unguarded;
 * db:export reads the database and writes only the dump it was asked for.
 *
 * @return array<class-string<CliCommandInterface>, bool>
 */
function foehnCommandsWrite(): array
{
    return [
        SeedCommand::class => true,
        MakeBlockCommand::class => true,
        MakeContextCommand::class => true,
        MakeContextProviderCommand::class => true,
        MakeControllerCommand::class => true,
        MakeHooksCommand::class => true,
        MakeImageSizeCommand::class => true,
        MakeMenuCommand::class => true,
        MakeModelCommand::class => true,
        MakePatternCommand::class => true,
        MakePostTypeCommand::class => true,
        MakeSeederCommand::class => true,
        MakeShortcodeCommand::class => true,
        MakeTaxonomyCommand::class => true,
        SaltsGenerateCommand::class => true,
        DbExportCommand::class => false,
        DiscoveryClearCommand::class => false,
        DiscoveryGenerateCommand::class => false,
        DiscoveryListCommand::class => false,
        DiscoveryStatusCommand::class => false,
        PageCacheClearCommand::class => false,
        PageCacheConfigCommand::class => false,
        PageCacheStatusCommand::class => false,
        PageCacheWarmCommand::class => false,
        RewriteFlushCommand::class => false,
        ErrorsCommand::class => false,
        LogCommand::class => false,
        TraceCommand::class => false,
        InspectCommand::class => false,
        SkillCommand::class => false,
        VerifyCommand::class => false,
    ];
}

function commandAttribute(string $class): AsCliCommand
{
    return new ReflectionClass($class)->getAttributes(AsCliCommand::class)[0]->newInstance();
}

function guardRefusal(): ?string
{
    $errors = wp_stub_get_calls('wp_cli_error');

    return $errors === [] ? null : $errors[0]['args']['message'];
}

beforeEach(function (): void {
    wp_stub_reset();
    $GLOBALS['wp_stub_environment_type'] = 'production';
    $this->guard = new ProductionGuard(new WpCli());
});

afterEach(function (): void {
    unset($GLOBALS['wp_stub_environment_type']);
});

describe('ProductionGuard', function (): void {
    it('lists every command Føhn ships', function (): void {
        $shipped = [];

        foreach (glob(dirname(__DIR__, 3) . '/src/{Console/Commands,*}/*Command.php', GLOB_BRACE) ?: [] as $file) {
            if (preg_match('/^namespace (.+);$/m', (string) file_get_contents($file), $match) === 1) {
                $shipped[] = $match[1] . '\\' . basename($file, '.php');
            }
        }

        $shipped = array_values(array_filter(
            $shipped,
            static fn(string $class): bool => new ReflectionClass($class)->getAttributes(AsCliCommand::class) !== [],
        ));

        sort($shipped);
        $listed = array_keys(foehnCommandsWrite());
        sort($listed);

        expect($listed)->toBe($shipped);
    });

    foreach (foehnCommandsWrite() as $class => $writes) {
        $name = commandAttribute($class)->name;

        if (!$writes) {
            it("lets {$name} run in production", function () use ($class): void {
                expect(commandAttribute($class)->writes)->toBeFalse();
                expect($this->guard->allows(commandAttribute($class), []))->toBeTrue();
                expect(guardRefusal())->toBeNull();
            });

            continue;
        }

        it("stops {$name} in production and says why and how", function () use ($class, $name): void {
            expect($this->guard->allows(commandAttribute($class), []))->toBeFalse();
            expect(guardRefusal())
                ->toBe(
                    "`wp foehn {$name}` changes content or files, and wp_get_environment_type() is 'production', "
                    . 'so it did not run. Run it on a local or staging copy, add --dry-run to see what it would do, '
                    . 'or add --force to run it here.',
                );
        });

        it("runs {$name} in production with --force or --dry-run", function () use ($class): void {
            // WP-CLI rejects an option the long description does not declare, so
            // the way out the refusal names has to be one the command takes.
            expect(commandAttribute($class)->longDescription)->toContain('[--force]')->toContain('[--dry-run]');
            expect($this->guard->allows(commandAttribute($class), ['force' => true]))->toBeTrue();
            expect($this->guard->allows(commandAttribute($class), ['dry-run' => true]))->toBeTrue();
            expect(guardRefusal())->toBeNull();
        });

        it("runs {$name} outside production", function () use ($class): void {
            foreach (['local', 'development', 'staging'] as $environment) {
                $GLOBALS['wp_stub_environment_type'] = $environment;

                expect($this->guard->allows(commandAttribute($class), []))->toBeTrue();
            }

            expect(guardRefusal())->toBeNull();
        });
    }

    it('names only --force for a command that takes no --dry-run', function (): void {
        $command = new AsCliCommand(
            name: 'import',
            description: 'Import',
            longDescription: "## OPTIONS\n\n[--force]\n: Run in production",
            writes: true,
        );

        expect($this->guard->allows($command, []))->toBeFalse();
        expect(guardRefusal())
            ->toEndWith('so it did not run. Run it on a local or staging copy, or add --force to run it here.');
    });
});
