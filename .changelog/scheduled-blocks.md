### Added

- Every block, core blocks included, can show only between two dates: a "Schedule" panel in the block inspector sets its `visibleFrom` and `visibleUntil` attributes, read in the site time zone. With the page cache on, a page that renders a scheduled block is purged by a cron job once a date has passed, at most five minutes or one real cron step later. Run `composer install` to copy the panel script into `web/wp-content/foehn/schedule.js`.
