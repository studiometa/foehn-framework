<?php

declare(strict_types=1);

use Studiometa\Foehn\Kernel;

use function Studiometa\Foehn\app;
use function Studiometa\Foehn\config;

describe('helpers', function () {
    afterEach(function () {
        Kernel::reset();
    });

    describe('app()', function () {
        it('throws exception when kernel not booted', function () {
            expect(fn() => app())->toThrow(RuntimeException::class, 'Kernel not booted. Call Kernel::boot() first.');
        });

        it('throws exception when getting class before boot', function () {
            expect(fn() => app(stdClass::class))
                ->toThrow(RuntimeException::class, 'Kernel not booted. Call Kernel::boot() first.');
        });
    });

    describe('config()', function () {
        it('throws exception when kernel not booted', function () {
            expect(fn() => config('key'))
                ->toThrow(RuntimeException::class, 'Kernel not booted. Call Kernel::boot() first.');
        });
    });
});
