# #[AsCron]

Mark a class as a recurring job. Action Scheduler runs it on a schedule.

## Signature

```php
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsCron
{
    public ?int $intervalSeconds;

    public ?string $expression;

    public function __construct(
        public CronInterval|int|string $schedule,
        public string $group = 'foehn',
        public ?string $hook = null,
    ) {}
}
```

## Parameters

| Parameter  | Type                        | Default   | Description                                                                                                  |
| ---------- | --------------------------- | --------- | ------------------------------------------------------------------------------------------------------------ |
| `schedule` | `CronInterval\|int\|string` | —         | An interval (`CronInterval` case or seconds) or a cron expression (required)                                 |
| `group`    | `string`                    | `'foehn'` | The Action Scheduler group, to filter in the admin                                                           |
| `hook`     | `?string`                   | `null`    | The action hook. `null` makes it from the class name: `App\Jobs\SyncStock` gives `foehn/app/jobs/sync_stock` |

## Properties

The constructor sets two read-only properties from `schedule`:

| Property          | Type      | Value                                                                    |
| ----------------- | --------- | ------------------------------------------------------------------------ |
| `intervalSeconds` | `?int`    | The interval in seconds, or `null` when `schedule` is a cron expression  |
| `expression`      | `?string` | The cron expression with single spaces, or `null` when it is an interval |

## CronInterval

`Studiometa\Foehn\Jobs\CronInterval` is an integer enum:

| Case         | Seconds  |
| ------------ | -------- |
| `Hourly`     | `3600`   |
| `TwiceDaily` | `43200`  |
| `Daily`      | `86400`  |
| `Weekly`     | `604800` |

## Requirements

- The class has a public `__invoke()` method with no required arguments. The constructor can receive services from the container.
- A cron expression has five fields: minute, hour, day of month, month and day of week. It runs in the site timezone.
- Action Scheduler is active. Without it, Føhn schedules nothing.

When the class has no public `__invoke()`, or the expression is not valid, Føhn does not schedule it and `wp foehn inspect problems` reports it.

## Usage

```php
<?php

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

// Every 300 seconds, in its own group
#[AsCron(300, group: 'inventory')]
final class SyncStock
{
    public function __invoke(): void {}
}

// At 06:30 from Monday to Friday, in the site timezone
#[AsCron('30 6 * * 1-5')]
final class SendDigest
{
    public function __invoke(): void {}
}
```

## Related

- [Guide: Cron Jobs](/guide/cron)
- [`#[AsJob]`](./as-job)
