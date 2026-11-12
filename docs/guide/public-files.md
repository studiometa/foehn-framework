# Public Files

`studiometa/foehn-installer` generates `web/` again on each `composer install`. Do not put a file in `web/` yourself: the next install or deploy does not keep it. Put the file in `public/` at the project root. The installer copies the content of `public/` into `web/`.

```
public/
├── .user.ini
├── .well-known/
│   └── security.txt
├── favicon.ico
└── robots.txt
```

After `composer install`, `web/.user.ini`, `web/.well-known/security.txt`, `web/favicon.ico` and `web/robots.txt` exist. The relative paths do not change. Dotfiles are copied too.

The starter has an empty `public/` with a `.gitkeep`.

## What goes in `public/`

Files that must be at the document root, and that you keep in the repository:

- `.user.ini`, for PHP-FPM settings of the site, for example `auto_prepend_file`.
- The file a firewall plugin prepends with `.user.ini`, for example `wordfence-waf.php` for Wordfence extended protection. Copy the file the plugin writes into `public/`, and commit it.
- `robots.txt`, to replace the virtual one of WordPress.
- `favicon.ico`.
- `.well-known/` files: `security.txt`, app site association files, ACME challenge files.
- `ads.txt` and `app-ads.txt`.
- Verification files of search consoles and other services.

## What does not go in `public/`

- Uploads. They are in `web/wp-content/uploads/`. See [Uploads](./uploads).
- Caches and other files that the site writes when it runs.
- Theme assets. Vite builds them in the theme. See [Assets](./assets).
- Files that contain secrets. Each file in `public/` can be served.

## Rules

- The installer copies the files. It does not create symlinks. The web root stays complete without the project directory, for a runtime image or an `rsync` deployment.
- The installer copies after it generates the web root. The paths of the installer and of WordPress have priority: `index.php`, `wp-config.php`, the WordPress directory (`wp/`) and all of `wp-content/`. The installer does not copy a file of `public/` to these paths. It shows a warning.
- The installer does not copy a symlink of `public/`. It shows a warning. Put the file itself in `public/`.
- When you remove a file from `public/`, the next `composer install` removes it from `web/`. The installer keeps the list of the files it copied in `web/wp-content/foehn/public-files.php`. It removes only files of this list.
- When you change a file in `web/` after the copy, the next `composer install` writes the file of `public/` again.

## Another directory

Set `extra.foehn.public-dir` in `composer.json`:

```json
{
  "extra": {
    "foehn": {
      "public-dir": "static"
    }
  }
}
```

The directory must be in the project. The installer refuses a directory outside the project, the project root itself, and a directory that is the web root, is in the web root or contains the web root. It shows a warning and copies nothing.

## Docker images

The installer runs during `composer install`. Copy `public/` into the build stage before `composer install`, as you copy the theme:

```dockerfile
COPY composer.json composer.lock ./
COPY theme/ ./theme/
COPY public/ ./public/
RUN composer install --no-dev --no-interaction --optimize-autoloader
```

The copied files are part of `web/`. The ownership rule of the [runtime image](./docker-image#using-it) does not change: they belong to `root`, and PHP can read them.

## Verification

`wp foehn verify --profile=production` has a `public-files` check. It fails when a file of `public/` is not in the web root, or when the file in the web root is different. Run `composer install` to correct it. See [Verification](./verification).

The check reads `public/` on the server, so the runtime image must contain it. A project without the directory passes.
