<?php

declare(strict_types=1);

use Studiometa\Foehn\Console\ClassFileGenerator;
use Studiometa\Foehn\Console\GenerationRequest;
use Studiometa\Foehn\Console\Scaffolder;
use Studiometa\Foehn\Console\Stubs\HooksStub;
use Studiometa\Foehn\Console\WpCli;

beforeEach(function () {
    wp_stub_reset();

    $this->root = sys_get_temp_dir() . '/foehn-scaffolder-' . bin2hex(random_bytes(6));
    $this->appPath = $this->root . '/app';

    mkdir($this->appPath, 0o755, true);
    file_put_contents($this->root . '/composer.json', json_encode(['autoload' => ['psr-4' => ['Theme\\' => 'app/']]]));

    $this->scaffolder = new Scaffolder(new WpCli(), new ClassFileGenerator($this->appPath));
    $this->path = $this->appPath . '/Hooks/ThemeHooks.php';

    $this->scaffold = function (array $assocArgs, array $notes = ['Add hooks.']): void {
        $this->scaffolder->scaffold(
            new GenerationRequest(
                stub: HooksStub::class,
                subdirectory: 'Hooks',
                className: 'ThemeHooks',
                replacements: ['DummyHooks' => 'ThemeHooks'],
            ),
            $assocArgs,
            'Hooks class created',
            $notes,
        );
    };

    $this->messages = static fn(string $call): string => implode("\n", array_column(
        array_column(wp_stub_get_calls($call), 'args'),
        'message',
    ));

    /** @return array{dryRun: bool, files: list<array{path: string, status: string, contents?: string}>, notes: list<string>} */
    $this->json = function (): array {
        $lines = wp_stub_get_calls('wp_cli_line');

        expect($lines)->toHaveCount(1);

        return json_decode($lines[0]['args']['message'], true, flags: JSON_THROW_ON_ERROR);
    };
});

afterEach(function () {
    exec('rm -rf ' . escapeshellarg($this->root));
});

describe('Scaffolder text output', function () {
    it('writes the file, then reports it and the next steps', function () {
        ($this->scaffold)([]);

        expect($this->path)->toBeFile();
        expect(($this->messages)('wp_cli_success'))->toContain('Hooks class created:');
        expect(($this->messages)('wp_cli_log'))->toBe('Add hooks.');
        expect(wp_stub_get_calls('wp_cli_error'))->toBeEmpty();
    });

    it('previews a dry run in full and writes nothing', function () {
        ($this->scaffold)(['dry-run' => true]);

        expect(file_exists($this->path))->toBeFalse();
        expect(wp_stub_get_calls('wp_cli_success'))->toBeEmpty();
        expect(($this->messages)('wp_cli_log'))->toContain('Would create:');
        expect(($this->messages)('wp_cli_line'))->toContain('class ThemeHooks');
        expect(($this->messages)('wp_cli_log'))->not->toContain('more lines');
    });

    it('says a forced dry run would overwrite the file', function () {
        ($this->scaffold)([]);
        file_put_contents($this->path, '<?php // hand-edited');
        wp_stub_reset();

        ($this->scaffold)(['dry-run' => true, 'force' => true]);

        expect(file_get_contents($this->path))->toBe('<?php // hand-edited');
        expect(($this->messages)('wp_cli_log'))->toContain('Would overwrite:');
    });

    it('truncates a long preview and says how much it hid', function () {
        $this->scaffolder->scaffold(
            new GenerationRequest(
                stub: HooksStub::class,
                subdirectory: 'Hooks',
                className: 'ThemeHooks',
                replacements: ['DummyHooks' => 'ThemeHooks' . str_repeat("\n", 60)],
            ),
            ['dry-run' => true],
            'Hooks class created',
        );

        expect(($this->messages)('wp_cli_log'))->toMatch('/\.\.\. \(\d+ more lines\)/');
    });
});

describe('Scaffolder JSON output', function () {
    it('reports the written file, its status and the next steps', function () {
        ($this->scaffold)(['format' => 'json']);

        expect(($this->json)())->toBe([
            'dryRun' => false,
            'files' => [['path' => $this->path, 'status' => 'created']],
            'notes' => ['Add hooks.'],
        ]);
        expect(wp_stub_get_calls('wp_cli_success'))->toBeEmpty();
        expect(wp_stub_get_calls('wp_cli_log'))->toBeEmpty();
    });

    it('reports an overwrite', function () {
        ($this->scaffold)([]);
        wp_stub_reset();

        ($this->scaffold)(['format' => 'json', 'force' => true]);

        expect(($this->json)()['files'][0]['status'])->toBe('overwritten');
    });

    it('carries the contents a dry run would write, and writes nothing', function () {
        ($this->scaffold)(['format' => 'json', 'dry-run' => true]);

        $report = ($this->json)();

        expect(file_exists($this->path))->toBeFalse();
        expect($report['dryRun'])->toBeTrue();
        expect($report['files'][0]['status'])->toBe('would-create');
        expect($report['files'][0]['contents'])->toContain('final class ThemeHooks');
    });

    it('reports a file it left alone, and fails', function () {
        ($this->scaffold)([]);
        file_put_contents($this->path, '<?php // hand-edited');
        wp_stub_reset();

        ($this->scaffold)(['format' => 'json']);

        expect(($this->json)()['files'][0]['status'])->toBe('exists');
        expect(file_get_contents($this->path))->toBe('<?php // hand-edited');

        $errors = wp_stub_get_calls('wp_cli_error');

        expect($errors)->toHaveCount(1);
        expect($errors[0]['args']['message'])->toContain('Use --force to overwrite.');
        expect($errors[0]['args']['exit'])->toBeTrue();
    });
});

describe('Scaffolder errors', function () {
    it('fails, and exits, when the file exists and --force is not given', function () {
        ($this->scaffold)([]);
        wp_stub_reset();

        ($this->scaffold)([]);

        $errors = wp_stub_get_calls('wp_cli_error');

        expect($errors)->toHaveCount(1);
        expect($errors[0]['args']['exit'])->toBeTrue();
        expect(wp_stub_get_calls('wp_cli_success'))->toBeEmpty();
    });

    it('fails a dry run that the real run would refuse', function () {
        ($this->scaffold)([]);
        wp_stub_reset();

        ($this->scaffold)(['dry-run' => true]);

        expect(wp_stub_get_calls('wp_cli_error'))->toHaveCount(1);
        expect(($this->messages)('wp_cli_log'))->not->toContain('Would create:');
    });

    it('rejects an unknown format before it generates anything', function (mixed $format, string $shown) {
        ($this->scaffold)(['format' => $format]);

        expect(file_exists($this->path))->toBeFalse();
        expect(($this->messages)('wp_cli_error'))->toBe("Unknown format '{$shown}'. Use text or json.");
    })->with([
        'a name it does not know' => ['yaml', 'yaml'],
        'the flag without a value' => [true, 'bool'],
    ]);
});
