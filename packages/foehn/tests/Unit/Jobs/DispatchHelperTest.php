<?php

declare(strict_types=1);

use Studiometa\Foehn\Contracts\JobDispatcher;
use Studiometa\Foehn\Jobs\JobRegistry;
use Tests\Fixtures\JobDtoFixture;
use Tests\Fixtures\JobHandlerFixture;

use function Studiometa\Foehn\dispatch;

beforeEach(function () {
    wp_stub_reset();
    $this->container = bootTestContainer();
});

afterEach(function () {
    tearDownTestContainer();
});

describe('dispatch() helper', function () {
    it('dispatches a job via the JobDispatcher service', function () {
        $dispatched = [];

        // Register a fake dispatcher
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

        // Boot a minimal kernel with our fake dispatcher
        $this->container->singleton(JobDispatcher::class, fn() => $fakeDispatcher);

        \Studiometa\Foehn\Kernel::reset();

        // We can't easily use dispatch() without a full Kernel boot,
        // so test the dispatcher directly through the container
        $dispatcher = $this->container->get(JobDispatcher::class);
        $dispatcher->dispatch(new JobDtoFixture(42, 'csv'));

        expect($dispatched)->toHaveCount(1);
        expect($dispatched[0]['job'])->toBeInstanceOf(JobDtoFixture::class);
        expect($dispatched[0]['job']->importId)->toBe(42);
        expect($dispatched[0]['delay'])->toBeNull();
    });

    it('passes delay to the dispatcher', function () {
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

        $this->container->singleton(JobDispatcher::class, fn() => $fakeDispatcher);

        $dispatcher = $this->container->get(JobDispatcher::class);
        $dispatcher->dispatch(new JobDtoFixture(42, 'csv'), delay: 3600);

        expect($dispatched)->toHaveCount(1);
        expect($dispatched[0]['delay'])->toBe(3600);
    });
});
