### Changed

- The starter no longer ships the framework smoke suites in `tests/smoke/`: a created project keeps `tests/smoke/run.sh` and a short Pest test, `SiteTest.php`, that `composer test:smoke` runs against the started ddev site (home page with no PHP error, active theme). The starter `composer.json` no longer has the `Studiometa\Foehn\Smoke\` autoload. A project created from an earlier starter can delete the other files in `tests/smoke/` and that autoload entry.
