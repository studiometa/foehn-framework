### Added

- `wp foehn errors` prints the last errors of the site in a few lines: uncaught exceptions, fatal errors, and the warnings and notices of the theme's own code, with the file and line, the Twig template and line, a short stack of the theme's frames, the URL, the WordPress template, the controller, the view and its context keys. `--limit`, `--level`, `--since`, `--format=json` and `--clear` filter and empty the log. See [The error log](https://studiometa.github.io/foehn-framework/guide/troubleshooting#the-error-log)
- Errors are recorded in the `local` and `development` environments, to `wp-content/cache/foehn/errors/errors.jsonl` (3 files of 256 KB at most). `FoehnConfig::$recordErrors` (`'record_errors'` in the array config) turns recording on in another environment, or off
