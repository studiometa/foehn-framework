<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Jobs;

/**
 * Standard cron recurrence intervals.
 *
 * Backed by their duration in seconds.
 */
enum CronInterval: int
{
    case Hourly = 3_600;
    case TwiceDaily = 43_200;
    case Daily = 86_400;
    case Weekly = 604_800;
}
