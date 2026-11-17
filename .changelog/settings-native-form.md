### Changed

- **Breaking:** `#[AsSettingsPage]` draws its form with `add_settings_field()`, a native field for each setting, so the page looks like the WordPress settings screens. The `template` argument and `SettingsFormInterface` are removed: give each `Setting` a `label`, a `description` and a control instead, and delete the Twig form
- `Setting::string()` takes `control` (`text`, `textarea`, `email`, `url` or `tel`), and `Setting::string()` and `Setting::int()` take `options` for a `<select>`. Every factory takes `label`. The control sets the default sanitizer, and a select keeps one of its options
- `wp foehn inspect settings` gives the label and the control of each setting

### Fixed

- A change to a setting that a `#[AsSettingsPage]` declares empties the page cache, as a change to the site title does. The pages kept the old value until their TTL expired
