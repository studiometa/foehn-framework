### Added

- `wp foehn log` reads the WordPress debug log (`wp-content/debug.log`, or the path `WP_DEBUG_LOG` holds) as entries: level, message, file and line, and the stack trace of a fatal error. Entries that repeat are one group with a count and the time of the first and the last. It prints the last 20 groups; `--level`, `--since`, `--grep`, `--limit` and `--format=json` select and format them. When `WP_DEBUG_LOG` is off, it says how to turn it on: `WP_DEBUG=true` in `.env`.
