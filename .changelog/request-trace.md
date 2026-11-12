### Added

- `wp foehn trace [<path>|<id>|last|list]` prints what a front-end request ran: the status, time and query count, the template files WordPress tried and the one it found, the template controller, each Twig view with the views `renderFirst()` tried, its context providers and its context keys, the blocks, and the errors of the request. With a path, the command requests the page on the site; `--format=json` prints every field.
- In the `local` and `development` environments, each front-end request sends its id in the `X-Foehn-Trace` response header and stores its trace in `wp-content/cache/foehn/traces` (the last 100). `FoehnConfig::$traceRequests` (`trace_requests`) turns tracing on or off in any environment.
- `wp foehn errors --request=<id>` prints only the errors of one traced request. An error entry now carries the id of its request in `requestId`.
