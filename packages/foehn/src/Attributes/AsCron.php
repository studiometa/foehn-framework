<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Attributes;

use Attribute;
use InvalidArgumentException;
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
 *     // Cron expression, read in the site timezone: every day at midnight
 *     #[AsCron('0 0 * * *')]
 *     final class PurgeAgenda
 *     {
 *         public function __invoke(): void { ... }
 *     }
 *
 * Available schedules: CronInterval::Hourly, CronInterval::TwiceDaily,
 * CronInterval::Daily, CronInterval::Weekly, any integer (seconds), or a cron
 * expression with five fields (minute, hour, day of month, month, day of week).
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsCron
{
    /**
     * The allowed values of each field of a cron expression, in order.
     *
     * @var list<array{string, int, int}>
     */
    private const array FIELDS = [
        ['minute',       0, 59],
        ['hour',         0, 23],
        ['day of month', 1, 31],
        ['month',        1, 12],
        ['day of week',  0, 7],
    ];

    /**
     * The recurrence interval in seconds, or null when the schedule is a cron expression.
     */
    public ?int $intervalSeconds;

    /**
     * The cron expression with single spaces, or null when the schedule is an interval.
     */
    public ?string $expression;

    /**
     * @param CronInterval|int|string $schedule Recurrence interval (enum or custom seconds) or cron expression
     * @param string $group Action Scheduler group for admin UI filtering
     * @param string|null $hook Custom hook name (defaults to class-derived name)
     *
     * @throws InvalidArgumentException When $schedule is not a valid cron expression
     */
    public function __construct(
        public CronInterval|int|string $schedule,
        public string $group = 'foehn',
        public ?string $hook = null,
    ) {
        if (is_string($schedule)) {
            $this->intervalSeconds = null;
            $this->expression = self::normalizeExpression($schedule);

            return;
        }

        $this->intervalSeconds = $schedule instanceof CronInterval ? $schedule->value : $schedule;
        $this->expression = null;
    }

    /**
     * Validate a cron expression and join its fields with single spaces.
     *
     * Each field is a list of `*`, a number or a range of numbers, each with an
     * optional `/step`. Names (`MON`, `JAN`), macros (`@daily`) and the `L`, `W`
     * and `#` modifiers are not accepted.
     *
     * The result is the form Action Scheduler gives back for a stored cron schedule,
     * so discovery can compare the two.
     *
     * @throws InvalidArgumentException
     */
    private static function normalizeExpression(string $expression): string
    {
        $fields = preg_split('/\s+/', trim($expression), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($fields) !== count(self::FIELDS)) {
            throw new InvalidArgumentException(sprintf(
                '"%s" is not a valid cron expression: it must have 5 fields (minute, hour, day of month, month, day of week), it has %d.',
                $expression,
                count($fields),
            ));
        }

        foreach ($fields as $index => $field) {
            [$name, $min, $max] = self::FIELDS[$index];

            foreach (explode(',', $field) as $item) {
                if (!self::isValidItem($item, $min, $max)) {
                    throw new InvalidArgumentException(sprintf(
                        '"%s" is not a valid cron expression: "%s" is not a valid %s. Use *, a number from %d to %d, a range such as %d-%d, and an optional /step.',
                        $expression,
                        $field,
                        $name,
                        $min,
                        $max,
                        $min,
                        $max,
                    ));
                }
            }
        }

        return implode(' ', $fields);
    }

    /**
     * Whether one item of a field list is `*`, `n` or `n-m`, with an optional `/step`.
     */
    private static function isValidItem(string $item, int $min, int $max): bool
    {
        $matches = [];

        if (preg_match('/^(?:(\*)|(\d+)(?:-(\d+))?)(?:\/(\d+))?$/', $item, $matches, PREG_UNMATCHED_AS_NULL) !== 1) {
            return false;
        }

        [, $any, $from, $to, $step] = $matches + [null, null, null, null, null];

        if ($step !== null && (int) $step === 0) {
            return false;
        }

        if ($any !== null) {
            return true;
        }

        $from = (int) $from;
        $to = $to !== null ? (int) $to : $from;

        return $from >= $min && $to <= $max && $from <= $to;
    }
}
