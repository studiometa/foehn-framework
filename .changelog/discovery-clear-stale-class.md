### Fixed

- A discovery cache that names a deleted class no longer stops WordPress from booting. Føhn empties the cache and discovers again. Before, every request and every WP-CLI command, `wp foehn discovery:clear` too, failed with a `ReflectionException`, and the cache folder had to be deleted by hand
