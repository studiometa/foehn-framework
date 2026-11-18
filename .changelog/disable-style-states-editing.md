### Added

- The `Hooks\Cleanup\DisableStyleStatesEditing` hook turns off the responsive and state style editing of WordPress 7.1 in the block editor: it sets the `responsiveEditingEnabled` and `blockStatesEditingEnabled` editor settings to `false`. Editors can no longer set block styles per viewport or per state (`:hover`, `:focus`), in the block inspector or in Global Styles. Styles that are already saved still render. Add it to the `hooks` of `foehn.config.php` to use it
