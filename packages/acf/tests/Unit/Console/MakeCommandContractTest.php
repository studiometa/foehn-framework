<?php

declare(strict_types=1);

use Studiometa\Foehn\Console\ClassFileGenerator;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\Commands\MakeAcfBlockCommand;
use Studiometa\Foehn\Console\Commands\MakeFieldGroupCommand;
use Studiometa\Foehn\Console\Commands\MakeOptionsPageCommand;
use Studiometa\Foehn\Console\WpCli;

/**
 * The three make: commands this package ships, and where a default invocation
 * puts each file.
 *
 * The behaviours are the family's, asserted through the framework's shared
 * contract suite rather than a copy of it. Per-command semantics live in
 * MakeCommandsTest.
 *
 * @return array<string, array{class: class-string<CliCommandInterface>, args: list<string>, path: string}>
 */
function acfMakeCommandContracts(): array
{
    return [
        'make:acf-block' => ['class' => MakeAcfBlockCommand::class, 'args' => ['quote'], 'path' => 'Blocks/QuoteBlock'],
        'make:field-group' => [
            'class' => MakeFieldGroupCommand::class,
            'args' => ['MetaFields'],
            'path' => 'Fields/MetaFields',
        ],
        'make:options-page' => [
            'class' => MakeOptionsPageCommand::class,
            'args' => ['site-options'],
            'path' => 'Fields/Options/SiteOptions',
        ],
    ];
}

beforeEach(function () {
    wp_stub_reset();

    $this->root = sys_get_temp_dir() . '/foehn-acf-contract-' . bin2hex(random_bytes(6));
    $this->appPath = $this->root . '/app';

    mkdir($this->appPath, 0o755, true);
    file_put_contents($this->root . '/composer.json', json_encode(['autoload' => ['psr-4' => ['Theme\\' => 'app/']]]));

    $this->generator = new ClassFileGenerator($this->appPath);
    $this->make = fn(string $class): CliCommandInterface => new $class(new WpCli(), $this->generator);
});

afterEach(function () {
    exec('rm -rf ' . escapeshellarg($this->root));
});

describe('every ACF make: command', function () {
    makeCommandContractSuite(acfMakeCommandContracts());

    it('covers every make: command this package ships', function () {
        $shipped = array_map(
            static fn(string $file): string => 'Studiometa\\Foehn\\Console\\Commands\\' . basename($file, '.php'),
            glob(dirname(__DIR__, 3) . '/src/Console/Commands/Make*.php') ?: [],
        );

        expect(array_column(acfMakeCommandContracts(), 'class'))->toEqualCanonicalizing($shipped);
    });
});
