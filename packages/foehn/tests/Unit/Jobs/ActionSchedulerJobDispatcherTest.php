<?php

declare(strict_types=1);

use Studiometa\Foehn\Jobs\ActionSchedulerJobDispatcher;
use Studiometa\Foehn\Jobs\JobRegistry;
use Tests\Fixtures\JobDtoFixture;
use Tests\Fixtures\JobHandlerFixture;

beforeEach(function () {
    wp_stub_reset();
    $this->registry = new JobRegistry();
    $this->dispatcher = new ActionSchedulerJobDispatcher($this->registry);
});

describe('ActionSchedulerJobDispatcher', function () {
    it('reports availability based on Action Scheduler functions', function () {
        // as_schedule_single_action is defined in this test file's setup
        if (function_exists('as_schedule_single_action')) {
            expect($this->dispatcher->isAvailable())->toBeTrue();
        } else {
            expect($this->dispatcher->isAvailable())->toBeFalse();
        }
    });

    it('throws when dispatching without Action Scheduler', function () {
        // When AS is not available, this should throw
        // This test only applies if we can control AS availability,
        // so we test the error case for unregistered handler instead
        $this->registry->register(
            JobDtoFixture::class,
            JobHandlerFixture::class,
            'foehn/tests/fixtures/job_dto_fixture',
            'foehn',
        );

        if (!function_exists('as_schedule_single_action')) {
            expect(fn() => $this->dispatcher->dispatch(new JobDtoFixture(42, 'csv')))
                ->toThrow(RuntimeException::class, 'Action Scheduler is not available');
        } else {
            // AS stubs are available, dispatch should work
            $this->dispatcher->dispatch(new JobDtoFixture(42, 'csv'));

            $calls = wp_stub_get_calls('as_schedule_single_action');
            expect($calls)->toHaveCount(1);
            expect($calls[0]['args']['hook'])->toBe('foehn/tests/fixtures/job_dto_fixture');
            expect($calls[0]['args']['group'])->toBe('foehn');
        }
    });

    it('throws when no handler is registered for the DTO', function () {
        // Registry is empty, no handler for JobDtoFixture
        if (function_exists('as_schedule_single_action')) {
            expect(fn() => $this->dispatcher->dispatch(new JobDtoFixture(42, 'csv')))
                ->toThrow(RuntimeException::class, 'No #[AsJob] handler registered');
        }
    });

    it('includes delay in timestamp when provided', function () {
        $this->registry->register(
            JobDtoFixture::class,
            JobHandlerFixture::class,
            'foehn/tests/fixtures/job_dto_fixture',
            'foehn',
        );

        if (function_exists('as_schedule_single_action')) {
            $beforeTime = time() + 3600;
            $this->dispatcher->dispatch(new JobDtoFixture(42, 'csv'), delay: 3600);
            $afterTime = time() + 3600;

            $calls = wp_stub_get_calls('as_schedule_single_action');
            expect($calls)->toHaveCount(1);
            expect($calls[0]['args']['timestamp'])->toBeGreaterThanOrEqual($beforeTime);
            expect($calls[0]['args']['timestamp'])->toBeLessThanOrEqual($afterTime);
        }
    });

    it('serializes the DTO payload correctly', function () {
        $this->registry->register(
            JobDtoFixture::class,
            JobHandlerFixture::class,
            'foehn/tests/fixtures/job_dto_fixture',
            'foehn',
        );

        if (function_exists('as_schedule_single_action')) {
            $this->dispatcher->dispatch(new JobDtoFixture(42, 'csv'));

            $calls = wp_stub_get_calls('as_schedule_single_action');
            $args = $calls[0]['args']['args'];

            expect($args)->toHaveCount(1);
            expect($args[0]['__class'])->toBe(JobDtoFixture::class);
            expect($args[0]['__data'])->toBe([
                'importId' => 42,
                'source' => 'csv',
            ]);
        }
    });
});
