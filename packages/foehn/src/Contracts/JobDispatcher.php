<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Contracts;

/**
 * Interface for dispatching async jobs.
 *
 * Jobs are typed DTOs that get serialized and processed in the background
 * by their corresponding `#[AsJob]` handler.
 */
interface JobDispatcher
{
    /**
     * Dispatch a job for async processing.
     *
     * @param object $job The job DTO to dispatch
     * @param int|null $delay Optional delay in seconds before the job runs
     */
    public function dispatch(object $job, ?int $delay = null): void;

    /**
     * Check if the underlying queue system is available.
     */
    public function isAvailable(): bool;
}
