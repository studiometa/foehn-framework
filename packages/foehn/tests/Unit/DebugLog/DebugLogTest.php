<?php

declare(strict_types=1);

use Studiometa\Foehn\DebugLog\DebugLog;

describe('DebugLog', function () {
    afterEach(function () {
        if (isset($this->file) && is_file($this->file)) {
            unlink($this->file);
        }
    });

    it('finds the file the way wp_debug_mode() does', function (
        mixed $debugLog,
        mixed $debug,
        ?string $path,
        bool $writing,
    ) {
        $log = DebugLog::fromSettings($debugLog, $debug, '/srv/site/wp-content');

        expect($log->path)->toBe($path);
        expect($log->writing)->toBe($writing);
    })->with([
        'true' => [true, true, '/srv/site/wp-content/debug.log', true],
        "'1'" => ['1', true, '/srv/site/wp-content/debug.log', true],
        'a path' => ['/srv/logs/wp.log', true, '/srv/logs/wp.log', true],
        'WP_DEBUG off' => [true, false, '/srv/site/wp-content/debug.log', false],
        'false' => [false, true, null, false],
        'empty' => ['', true, null, false],
    ]);

    it('is off in the test stubs, which define both constants as false', function () {
        expect(DebugLog::fromWordPress()->path)->toBeNull();
    });

    it('reads nothing when the file does not exist', function () {
        expect(new DebugLog('/no/such/debug.log', true)->read())->toBeNull();
        expect(new DebugLog(null, false)->read())->toBeNull();
    });

    it('reads a small file whole', function () {
        $this->file = tempnam(sys_get_temp_dir(), 'foehn-debug-log-');
        file_put_contents($this->file, "[12-Nov-2026 10:00:00 UTC] Hello\n");

        expect(new DebugLog($this->file, true)->read())->toBe([
            'text' => "[12-Nov-2026 10:00:00 UTC] Hello\n",
            'size' => 33,
        ]);
    });

    it('reads only the end of a large file, from its first whole line', function () {
        $this->file = tempnam(sys_get_temp_dir(), 'foehn-debug-log-');
        $line = str_repeat('x', 99) . "\n";
        file_put_contents($this->file, str_repeat($line, (int) (DebugLog::MAX_BYTES / 100) + 50) . "last\n");

        $read = new DebugLog($this->file, true)->read();

        expect($read['size'])->toBe((int) filesize($this->file));
        expect(strlen($read['text']))->toBeLessThan(DebugLog::MAX_BYTES);
        expect($read['text'])->toStartWith($line);
        expect($read['text'])->toEndWith("last\n");
    });
});
