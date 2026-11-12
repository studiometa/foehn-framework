### Added

- The demo shows more of Føhn on its pages: an interactive block on the Interactivity API, blocks with the `gallery`, `posts` and `file` controls and with `parent`, a block pattern, the hero, section and callout blocks and the reading-time binding in seeded content, a journal with categories, tags and a password-protected post, project tags, a film embedded with `video_embed()`, `ContentImageHooks`, testimonial meta, `Model::query()` in a controller, a `#[AsTimberModel]` for attachments, a `#[AsTwigExtension]`, the twig-toolkit helpers, the query helpers, a `#[AsShortcode]`, `#[AsRestRoute]` routes, a tagged cache, an `#[AsAcfFieldGroup]` and the `AGENTS.md` block of `wp foehn agents-md`.
- The demo runs Action Scheduler. Saving a project dispatches a `#[AsJob]` that requests the image transforms of its plates, so the first visitor does not wait for them.
- The demo schedules two `#[AsCron]` jobs: one on a daily interval that warms the plates of every project, and one on the cron expression `0 0 1 1 *` that empties the page cache at midnight on 1 January. Its seeded site timezone is Europe/Paris, so the expression runs at midnight in Paris.
- The demo has two commands of its own under `wp app`: `projects:stats`, which only reads, and `projects:reorder`, which declares `writes: true` and so runs in production only with `--dry-run` or `--force`.
- The demo turns on `SecurityHeaders`, `DisableFileEditor`, `RestApiAuth` and `CleanContent`.
- The demo has a `foehn.local.config.php` that starts from `foehn.config.php` and turns on `debug`, `recordErrors` and `traceRequests`, and its footer prints the environment from `Env::get()`.
- The demo's page cache sets `cacheControl`, repeats the `SecurityHeaders` headers in `responseHeaders`, and warms the pages a purge removes with `warmAfterPurge` and `warmExclude`. `examples/page-cache.production.config.php` shows bucket storage, and the bucket smoke test runs that file and checks that editing a project purges its page.
- The demo has a `public/` directory with `robots.txt`, `favicon.ico` and `.well-known/security.txt`. Its Docker image copies it before `composer install` and into the final stage, so `wp foehn verify --profile=production` passes its `public-files` check. Its ddev nginx serves `/.well-known/`.
- The demo has a `db-export.config.php`, and its database dump is made with `wp foehn db:export`. `database/restore.sh` sets the admin password after the import, since an export keeps no password hash.

### Changed

- The demo requires `studiometa/foehn-acf` and the free ACF plugin. Its projects have a `#[AsAcfFieldGroup]` with the `ButtonLinkBuilder` fragment, and the release moves and checks the demo's `studiometa/foehn-acf` pin with the other ones.
- The demo's series archives are at `/series/<slug>/` and its project tag archives at `/project-tags/<slug>/`. `/projects/series/<slug>/` was a 404.
- After a deploy to Fly, the demo's workflow runs one pass of the real cron, warms the page cache with `wp foehn cache:warm --sync` and runs `wp foehn verify --profile=production`, which fails the job on a failed check.

### Fixed

- The `wp_interactive`, `wp_context`, `wp_directive`, `wp_bind`, `wp_on`, `wp_class` and `wp_text` Twig functions and the `video_embed`, `video_id`, `video_platform` and `video_is_supported` functions and filters are registered. Their extensions had no `#[AsTwigExtension]`, so a template that used them failed with "Unknown function".
- `Model::query()->get()` and `Model::all()` return a list of posts. They returned Timber's collection object, so PHP threw a `TypeError` on the `array` return type at every call.
- `wp_context()` and the `wp_context` filter encode `<`, `>` and `&` like WordPress does. A `>` in a value made `wptexturize` rewrite the rest of a block rendered in post content, and the block broke.
- The view script of a block with `interactivity: true` depends on `@wordpress/interactivity`, so WordPress puts that module in the import map. Before, `import { store } from '@wordpress/interactivity'` failed on a page with no core interactive block.
- The Twig extensions guide and the `foehn` skill name the twig-toolkit tag `{% html_element %}` … `{% end_html_element %}`. They said `{% element %}`, which Twig does not know.
- A command with `writes: true` that does not run in production names itself as WP-CLI registers it: `` `wp app import:products` `` for a project command and `` `wp foehn-acf make:acf-block` `` for a command of foehn-acf. It said `wp foehn <name>` for each command.
- The demo's footer links to a Legal page that exists, its banner and page-size settings change the site, and it removes the inline global styles like the starter.
