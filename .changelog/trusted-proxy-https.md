### Added

- `FOEHN_TRUST_FORWARDED_PROTO=true` in the environment makes the generated `wp-config.php` set `$_SERVER['HTTPS']` to `on` when the `X-Forwarded-Proto` header says `https`. Use it on a host behind a load balancer or a CDN that terminates TLS, where `is_ssl()` was false and WordPress wrote `http://` URLs. Off by default: turn it on only when every request comes through the proxy and the proxy replaces the header. Run `composer install` to generate `wp-config.php` again
