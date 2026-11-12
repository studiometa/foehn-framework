<?php

declare(strict_types=1);

use Studiometa\Foehn\Errors\EntryBuilder;
use Studiometa\Foehn\Errors\ErrorLog;
use Studiometa\Foehn\Errors\ErrorRecorder;
use Studiometa\Foehn\Errors\RequestContext;
use Studiometa\Foehn\Errors\SourcePaths;

/**
 * A recorder whose own code is the test suite, writing to a temporary log.
 */
function testErrorRecorder(string $directory, string $root = ''): ErrorRecorder
{
    $paths = new SourcePaths($root === '' ? dirname(__DIR__, 2) : $root);
    $request = new RequestContext();

    return new ErrorRecorder(new ErrorLog($directory), new EntryBuilder($paths, $request), $paths, $request);
}

describe('ErrorRecorder', function () {
    beforeEach(function () {
        wp_stub_reset();
        $this->directory = errorLogDirectory();
        $this->log = new ErrorLog($this->directory);
        $this->recorder = testErrorRecorder($this->directory);
        $this->handled = [];
        // The suite runs with a narrow mask, like WordPress with WP_DEBUG off. The
        // recorder respects the mask, so these tests widen it.
        $this->errorReporting = error_reporting(E_ALL);
    });

    afterEach(function () {
        error_reporting($this->errorReporting);
        $this->recorder->stop();
        removeErrorLogDirectory($this->directory);
    });

    it('records a warning of the theme, and gives it to the handler that was there before', function () {
        // The handler before it answers true, so PHPUnit never sees the warning.
        set_error_handler(function (int $type, string $message): bool {
            $this->handled[] = $message;

            return true;
        });

        try {
            $this->recorder->start();
            trigger_error('A warning of the theme', E_USER_WARNING);
            $this->recorder->stop();
        } finally {
            restore_error_handler();
        }

        $entries = $this->log->entries();

        expect($this->handled)->toBe(['A warning of the theme']);
        expect($entries)->toHaveCount(1);
        expect($entries[0]['level'])->toBe('warning');
        expect($entries[0]['class'])->toBe('E_USER_WARNING');
        expect($entries[0]['message'])->toBe('A warning of the theme');
        expect($entries[0]['file'])->toBe('tests/Unit/Errors/ErrorRecorderTest.php');
    });

    it('does not record a warning outside the theme', function () {
        $recorder = testErrorRecorder($this->directory, '/srv/other-theme');

        expect($recorder->handleError(E_WARNING, 'Elsewhere', __FILE__, __LINE__))->toBeFalse();
        expect($this->log->entries())->toBe([]);
    });

    it('does not record a warning silenced with @, nor a deprecation', function () {
        $previous = error_reporting(E_ERROR);

        try {
            $this->recorder->handleError(E_WARNING, 'Silenced', __FILE__, __LINE__);
        } finally {
            error_reporting($previous);
        }

        $this->recorder->handleError(E_DEPRECATED, 'Deprecated', __FILE__, __LINE__);

        expect($this->log->entries())->toBe([]);
    });

    it('records a notice once per request, and at most a fixed number of errors', function () {
        $this->recorder->handleError(E_NOTICE, 'Again', __FILE__, 10);
        $this->recorder->handleError(E_NOTICE, 'Again', __FILE__, 10);

        expect($this->log->entries())->toHaveCount(1);
        expect($this->log->entries()[0]['level'])->toBe('notice');

        for ($line = 0; $line < 30; $line++) {
            $this->recorder->handleError(E_WARNING, 'Many', __FILE__, $line);
        }

        expect($this->log->entries())->toHaveCount(ErrorRecorder::MAX_PER_REQUEST);
    });

    it('records an uncaught exception, then gives it to the handler that was there before', function () {
        set_exception_handler(function (Throwable $throwable): void {
            $this->handled[] = $throwable->getMessage();
        });

        try {
            $this->recorder->start();
            $this->recorder->handleException(new LogicException('Uncaught here'));
        } finally {
            $this->recorder->stop();
            restore_exception_handler();
        }

        expect($this->handled)->toBe(['Uncaught here']);
        expect($this->log->entries()[0]['class'])->toBe(LogicException::class);
        expect($this->log->entries()[0]['level'])->toBe('exception');
    });

    it('throws the exception again when no handler was there before, so PHP ends the request', function () {
        $this->recorder->start();
        $exception = new RuntimeException('No handler');

        expect(fn() => $this->recorder->handleException($exception))->toThrow($exception);
        expect($this->log->entries())->toHaveCount(1);
    });

    it('records the fatal error that ended the request, without the stack trace in its message', function () {
        $this->recorder->start();
        $this->recorder->handleShutdown([
            'type' => E_ERROR,
            'message' => "Allowed memory size exhausted\nStack trace:\n#0 {main}",
            'file' => '/srv/site/web/wp/wp-includes/plugin.php',
            'line' => 20,
        ]);

        $entry = $this->log->entries()[0];

        expect($entry['level'])->toBe('fatal');
        expect($entry['class'])->toBe('E_ERROR');
        expect($entry['message'])->toBe('Allowed memory size exhausted');
        expect($entry['file'])->toBe('/srv/site/web/wp/wp-includes/plugin.php');
    });

    it('records nothing at shutdown for a warning, or for an exception it recorded already', function () {
        set_exception_handler(static fn() => null);

        try {
            $this->recorder->start();
            $this->recorder->handleShutdown(['type' => E_WARNING, 'message' => 'Not fatal', 'file' => '', 'line' => 0]);
            $this->recorder->handleException(new RuntimeException('Once'));
            $this->recorder->handleShutdown([
                'type' => E_ERROR,
                'message' => 'Uncaught RuntimeException: Once',
                'file' => __FILE__,
                'line' => 1,
            ]);
        } finally {
            $this->recorder->stop();
            restore_exception_handler();
        }

        expect(array_column($this->log->entries(), 'message'))->toBe(['Once']);
    });

    it('records nothing at shutdown once stopped', function () {
        $this->recorder->handleShutdown(['type' => E_ERROR, 'message' => 'After stop', 'file' => '', 'line' => 0]);

        expect($this->log->entries())->toBe([]);
    });

    it('remembers the view Timber renders and its context keys, and leaves the data unchanged', function () {
        expect($this->recorder->onRenderData(['post' => 1, 'site' => 2], 'pages/page.twig'))->toBe([
            'post' => 1,
            'site' => 2,
        ]);

        $this->recorder->handleError(E_WARNING, 'In a view', __FILE__, __LINE__);
        $entry = $this->log->entries()[0];

        expect($entry['view'])->toBe('pages/page.twig');
        expect($entry['context'])->toBe(['post', 'site']);
    });

    it('starts once, and listens to the views Timber renders', function () {
        $this->recorder->start();
        $this->recorder->start();

        expect($this->recorder->isStarted())->toBeTrue();
        expect(wp_stub_get_calls('add_filter'))->toHaveCount(1);
        expect(wp_stub_get_calls('add_filter')[0]['args']['hook'])->toBe('timber/loader/render_data');
    });
});
