### Added

- Every block, core blocks included, can show only between two dates: a "Schedule" panel in the block inspector sets its `visibleFrom` and `visibleUntil` attributes, read in the site time zone. With the page cache on, a stored page that renders a scheduled block is purged on the `foehn_cron_heartbeat` WordPress cron event once a date has passed, at most one real cron step later, with no Action Scheduler necessary. Run `composer install` to copy the panel script into `web/wp-content/foehn/schedule.js`.
