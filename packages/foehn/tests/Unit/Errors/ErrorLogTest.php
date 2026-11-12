<?php

declare(strict_types=1);

use Studiometa\Foehn\Errors\ErrorLog;

describe('ErrorLog', function () {
    beforeEach(function () {
        $this->directory = errorLogDirectory();
        $this->log = new ErrorLog($this->directory);
    });

    afterEach(fn() => removeErrorLogDirectory($this->directory));

    it('has no entries before the first error', function () {
        expect($this->log->entries())->toBe([]);
        expect(is_dir($this->directory))->toBeFalse();
    });

    it('writes one JSON object per line and reads them back, the newest first', function () {
        $this->log->append(['message' => 'first']);
        $this->log->append(['message' => 'second']);

        expect(file($this->directory . '/errors.jsonl', FILE_IGNORE_NEW_LINES))->toBe([
            '{"message":"first"}',
            '{"message":"second"}',
        ]);
        expect($this->log->entries())->toBe([['message' => 'second'], ['message' => 'first']]);
    });

    it('skips a line that is not a JSON object', function () {
        $this->log->append(['message' => 'kept']);
        file_put_contents($this->directory . '/errors.jsonl', "{\"message\":\"cut\n", FILE_APPEND);

        expect($this->log->entries())->toBe([['message' => 'kept']]);
    });

    it('rotates the file at its cap and keeps at most three files', function () {
        $message = str_repeat('x', 100 * 1024);

        for ($index = 1; $index <= 10; $index++) {
            $this->log->append(['index' => $index, 'message' => $message]);
        }

        $files = array_map(basename(...), glob($this->directory . '/*') ?: []);
        sort($files);

        expect($files)->toBe(['errors.1.jsonl', 'errors.2.jsonl', 'errors.jsonl']);

        foreach ($files as $file) {
            expect(filesize($this->directory . '/' . $file))->toBeLessThanOrEqual(ErrorLog::MAX_BYTES);
        }

        $indexes = array_column($this->log->entries(), 'index');

        expect($indexes[0])->toBe(10);
        expect($indexes)->toBe(range(10, 10 - count($indexes) + 1));
        expect(count($indexes))->toBeGreaterThanOrEqual(4);
    });

    it('deletes every file when cleared', function () {
        $this->log->append(['message' => 'gone']);

        expect($this->log->clear())->toBe(1);
        expect($this->log->entries())->toBe([]);
        expect($this->log->clear())->toBe(0);
    });

    it('lives in wp-content/cache/foehn/errors', function () {
        $expected = defined('WP_CONTENT_DIR')
            ? constant('WP_CONTENT_DIR') . '/cache/foehn/errors'
            : sys_get_temp_dir() . '/foehn/errors';

        expect(ErrorLog::inContentDirectory()->directory())->toBe($expected);
    });

    it('reads the browser log in the dist directory of the theme', function () {
        $GLOBALS['wp_stub_template_directory'] = '/srv/site/wp-content/themes/theme/';

        $log = ErrorLog::browserInTheme();

        expect($log->directory())->toBe('/srv/site/wp-content/themes/theme/dist');
        expect($log->file())->toBe('/srv/site/wp-content/themes/theme/dist/browser-errors.jsonl');

        unset($GLOBALS['wp_stub_template_directory']);
    });
});
