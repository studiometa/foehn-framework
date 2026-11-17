### Added

- `#[AsCustomizerSection]` adds a section to the Customizer with a native control for each setting it declares, stored as an option and read with `Settings::get()`. The id of a section that exists, such as `title_tagline`, adds the fields to that section
- `wp foehn inspect settings` gives the admin screen of each settings page and Customizer section

### Changed

- **Breaking:** `SettingsPageInterface` is renamed `SettingsInterface`, because a Customizer section implements it too. Rename it in each settings page class
