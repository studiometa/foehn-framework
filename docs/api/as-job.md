# #[AsJob]

Mark a class as the handler of a queued job. `dispatch()` queues the job, and Action Scheduler runs the handler later.

## Signature

```php
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsJob
{
    public function __construct(
        public string $group = 'foehn',
        public ?string $hook = null,
    ) {}
}
```

## Parameters

| Parameter | Type      | Default   | Description                                                                                                                     |
| --------- | --------- | --------- | ------------------------------------------------------------------------------------------------------------------------------- |
| `group`   | `string`  | `'foehn'` | The Action Scheduler group, to filter in the admin                                                                              |
| `hook`    | `?string` | `null`    | The action hook. `null` makes it from the class name of the job: `App\Jobs\ProcessImport` gives `foehn/app/jobs/process_import` |

## Requirements

- The handler has a public `__invoke()` method with exactly one parameter, typed with the class of the job.
- The job is an object that holds data only. Action Scheduler stores its public properties, so they must be scalar values or arrays: give an ID, not a post or a service.
- Action Scheduler is active. Without it, `dispatch()` throws a `RuntimeException`.

When the handler has no public `__invoke()`, or its parameter is not typed with a class, Føhn registers nothing and `wp foehn inspect problems` reports it.

## Usage

```php
<?php

namespace App\Jobs;

use Studiometa\Foehn\Attributes\AsJob;

final readonly class ProcessImport
{
    public function __construct(
        public int $importId,
    ) {}
}

#[AsJob(group: 'imports')]
final class ProcessImportHandler
{
    public function __invoke(ProcessImport $job): void
    {
        // The work, in the background
    }
}
```

Queue the job with [`dispatch()`](./helpers#dispatch):

```php
use function Studiometa\Foehn\dispatch;

dispatch(new ProcessImport(42));

// Not before 5 minutes from now
dispatch(new ProcessImport(42), delay: 300);
```

`dispatch()` throws a `RuntimeException` when no `#[AsJob]` class handles the job.

## Related

- [`dispatch()`](./helpers#dispatch)
- [`#[AsCron]`](./as-cron)
- [Guide: Cron Jobs](/guide/cron)
