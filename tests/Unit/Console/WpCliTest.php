<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use Studiometa\Foehn\Console\WpCli;

describe('WpCli', function (): void {
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
