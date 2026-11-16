<?php

declare(strict_types=1);

use Studiometa\Foehn\Kernel;

use function Studiometa\Foehn\app;

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
});
