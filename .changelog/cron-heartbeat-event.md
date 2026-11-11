### Changed

- **Breaking:** the generated `wp-config.php` reads `DISABLE_WP_CRON` from the environment and turns the pseudo-cron off by default. A host must run `wp cron event run --due-now` on a schedule (the runtime image does), or set `DISABLE_WP_CRON=false` to get the pseudo-cron back. Run `composer install` to regenerate `wp-config.php`. See [The real cron](https://studiometa.github.io/foehn-framework/guide/verification#the-real-cron)
- Føhn writes the cron heartbeat itself: a `foehn_cron_heartbeat` event, scheduled every minute on a new `foehn_every_minute` recurrence, writes `foehn_cron_last_run` each time a real cron runs it. Any host that runs `wp cron event run --due-now` (a Forge scheduled job, a system crontab) now passes `cron-heartbeat`, not only the runtime image
- The `real-cron` check of `wp foehn verify --profile=production` passes when `DISABLE_WP_CRON` is on. `cron-heartbeat` proves that a real cron runs
- The runtime image's cron runs only `wp cron event run --due-now`, and logs nothing when only the heartbeat event ran

### Removed

- **Breaking:** `FOEHN_CRON_ENABLED`. The generated `wp-config.php`, the runtime image and production verification no longer read it, and the image's cron can no longer be turned off. Use `DISABLE_WP_CRON=false` for the pseudo-cron
