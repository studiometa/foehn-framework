### Fixed

- The runtime image and the ddev configurations of the starter and the demo build WP-CLI on PHP 8.5 again. The `composer:2` image moved to PHP 8.6 without `mbstring`, and the build failed. A project made from the starter can copy the new `.ddev/web-build/prepend.Dockerfile.wp-cli`.
