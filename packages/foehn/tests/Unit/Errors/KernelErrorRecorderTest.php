<?php

declare(strict_types=1);

use Studiometa\Foehn\Errors\ErrorRecorder;
use Studiometa\Foehn\Errors\ErrorsCommand;
use Studiometa\Foehn\Kernel;
use Studiometa\Foehn\Trace\RequestTrace;

/**
 * When the kernel starts the error recorder, and when it leaves it alone.
 */
describe('Kernel: the error recorder', function () {
    afterEach(function () {
        // Put PHP's handlers back: the suite runs with failOnWarning.
        Kernel::get(ErrorRecorder::class)->stop();
        Kernel::reset();
        wp_stub_reset();
    });

    it('does not start it in production', function () {
        Kernel::boot(dirname(__DIR__, 3) . '/src', []);

        expect(Kernel::get(ErrorRecorder::class)->isStarted())->toBeFalse();
    });

    it('starts it in the local environment', function () {
        $GLOBALS['wp_stub_environment_type'] = 'local';

        Kernel::boot(dirname(__DIR__, 3) . '/src', []);

        expect(Kernel::get(ErrorRecorder::class)->isStarted())->toBeTrue();
    });

    it('starts it in production when the config asks for it', function () {
        Kernel::boot(dirname(__DIR__, 3) . '/src', ['record_errors' => true]);

        expect(Kernel::get(ErrorRecorder::class)->isStarted())->toBeTrue();
    });

    it('builds the command WP-CLI resolves, and shares one request context', function () {
        Kernel::boot(dirname(__DIR__, 3) . '/src', []);

        expect(Kernel::get(ErrorsCommand::class))->toBeInstanceOf(ErrorsCommand::class);
        expect(Kernel::get(RequestTrace::class))->toBe(Kernel::get(RequestTrace::class));
    });
});
