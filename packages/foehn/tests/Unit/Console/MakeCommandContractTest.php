<?php

declare(strict_types=1);

use Studiometa\Foehn\Console\ClassFileGenerator;
use Studiometa\Foehn\Console\CliCommandInterface;
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
use Studiometa\Foehn\Console\Commands\MakeShortcodeCommand;
use Studiometa\Foehn\Console\Commands\MakeTaxonomyCommand;
use Studiometa\Foehn\Console\WpCli;

/**
 * Every make: command, and where a default invocation puts its file.
 *
 * The five behaviours below are the ones the whole family shares — generate,
 * preview, refuse, force, and reject a missing name. They are the same lines in
 * every command, so they are asserted once per command from one table rather
 * than written out fifteen times. Per-command semantics live in
 * MakeCommandsTest.
 *
 * @return array<string, array{class: class-string<CliCommandInterface>, args: list<string>, path: string}>
 */
function makeCommandContracts(): array
{
    return [
        'make:post-type' => [
            'class' => MakePostTypeCommand::class,
            'args' => ['gadget'],
            'path' => 'PostTypes/GadgetPost',
        ],
        'make:taxonomy' => [
            'class' => MakeTaxonomyCommand::class,
            'args' => ['flavour'],
            'path' => 'Taxonomies/FlavourTerm',
        ],
        'make:block' => ['class' => MakeBlockCommand::class, 'args' => ['banner'], 'path' => 'Blocks/BannerBlock'],
        'make:pattern' => [
            'class' => MakePatternCommand::class,
            'args' => ['promo'],
            'path' => 'Patterns/PromoPattern',
        ],
        'make:context' => ['class' => MakeContextCommand::class, 'args' => ['sidebar'], 'path' => 'Context/Sidebar'],
        'make:context-provider' => [
            'class' => MakeContextProviderCommand::class,
            'args' => ['header'],
            'path' => 'ContextProviders/HeaderContextProvider',
        ],
        'make:controller' => [
            'class' => MakeControllerCommand::class,
            'args' => ['listing'],
            'path' => 'Controllers/ListingController',
        ],
        'make:hooks' => ['class' => MakeHooksCommand::class, 'args' => ['Cleanup'], 'path' => 'Hooks/CleanupHooks'],
        'make:image-size' => [
            'class' => MakeImageSizeCommand::class,
            'args' => ['CardThumb'],
            'path' => 'ImageSizes/CardThumb',
        ],
        'make:menu' => ['class' => MakeMenuCommand::class, 'args' => ['SocialMenu'], 'path' => 'Menus/SocialMenu'],
        'make:model' => ['class' => MakeModelCommand::class, 'args' => ['Gizmo'], 'path' => 'Models/Gizmo'],
        'make:shortcode' => [
            'class' => MakeShortcodeCommand::class,
            'args' => ['embed'],
            'path' => 'Shortcodes/EmbedShortcode',
        ],
    ];
}

beforeEach(function () {
    wp_stub_reset();

    $this->root = sys_get_temp_dir() . '/foehn-contract-' . bin2hex(random_bytes(6));
    $this->appPath = $this->root . '/app';

    mkdir($this->appPath, 0o755, true);
    file_put_contents($this->root . '/composer.json', json_encode(['autoload' => ['psr-4' => ['Theme\\' => 'app/']]]));

    $this->generator = new ClassFileGenerator($this->appPath);
    $this->make = fn(string $class): CliCommandInterface => new $class(new WpCli(), $this->generator);
});

afterEach(function () {
    exec('rm -rf ' . escapeshellarg($this->root));
});

describe('every make: command', function () {
    makeCommandContractSuite(makeCommandContracts());

    it('covers every make: command Foehn ships', function () {
        $shipped = array_map(
            static fn(string $file): string => 'Studiometa\\Foehn\\Console\\Commands\\' . basename($file, '.php'),
            glob(dirname(__DIR__, 3) . '/src/Console/Commands/Make*.php') ?: [],
        );

        expect(array_column(makeCommandContracts(), 'class'))->toEqualCanonicalizing($shipped);
    });
});
