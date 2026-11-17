### Fixed

- `ImageCacheHooks` now also clears the transformer cache of the full-size original image (`original_image`) when an attachment is updated or deleted. A large image is scaled to `-scaled`, and with the client-side media processing of WordPress 7.1 a transform asked for before that swap was cached under the original path and was never cleared.
