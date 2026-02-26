<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Attributes;

use Attribute;
use Studiometa\Foehn\Jobs\CronInterval;

/**
 * Mark a class as a recurring cron job handler.
 *
 * The class must implement `__invoke()` with no required arguments.
 * The job is auto-scheduled by discovery using Action Scheduler.
 *
 * Usage:
 *
 *     #[AsCron(CronInterval::Daily)]
 *     final class CleanupLogs
 *     {
 *         public function __invoke(): void { ... }
 *     }
 *
 *     #[AsCron(CronInterval::Hourly, group: 'my-plugin')]
 *     final class SyncInventory
 *     {
 *         public function __invoke(): void { ... }
 *     }
 *
 *     // Custom interval in seconds
 *     #[AsCron(300)]
 *     final class PollExternalApi
 *     {
 *         public function __invoke(): void { ... }
 *     }
 *
 * Available intervals: CronInterval::Hourly, CronInterval::TwiceDaily,
 * CronInterval::Daily, CronInterval::Weekly, or any integer (seconds).
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsCron
{
    /**
     * The recurrence interval in seconds.
     */
    public int $intervalSeconds;

    /**
     * @param CronInterval|int $interval Recurrence interval (enum or custom seconds)
     * @param string $group Action Scheduler group for admin UI filtering
     * @param string|null $hook Custom hook name (defaults to class-derived name)
     */
    public function __construct(
        public CronInterval|int $interval,
        public string $group = 'foehn',
        public ?string $hook = null,
    ) {
        $this->intervalSeconds = $interval instanceof CronInterval ? $interval->value : $interval;
    }
}
