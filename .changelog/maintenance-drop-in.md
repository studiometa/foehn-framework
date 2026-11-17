### Added

- The installer writes a `wp-content/maintenance.php` drop-in. During maintenance it sends a `503` with `Retry-After` and prints the theme's `maintenance.php`, or a plain page when the theme has none
