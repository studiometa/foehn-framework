<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Attributes;

use Attribute;

/**
 * Mark a class as a recurring cron job handler.
 *
 * The class must implement `__invoke()` with no required arguments.
 * The job is auto-scheduled by discovery using Action Scheduler.
 *
 * Usage:
 *
 *     #[AsCron('daily')]
 *     final class CleanupLogs
 *     {
 *         public function __invoke(): void { ... }
 *     }
 *
 *     #[AsCron('hourly', group: 'my-plugin')]
 *     final class SyncInventory
 *     {
 *         public function __invoke(): void { ... }
 *     }
 *
 * Available intervals: 'hourly', 'twicedaily', 'daily', 'weekly',
 * or any integer (seconds) for custom intervals.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsCron
{
    /**
     * The recurrence interval in seconds.
     */
    public int $intervalSeconds;

    /**
     * @param string|int $interval Recurrence: 'hourly', 'twicedaily', 'daily', 'weekly', or seconds as int
     * @param string $group Action Scheduler group for admin UI filtering
     * @param string|null $hook Custom hook name (defaults to class-derived name)
     */
    public function __construct(
        public string|int $interval,
        public string $group = 'foehn',
        public ?string $hook = null,
    ) {
        $this->intervalSeconds = match (true) {
            is_int($this->interval) => $this->interval,
            $this->interval === 'hourly' => 3600,
            $this->interval === 'twicedaily' => 43_200,
            $this->interval === 'daily' => 86_400,
            $this->interval === 'weekly' => 604_800,
            default => throw new \InvalidArgumentException(
                "Invalid cron interval '{$this->interval}'. Use 'hourly', 'twicedaily', 'daily', 'weekly', or an integer (seconds).",
            ),
        };
    }
}
