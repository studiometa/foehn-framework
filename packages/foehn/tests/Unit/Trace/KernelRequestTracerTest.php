<?php

declare(strict_types=1);

use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Studiometa\Foehn\Errors\ErrorRecorder;
use Studiometa\Foehn\Kernel;
use Studiometa\Foehn\Trace\RequestTrace;
use Studiometa\Foehn\Trace\RequestTracer;
use Studiometa\Foehn\Views\TimberViewEngine;

/**
 * When the kernel starts the request tracer, and what it shares with the rest.
 */
describe('Kernel: the request tracer', function () {
    afterEach(function () {
        // Put PHP's handlers back: the suite runs with failOnWarning.
        Kernel::get(ErrorRecorder::class)->stop();
        Kernel::reset();
        wp_stub_reset();
        // wp_stub_reset() keeps the environment: a later test would boot in `local`.
        unset($GLOBALS['wp_stub_environment_type']);
    });

    it('does not start it in production', function () {
        Kernel::boot(dirname(__DIR__, 3) . '/src', []);

        expect(Kernel::get(RequestTracer::class)->isStarted())->toBeFalse();
    });

    it('starts it in the local environment', function () {
        $GLOBALS['wp_stub_environment_type'] = 'local';

        Kernel::boot(dirname(__DIR__, 3) . '/src', []);

        expect(Kernel::get(RequestTracer::class)->isStarted())->toBeTrue();
    });

    it('starts it in production when the config asks for it', function () {
        Kernel::boot(dirname(__DIR__, 3) . '/src', ['trace_requests' => true]);

        expect(Kernel::get(RequestTracer::class)->isStarted())->toBeTrue();
    });

    it('gives the view engine the request trace the tracer stores', function () {
        Kernel::boot(dirname(__DIR__, 3) . '/src', []);

        $engine = Kernel::get(ViewEngineInterface::class);

        expect($engine)->toBeInstanceOf(TimberViewEngine::class);
        expect(new ReflectionProperty($engine, 'trace')->getValue($engine))->toBe(Kernel::get(RequestTrace::class));
    });
});
