### Changed

- The starter no longer ships the framework smoke suites in `tests/smoke/`: a created project keeps `tests/smoke/run.sh` and a short Pest test, `SiteTest.php`, that `composer test:smoke` runs against the started ddev site (home page with no PHP error, active theme). The starter `composer.json` no longer has the `Studiometa\Foehn\Smoke\` autoload. A project created from an earlier starter can delete the other files in `tests/smoke/` and that autoload entry.

### Fixed

- In a project created from the starter, `ddev start` now installs WordPress (`admin` / `admin`) and activates the theme when WordPress is not installed, and runs `composer install` when `vendor/` is missing, as the documentation says. The hook is in `.ddev/config.yaml`. A project created from an earlier starter can copy the `hooks:` block of the starter `.ddev/config.yaml`.
