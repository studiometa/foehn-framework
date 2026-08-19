<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use Studiometa\Foehn\Console\GeneratedFile;
use Studiometa\Foehn\Console\WpCli;

describe('WpCli', function (): void {
    it('previews a short generated file in full', function (): void {
        wp_stub_reset();

        new WpCli()->previewGeneratedFile(new GeneratedFile('/app/Thing.php', "one\ntwo\nthree"));

        $logged = implode("\n", array_column(array_column(wp_stub_get_calls('wp_cli_log'), 'args'), 'message'));
        $lines = implode("\n", array_column(array_column(wp_stub_get_calls('wp_cli_line'), 'args'), 'message'));

        expect($logged)->toContain('Would create:');
        expect($lines)->toContain('one');
        expect($lines)->toContain('three');
        expect($logged)->not->toContain('more lines');
    });

    it('truncates a long generated file and says how much it hid', function (): void {
        wp_stub_reset();

        $contents = implode("\n", array_map(static fn(int $i): string => "line {$i}", range(1, 60)));

        new WpCli()->previewGeneratedFile(new GeneratedFile('/app/Thing.php', $contents), maxLines: 50);

        $logged = implode("\n", array_column(array_column(wp_stub_get_calls('wp_cli_log'), 'args'), 'message'));
        $lines = implode("\n", array_column(array_column(wp_stub_get_calls('wp_cli_line'), 'args'), 'message'));

        expect($lines)->toContain('line 50');
        expect($lines)->not->toContain('line 51');
        expect($logged)->toContain('... (10 more lines)');
    });

    it('reports an existing file as an error that does not exit', function (): void {
        wp_stub_reset();

        new WpCli()->reportFileExists(new GeneratedFile('/app/Thing.php', ''));

        $errors = wp_stub_get_calls('wp_cli_error');

        expect($errors)->toHaveCount(1);
        expect($errors[0]['args']['message'])->toContain('Use --force to overwrite.');
        expect($errors[0]['args']['exit'])->toBeFalse();
    });

    it('reports unavailable when WP_CLI is not defined', function (): void {
        expect(WpCli::isAvailable())->toBeFalse();
    });

    it('can get relative path from absolute path', function (): void {
        $cli = new WpCli();

        // When STYLESHEETPATH is not defined, it uses getcwd()
        $cwd = getcwd();
        $relativePath = $cli->getRelativePath($cwd . '/app/PostTypes/TestPost.php');

        expect($relativePath)->toBe('app/PostTypes/TestPost.php');
    });

    it('returns absolute path when not under root', function (): void {
        $cli = new WpCli();

        $absolutePath = '/some/other/path/file.php';
        $result = $cli->getRelativePath($absolutePath);

        expect($result)->toBe($absolutePath);
    });
});
