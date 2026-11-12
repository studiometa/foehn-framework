### Added

- `wp foehn db:export [<file>|-] [--from=<date>]` writes a light, anonymized, gzipped dump of the database, to import on a local copy of the site. Users, user meta, comments and WooCommerce orders are anonymized, transients and old revisions are left out, and a table that no rule covers is exported without rows and listed in a warning. A project declares its own tables in `app/db-export.config.php` with `DatabaseExportConfig`. See [Database Export](https://studiometa.github.io/foehn-framework/guide/database-export)

### Dependencies

- `studiometa/foehn` requires `druidfi/mysqldump-php` `^2`, which dumps the database with PDO, without a `mysqldump` binary
