### Added

- The starter has a seeder, `App\Seeders\MenusSeeder`: `wp foehn seed` creates the header, footer and legal menus with a Home link when they are missing, and assigns them to their locations. A second run creates nothing.

### Changed

- The starter `.env.example` sets `DISABLE_WP_CRON=false`, so WordPress cron, `#[AsCron]`, `#[AsJob]` and the page-cache warm-up run in a new local project. Production keeps the default (`true`) and a real cron. Add the line to the `.env` of a local project created from an earlier starter.
- The starter `theme/app/page-cache.config.php` names the page cache options a project may need, with links to the guide: `browserMaxAge`, `cacheControl`, `responseHeaders`, `warmAfterPurge`, `warmSitemap`, `warmExclude` and bucket `storage`. The active values do not change.
- The starter enables `DisableFileEditor` in `theme/app/foehn.config.php`: the admin has no theme or plugin file editor. A comment names `SecurityHeaders` and `RestApiAuth`, which stay opt-in.
- The starter no longer ships the framework smoke suites in `tests/smoke/`: a created project keeps `tests/smoke/run.sh` and a short Pest test, `SiteTest.php`, that `composer test:smoke` runs against the started ddev site (home page with no PHP error, active theme). The starter `composer.json` no longer has the `Studiometa\Foehn\Smoke\` autoload. A project created from an earlier starter can delete the other files in `tests/smoke/` and that autoload entry.

### Fixed

- In a project created from the starter, `ddev start` now installs WordPress (`admin` / `admin`) and activates the theme when WordPress is not installed, and runs `composer install` when `vendor/` is missing, as the documentation says. The hook is in `.ddev/config.yaml`. A project created from an earlier starter can copy the `hooks:` block of the starter `.ddev/config.yaml`.
