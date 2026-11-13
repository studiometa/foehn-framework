# Cron Jobs

Føhn provides `#[AsCron]` for jobs that run again and again. Action Scheduler runs them. Without Action Scheduler, Føhn schedules nothing.

For a job that runs one time, in the background, use `#[AsJob]` and [`dispatch()`](/api/helpers#dispatch).

## Installing Action Scheduler

The starter does not ship Action Scheduler. Install it from WordPress Packagist, then activate it:

```bash
composer require wpackagist-plugin/action-scheduler
wp plugin activate action-scheduler
```

Action Scheduler runs its queue on WordPress cron passes. If no cron pass runs, no job runs. In production, a real cron must run the scheduled events: see [The real cron](/guide/verification#the-real-cron).

## Basic Cron Job

Put `#[AsCron]` on a class with a public `__invoke()` method. The method must not have required arguments.

```php
<?php
// app/Jobs/PurgeExpiredExports.php

namespace App\Jobs;

use Studiometa\Foehn\Attributes\AsCron;
use Studiometa\Foehn\Jobs\CronInterval;

#[AsCron(CronInterval::Daily)]
final class PurgeExpiredExports
{
    public function __invoke(): void
    {
        // …
    }
}
```

The constructor can receive services from the container.

## Schedules

The first argument is the schedule. It is an interval or a cron expression.

| Schedule                   | Runs                                        |
| -------------------------- | ------------------------------------------- |
| `CronInterval::Hourly`     | Every hour                                  |
| `CronInterval::TwiceDaily` | Every 12 hours                              |
| `CronInterval::Daily`      | Every 24 hours                              |
| `CronInterval::Weekly`     | Every 7 days                                |
| `300`                      | Every 300 seconds                           |
| `'0 0 * * *'`              | At midnight in the site timezone, every day |

### Intervals

An interval starts when Føhn schedules the job for the first time, usually at the first request after a deploy. A daily job can thus run at any hour. Use a cron expression when the time of day is important.

### Cron Expressions

A cron expression sets the minute, the hour, the day of the month, the month and the day of the week, in this order:

```php
// Every day at midnight, for pages that show today's date
#[AsCron('0 0 * * *')]

// At 06:30 from Monday to Friday
#[AsCron('30 6 * * 1-5')]

// Every 15 minutes
#[AsCron('*/15 * * * *')]
```

Each field accepts `*`, a number, a range (`1-5`), a list (`0,30`) and a step (`*/15`, `8-18/2`). Sunday is `0` or `7`. Names (`MON`, `JAN`), macros (`@daily`) and the `L`, `W` and `#` modifiers are not accepted.

::: warning
The cron library of Action Scheduler reads a step on `*` as "every value that the step divides". In the minute and the hour, this is the usual result: `*/15` runs at minutes 0, 15, 30 and 45. In the day of the month and the month, it is not: `*/2` in the day of the month runs on days 2, 4, 6 and so on, not on days 1, 3, 5. Use a range with a step, such as `1-31/2`, to start from the first day.
:::

Føhn checks the expression when it reads the attribute. An expression that never matches a date, such as `0 0 31 2 *` (31 February), is not valid. When the expression is not valid, Føhn does not schedule the class and records a [problem](/guide/troubleshooting) that tells why. The other classes are not affected.

### Timezone

A cron expression runs in the **site timezone**: the timezone in **Settings → General**, which `wp_timezone()` returns. With `Europe/Paris`, `0 0 * * *` runs at midnight in Paris: at 23:00 UTC in winter and at 22:00 UTC in summer.

`as_schedule_cron_action()` from Action Scheduler reads expressions in UTC. Action Scheduler makes all its dates in UTC, and its cron library reads each field in the timezone of the date it gets. Thus Føhn does not use `as_schedule_cron_action()`. It saves the action with its own schedule class, `Studiometa\Foehn\Jobs\SiteTimezoneCronSchedule`, which moves each date to the site timezone before it calculates the next run.

The Action Scheduler admin page (**Tools → Scheduled Actions**) shows the expression and the next run of the action.

::: warning
If you change the site timezone, the next run that is already scheduled does not move. The runs after it use the new timezone.
:::

## Changing a Schedule

Føhn compares the schedule of the pending action with the attribute on each request. When the interval or the expression changed, or an interval became an expression, Føhn removes the pending actions of the hook and schedules the job again. When nothing changed, Føhn does nothing.

This costs two small database queries for each cron job on each request: one to find the pending action and one to read its schedule.

When an instance runs, it has no pending action. Føhn then waits: Action Scheduler schedules the next run when the instance ends, and Føhn checks it on the next request.

## Group and Hook

```php
#[AsCron(CronInterval::Hourly, group: 'my-plugin', hook: 'my_plugin/sync_inventory')]
```

| Argument | Default                         | Description                                        |
| -------- | ------------------------------- | -------------------------------------------------- |
| `group`  | `'foehn'`                       | The Action Scheduler group, to filter in the admin |
| `hook`   | A name made from the class name | The action hook that runs the job                  |

Føhn looks for pending actions of the hook in this group only.
