### Added

- `#[AsCron]` accepts a cron expression with five fields, for example `#[AsCron('0 0 * * *')]` for every day at midnight. The expression runs in the site timezone (`wp_timezone()`), not in UTC as with `as_schedule_cron_action()`. An invalid expression skips the class and records a discovery problem.
- `#[AsCron]` schedules a job again when its interval or its expression changed, or when an interval became an expression. Before, a changed schedule kept the old one until somebody removed the pending action by hand.

### Changed

- The first argument of `#[AsCron]` is now `schedule`, not `interval`. Projects that write `#[AsCron(interval: …)]` must write `#[AsCron(schedule: …)]`. `AsCron::$intervalSeconds` is `null` for a cron expression; `AsCron::$expression` holds the expression.
