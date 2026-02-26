<?php

declare(strict_types=1);

use Studiometa\Foehn\Contracts\JobDispatcher;
use Studiometa\Foehn\Kernel;
use Tests\Fixtures\JobDtoFixture;

use function Studiometa\Foehn\dispatch;

afterEach(function () {
    Kernel::reset();
    wp_stub_reset();
});

describe('dispatch() helper', function () {
    it('dispatches a job via the Kernel container', function () {
        $kernel = Kernel::boot(dirname(__DIR__, 3) . '/src');

        // Restore error/exception handlers set by Tempest::boot()
        restore_error_handler();
        restore_exception_handler();

        // Replace the dispatcher with a fake
        $dispatched = [];
        $fakeDispatcher = new class($dispatched) implements JobDispatcher {
            /** @var list<array{job: object, delay: int|null}> */
            private array $dispatched;

            public function __construct(array &$dispatched)
            {
                $this->dispatched = &$dispatched;
            }

            public function dispatch(object $job, ?int $delay = null): void
            {
                $this->dispatched[] = ['job' => $job, 'delay' => $delay];
            }

            public function isAvailable(): bool
            {
                return true;
            }
        };

        Kernel::container()->singleton(JobDispatcher::class, fn() => $fakeDispatcher);

        // Call the actual dispatch() helper function
        dispatch(new JobDtoFixture(42, 'csv'));

        expect($dispatched)->toHaveCount(1);
        expect($dispatched[0]['job'])->toBeInstanceOf(JobDtoFixture::class);
        expect($dispatched[0]['job']->importId)->toBe(42);
        expect($dispatched[0]['delay'])->toBeNull();
    });

    it('passes delay to the dispatcher via helper', function () {
        $kernel = Kernel::boot(dirname(__DIR__, 3) . '/src');

        restore_error_handler();
        restore_exception_handler();

        $dispatched = [];
        $fakeDispatcher = new class($dispatched) implements JobDispatcher {
            /** @var list<array{job: object, delay: int|null}> */
            private array $dispatched;

            public function __construct(array &$dispatched)
            {
                $this->dispatched = &$dispatched;
            }

            public function dispatch(object $job, ?int $delay = null): void
            {
                $this->dispatched[] = ['job' => $job, 'delay' => $delay];
            }

            public function isAvailable(): bool
            {
                return true;
            }
        };

        Kernel::container()->singleton(JobDispatcher::class, fn() => $fakeDispatcher);

        dispatch(new JobDtoFixture(99, 'api'), delay: 3600);

        expect($dispatched)->toHaveCount(1);
        expect($dispatched[0]['delay'])->toBe(3600);
    });
});
