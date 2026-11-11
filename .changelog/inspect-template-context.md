### Added

- `wp foehn inspect settings` lists each `#[AsSettingsPage]` with the options it stores: option name, type, default and sanitizer. The sanitizer runs under WP-CLI too, so `wp option update` saves a value as the admin form does
- The `blocks` section of `wp foehn inspect` gives each block attribute with its type, default and allowed values
