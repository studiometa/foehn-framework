### Added

- `studiometa/foehn-installer` copies the content of the project `public/` directory into the generated web root on `composer install`, dotfiles included. Put the files that must be at the document root (`.user.ini`, `robots.txt`, `favicon.ico`, `.well-known/`) there. `extra.foehn.public-dir` sets another directory. The installer does not copy to `index.php`, `wp-config.php`, the WordPress directory or `wp-content/`, and the next install removes a file deleted from `public/`. A Docker build must copy `public/` before `composer install`.
- `wp foehn verify --profile=production` has a `public-files` check: it fails when a file of the public directory is missing from the web root or is different there.
- The starter has an empty `public/` directory.
