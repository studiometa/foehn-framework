<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\DiscoveryLocation;
use Studiometa\Foehn\Discovery\JobDiscovery;
use Studiometa\Foehn\Jobs\JobRegistry;
use Studiometa\Foehn\Jobs\JobSerializer;
use Tests\Fixtures\JobDtoFixture;
use Tests\Fixtures\JobHandlerFixture;

beforeEach(function () {
    wp_stub_reset();
    $this->container = bootTestContainer();
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    $this->registry = new JobRegistry();
    $this->discovery = new JobDiscovery($this->registry);
});

afterEach(fn() => tearDownTestContainer());

describe('JobDiscovery apply callback', function () {
    it('deserializes payload and invokes the handler when the action callback fires', function () {
        // Track invocations
        $receivedJob = null;
        $handler = new class($receivedJob) {
            private ?object $receivedJob;

            public function __construct(?object &$receivedJob)
            {
                $this->receivedJob = &$receivedJob;
            }

            public function __invoke(JobDtoFixture $job): void
            {
                $this->receivedJob = $job;
            }
        };

        $this->container->singleton(JobHandlerFixture::class, fn() => $handler);

        $this->discovery->discover($this->location, new ReflectionClass(JobHandlerFixture::class));
        $this->discovery->apply();

        // Capture the add_action callback
        $actionCalls = wp_stub_get_calls('add_action');
        $jobActions = array_values(array_filter(
            $actionCalls,
            fn($call) => $call['args']['hook'] === 'foehn/tests/fixtures/job_dto_fixture',
        ));

        expect($jobActions)->toHaveCount(1);

        // Build a serialized payload like Action Scheduler would provide
        $payload = JobSerializer::serialize(new JobDtoFixture(42, 'csv'));

        // Invoke the callback — exercises deserialize + get() + $handler($job) path
        $callback = $jobActions[0]['args']['callback'];
        $callback($payload);

        expect($receivedJob)->toBeInstanceOf(JobDtoFixture::class);
        expect($receivedJob->importId)->toBe(42);
        expect($receivedJob->source)->toBe('csv');
    });
});
