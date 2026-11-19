### Added

- `#[AsPostMeta]` takes a `control` (`text`, `textarea`, `email`, `url`, `tel`, `number`, `select`, `checkbox`), a `label` and `options`: a key with a control gets a native field in a "Fields" meta box on its post type, drawn like a field of `#[AsSettingsPage]`, in the classic and the block editor. A save changes only the keys in the request and checks the nonce and the key's `capability`. Keys without a control get no field, as before.
