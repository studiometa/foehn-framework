<?php

declare(strict_types=1);

use Studiometa\Foehn\Console\ClassFileGenerator;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\Commands\MakeAcfBlockCommand;
use Studiometa\Foehn\Console\Commands\MakeBlockCommand;
use Studiometa\Foehn\Console\Commands\MakeContextCommand;
use Studiometa\Foehn\Console\Commands\MakeContextProviderCommand;
use Studiometa\Foehn\Console\Commands\MakeControllerCommand;
use Studiometa\Foehn\Console\Commands\MakeFieldGroupCommand;
use Studiometa\Foehn\Console\Commands\MakeHooksCommand;
use Studiometa\Foehn\Console\Commands\MakeImageSizeCommand;
use Studiometa\Foehn\Console\Commands\MakeMenuCommand;
use Studiometa\Foehn\Console\Commands\MakeModelCommand;
use Studiometa\Foehn\Console\Commands\MakeOptionsPageCommand;
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
        'make:acf-block' => ['class' => MakeAcfBlockCommand::class, 'args' => ['quote'], 'path' => 'Blocks/QuoteBlock'],
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
        'make:field-group' => [
            'class' => MakeFieldGroupCommand::class,
            'args' => ['MetaFields'],
            'path' => 'Fields/MetaFields',
        ],
        'make:hooks' => ['class' => MakeHooksCommand::class, 'args' => ['Cleanup'], 'path' => 'Hooks/CleanupHooks'],
        'make:image-size' => [
            'class' => MakeImageSizeCommand::class,
            'args' => ['CardThumb'],
            'path' => 'ImageSizes/CardThumb',
        ],
        'make:menu' => ['class' => MakeMenuCommand::class, 'args' => ['SocialMenu'], 'path' => 'Menus/SocialMenu'],
        'make:model' => ['class' => MakeModelCommand::class, 'args' => ['Gizmo'], 'path' => 'Models/Gizmo'],
        'make:options-page' => [
            'class' => MakeOptionsPageCommand::class,
            'args' => ['site-options'],
            'path' => 'Fields/Options/SiteOptions',
        ],
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
    foreach (makeCommandContracts() as $label => $case) {
        $class = $case['class'];
        $args = $case['args'];
        $relative = $case['path'] . '.php';

        it("{$label} generates its file", function () use ($class, $args, $relative) {
            ($this->make)($class)($args, []);

            $path = "{$this->appPath}/{$relative}";

            expect($path)->toBeFile();
            expect(file_get_contents($path))->toContain('declare(strict_types=1);');
            expect(wp_stub_get_calls('wp_cli_success'))->toHaveCount(1);
            expect(wp_stub_get_calls('wp_cli_error'))->toBeEmpty();
        });

        it("{$label} generates valid PHP", function () use ($class, $args, $relative) {
            ($this->make)($class)($args, []);

            exec('php -l ' . escapeshellarg("{$this->appPath}/{$relative}") . ' 2>&1', $output, $status);

            expect($status)->toBe(0, implode("\n", $output));
        });

        it("{$label} leaves no placeholder behind", function () use ($class, $args, $relative) {
            ($this->make)($class)($args, []);

            $contents = (string) file_get_contents("{$this->appPath}/{$relative}");

            expect($contents)->not->toContain('dummy');
            expect($contents)->not->toContain('Dummy');
        });

        it("{$label} writes nothing on --dry-run but previews the file", function () use ($class, $args, $relative) {
            ($this->make)($class)($args, ['dry-run' => true]);

            expect(file_exists("{$this->appPath}/{$relative}"))->toBeFalse();
            expect(wp_stub_get_calls('wp_cli_success'))->toBeEmpty();

            $logged = implode("\n", array_column(array_column(wp_stub_get_calls('wp_cli_log'), 'args'), 'message'));

            expect($logged)->toContain('Would create:');
        });

        it("{$label} refuses to overwrite without --force", function () use ($class, $args, $relative) {
            $path = "{$this->appPath}/{$relative}";

            ($this->make)($class)($args, []);
            file_put_contents($path, '<?php // hand-edited');
            wp_stub_reset();

            ($this->make)($class)($args, []);

            expect(file_get_contents($path))->toBe('<?php // hand-edited');
            expect(wp_stub_get_calls('wp_cli_error'))->toHaveCount(1);
            expect(wp_stub_get_calls('wp_cli_success'))->toBeEmpty();
        });

        it("{$label} overwrites with --force", function () use ($class, $args, $relative) {
            $path = "{$this->appPath}/{$relative}";

            ($this->make)($class)($args, []);
            file_put_contents($path, '<?php // hand-edited');

            ($this->make)($class)($args, ['force' => true]);

            expect(file_get_contents($path))->toContain('declare(strict_types=1);');
        });

        it("{$label} reports a missing name instead of generating", function () use ($class, $case) {
            ($this->make)($class)([], []);

            expect(wp_stub_get_calls('wp_cli_error'))->toHaveCount(1);

            $directory = dirname("{$this->appPath}/{$case['path']}");

            expect(is_dir($directory) ? (glob($directory . '/*.php') ?: []) : [])->toBeEmpty();
        });
    }

    it('covers every make: command Foehn ships', function () {
        $shipped = array_map(
            static fn(string $file): string => 'Studiometa\\Foehn\\Console\\Commands\\' . basename($file, '.php'),
            glob(dirname(__DIR__, 3) . '/src/Console/Commands/Make*.php') ?: [],
        );

        expect(array_column(makeCommandContracts(), 'class'))->toEqualCanonicalizing($shipped);
    });
});
