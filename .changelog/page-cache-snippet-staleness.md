### Added

- `wp foehn verify --profile=production` has a `page-cache-snippet` check. It fails when an nginx page cache snippet of the project carries the policy hash of another config or of an older release. A deploy that runs the production profile now fails until the snippet is generated again with `wp foehn cache:config --server=nginx`

### Fixed

- `wp foehn cache:status` finds each nginx page cache snippet in `config/nginx/` and `.ddev/nginx/` by the comment that the generator writes, whatever its file name. Before, it read only `foehn-page-cache.conf`, so a snippet committed under another name went stale after an upgrade without a warning
