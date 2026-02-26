<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Jobs;

use RuntimeException;
use Studiometa\Foehn\Contracts\JobDispatcher;

/**
 * Job dispatcher backed by Action Scheduler.
 *
 * Serializes job DTOs and schedules them as single Action Scheduler actions.
 * The corresponding `#[AsJob]` handler will deserialize and process them.
 */
class ActionSchedulerJobDispatcher implements JobDispatcher
{
    public function __construct(
        private readonly JobRegistry $jobRegistry,
    ) {}

    /**
     * Dispatch a job for async processing.
     *
     * @param object $job The job DTO to dispatch
     * @param int|null $delay Optional delay in seconds before the job runs
     * @throws RuntimeException If Action Scheduler is not available or no handler registered
     */
    public function dispatch(object $job, ?int $delay = null): void
    {
        if (!$this->isAvailable()) {
            throw new RuntimeException('Action Scheduler is not available. Install woocommerce/action-scheduler.');
        }

        $dtoClass = $job::class;
        $registration = $this->jobRegistry->getForDto($dtoClass);

        if ($registration === null) {
            throw new RuntimeException("No #[AsJob] handler registered for DTO '{$dtoClass}'.");
        }

        $payload = JobSerializer::serialize($job);
        $timestamp = time() + ($delay ?? 0);

        \as_schedule_single_action($timestamp, $registration['hook'], [$payload], $registration['group']);
    }

    /**
     * Check if Action Scheduler is available.
     */
    public function isAvailable(): bool
    {
        return function_exists('as_schedule_single_action');
    }
}
