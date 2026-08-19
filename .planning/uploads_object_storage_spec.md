# Object storage for uploads

Uploads are the one part of a Føhn site that does not survive a deploy.

Everything else the framework generates is disposable: `web/wp/` comes from Composer, `web/wp-config.php` and the symlinks come from the installer, the discovery cache rebuilds itself on the first request. `web/wp-content/uploads/` is the exception — it is the only directory whose contents are neither in the repository nor reproducible, and the generated web root is exactly the shape people deploy to containers, where local disk does not persist between releases.

Nothing in the monorepo touches `upload_dir`. This has been recorded as a gap twice: [`editor_layer_spec.md`](editor_layer_spec.md) §9 lists it as phase 4, and [`research_responsive_images.md`](research_responsive_images.md) notes that offloaded uploads break local variant generation and need detecting.

> **Revised 2026-08-20.** The first draft of this spec proposed building `studiometa/foehn-uploads` on `async-aws/s3`, at 6–8 days. That recommendation is withdrawn. Checking what the installer already generates showed that the plugin's only documented prerequisite is already met and most of the claimed ergonomic advantages are already available for free. §2 is the current recommendation; §5 keeps the build-it analysis for the day something makes it necessary.

| Property                             | Decision                                                                                                            |
| ------------------------------------ | ------------------------------------------------------------------------------------------------------------------- |
| Approach                             | Integrate [`humanmade/s3-uploads`](https://github.com/humanmade/S3-Uploads). Do not implement an offloader. See §2. |
| Code Føhn owns                       | The constants block in the generated `wp-config.php`, one opt-in hook class, `.env.example`, docs, one smoke test.  |
| New package                          | None.                                                                                                               |
| New dependency in `studiometa/foehn` | None. The plugin is required by `foehn-starter` and `foehn-demo`, not by the MIT core. See §3.4.                    |
| New attributes                       | None. This is configuration, not discovery.                                                                         |
| Estimate                             | ~1 day, against 6–8 for building it. See §10.                                                                       |
| Status                               | **Proposed.** Smaller than the version that was undecided; §11 is what is left to answer.                           |

## 1. What "offloading uploads" has to solve

WordPress treats uploads as files, in four distinct ways, and any offload has to answer all four:

1. **Writing.** `wp_handle_upload()` calls `move_uploaded_file()` into the directory `upload_dir` names.
2. **Deriving.** `wp_generate_attachment_metadata()` reads that file and writes sub-sizes beside it — which is where `#[AsImageSize]` ends up.
3. **Reading back.** The image editor, "Regenerate thumbnails" and a long tail of plugins call `get_attached_file()` and expect a path they can `fopen()`.
4. **Serving.** `wp_get_attachment_url()`, `wp_get_attachment_image_src()` and `wp_calculate_image_srcset()` build public URLs from the uploads base URL.

Only (4) is purely about URLs. The other three are about paths, and that is where every implementation gets its scars. `humanmade/s3-uploads` answers all four with a stream wrapper, has answered them for 2.5 million installs, and is still shipping releases — 3.0.13 in February 2026.

## 2. Recommendation: integrate, do not implement

Two facts, both checked against the code rather than assumed, decide this.

**The plugin's only documented prerequisite is already satisfied.** Its README requires the Composer autoloader to be loaded before the plugin, in `wp-config.php`. `WebRootGenerator` already writes exactly that:

```php
// Load Composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';
```

**And it is configured entirely through constants, in the file Føhn generates.** `S3_UPLOADS_BUCKET`, `S3_UPLOADS_REGION`, `S3_UPLOADS_KEY`, `S3_UPLOADS_SECRET`, `S3_UPLOADS_BUCKET_URL`, plus `S3_UPLOADS_USE_INSTANCE_PROFILE`, `S3_UPLOADS_OBJECT_ACL`, `S3_UPLOADS_HTTP_CACHE_CONTROL` and `S3_UPLOADS_USE_LOCAL`. That generated `wp-config.php` already loads `.env` through `vlucas/phpdotenv` and already defines every other environment-shaped constant through an `$env()` helper:

```php
define('WP_HOME', $env('WP_HOME', 'http://localhost'));
```

Five more `define()` calls in that block, guarded so a site with no bucket is untouched, is the whole configuration story. `composer/installers` already routes `type:wordpress-plugin` to `web/wp-content/plugins/{$name}/` in the starter's `installer-paths`, so the plugin lands in the right place with no packaging work at all.

The goal — _a Føhn site is deployable where local disk does not persist, and that is proven in CI_ — is reached by wiring, not by writing an upload pipeline.

### Why not the existing config files

The generated `wp-config.php` already loads `config/wordpress.config.php` and `config/wordpress.{env}.config.php`, which looks like the natural home for these constants and is not. **Those files are loaded before `.env`**, so a `define()` there reading `$_ENV` sees only real environment variables. That happens to work in a container and silently fails in ddev, which is the worst possible split. The constants belong in the installer's own block, after dotenv, where every other constant already is.

This is worth fixing on its own merits — project config that cannot read the project's `.env` is a trap regardless of S3 — but it is a separate change and it is not a prerequisite for this one.

## 3. The work

### 3.1 Installer

`WebRootGenerator` gains a guarded block after the existing defines:

```php
// Object storage for uploads (humanmade/s3-uploads), when a bucket is configured
if ($env('S3_UPLOADS_BUCKET')) {
    define('S3_UPLOADS_BUCKET', $env('S3_UPLOADS_BUCKET'));
    define('S3_UPLOADS_REGION', $env('S3_UPLOADS_REGION', 'auto'));
    define('S3_UPLOADS_KEY', $env('AWS_ACCESS_KEY_ID'));
    define('S3_UPLOADS_SECRET', $env('AWS_SECRET_ACCESS_KEY'));
    define('S3_UPLOADS_BUCKET_URL', $env('S3_UPLOADS_BUCKET_URL'));
}
```

`.env.example` gains the same five, commented out, with a line saying what happens when they are absent: nothing.

Credentials are read from the environment and never written to a file the installer generates, which is already true of everything else in that block.

### 3.2 One opt-in hook class

Custom endpoints — R2, Scaleway, MinIO — are the plugin's `s3_uploads_s3_client_params` filter, which its documentation tells you to write in an mu-plugin. Føhn already has the mechanism for exactly this shape of thing: opt-in hook classes listed in `foehn.config.php`, next to `YouTubeNoCookieHooks`, `DisableXmlRpc` and the rest.

So `Studiometa\Foehn\Hooks\S3UploadsEndpoint` reads `S3_UPLOADS_ENDPOINT` and `S3_UPLOADS_PATH_STYLE` from the environment and returns them to that filter, and no-ops when the plugin is absent. That is the entire class, and it is the one place where Føhn genuinely makes the plugin nicer to use than the plugin.

### 3.3 Proof in the demo

MinIO as a ddev service in `packages/demo`, a real `wp media import`, and smoke assertions that the object exists in the bucket and that the rendered page's `srcset` points at the bucket URL. MinIO in ddev keeps it hermetic: no credentials in CI, no network flakiness, no bill.

Every assertion checked to fail with the feature removed, as with items 2 through 10.

### 3.4 Where the requirement goes

`humanmade/s3-uploads` is required by `studiometa/foehn-starter` and `studiometa/foehn-demo`, **not** by `studiometa/foehn`. That is the right dependency direction anyway — the framework should not force an S3 client on a site that has no bucket — and it disposes of the licence question entirely, since a GPL-2.0+ plugin sitting in a project scaffold alongside WordPress itself is unremarkable, while requiring one from an MIT library would not be.

## 4. What owning it would have added, honestly

The first draft justified a package on four grounds. Held against what the plugin and the installer already do, three of them fail:

| Claimed advantage of owning it                   | What it is actually worth                                                                                                               |
| ------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------- |
| Typed `uploads.config.php` instead of `define()` | The installer _generates_ the `wp-config.php`. Constants from `.env` is the same outcome for the user, minus a package.                 |
| Installer wiring and `.env.example`              | Identical either way. This was never an argument for owning the code.                                                                   |
| `wp foehn uploads:migrate` / `verify` / `status` | The plugin ships `verify`, `ls`, `cp`, `upload-directory`, `enable`, `disable`. Ours would be a worse subset under a second vocabulary. |
| Provider support for R2 and Scaleway             | One filter, which fits the existing opt-in hooks mechanism. §3.2. This one survives, at about twenty lines.                             |

Two arguments for owning it are real, and neither is worth six days today:

- **A plugin can be deactivated.** Someone with admin access can break the media library from a WordPress screen. Force-loading it from the mu-plugins directory the installer already symlinks would close this if it ever matters.
- **A stream wrapper makes every intermediate sub-size write a network round trip.** Real, measurable on upload, and invisible to page visitors.

## 5. What would justify building it

The one place owning the pipeline genuinely wins is image processing, and it is worth keeping the analysis because the trigger is plausible.

Under a stream wrapper, WordPress never has the file locally, so `#[AsImageSize]` sub-size generation writes each variant over the network, and the image editor works only because the plugin fetches originals back down. The alternative — **copy after upload**: let WordPress write locally exactly as it does today, and once `wp_generate_attachment_metadata` has produced the original and every sub-size, upload the set, rewrite the URLs and optionally delete the local copies. This is what WP Offload Media does. It needs no stream wrapper, no `upload_dir` filter and no filesystem emulation: an API client, five filters and a decision about local retention. `async-aws/s3` (MIT, ^8.2, 17.5 M installs) would be the client, since under this model the AWS SDK's stream wrapper — the one thing it has that async-aws lacks — is exactly what is not needed.

It closes the [`research_responsive_images.md`](research_responsive_images.md) risk rather than mitigating it: variants get generated before the upload, on a real filesystem, and travel with the original.

**Build it when, and only when, all three hold:**

1. Føhn generates its own image variants, so controlling when files are local stops being theoretical.
2. Sub-size generation over the network is measured to be a problem on a real site, not predicted to be one.
3. More than one project is running offloaded uploads, so the maintenance has somewhere to amortise.

Until then, detection is `defined('S3_UPLOADS_BUCKET')` and the responsive-images work falls back, which is what that research already planned to do.

## 6. Out of scope

- **Private or signed URLs.** Everything served is public.
- **Multisite.**
- **Two-way sync.** Nothing watches the bucket for changes made elsewhere.
- **Serving through the framework.** URLs point at the bucket or a CDN. Føhn does not proxy bytes.
- **A migration path off the plugin.** If §5 ever fires, that is part of §5's cost, not a thing to design for now.

## 7. Interaction with the rest of the roadmap

**Page cache (item 1).** Cached HTML contains media URLs, so changing `S3_UPLOADS_BUCKET_URL` makes every cached page stale. Since these are constants rather than options, no `updated_option` hook fires and the page cache cannot detect it — a deploy that changes the CDN needs a cache flush, which is a documentation line, not code. Worth saying out loud in the page cache's docs, because it is the kind of thing that produces a bug report about "images broken after deploy".

**Responsive images.** See §5. The research doc's risk stands, and its planned fallback is correct.

**`#[AsImageSize]`.** Unchanged in behaviour, slower per upload under the stream wrapper.

**The installer.** §3.1 is the only change, and it touches the constants block, not the web root.

## 8. Testing

Unit tests over the WordPress stubs would cover the guard in the generated `wp-config.php` and the endpoint hook, and would prove nothing about S3 — the same limitation that made the smoke suite necessary in the first place. `WebRootGeneratorTest` gets the guarded-block assertions, because that is generated output and generated output is testable.

Everything else is §3.3: MinIO, a real import, a rendered page.

## 9. Risks

- **We are trusting a third party with the media library.** Mitigated by it being the most-installed answer to this problem in WordPress, actively maintained, with a stream wrapper that has survived far more traffic than ours would.
- **`S3_UPLOADS_USE_LOCAL` exists, and someone will ship it to production.** Test that the demo's production-shaped configuration does not set it.
- **S3-compatible is a spectrum.** R2, Scaleway and MinIO differ in path-style addressing, checksum headers and multipart minimums; the plugin documents having to disable AWS SDK 3.337+ checksums for third-party APIs. Pick the providers we claim to support, test them, say which they are.
- **A misconfigured bucket that accepts writes and serves nothing is data loss.** `wp s3-uploads verify` before trusting it — the plugin's command, not one we write.
- **The AWS SDK lands in every project that requires the plugin.** ~5.4 MB pruned to two services. Acceptable; noted so nobody rediscovers it as a surprise.

## 10. Phases and estimate

| Phase | Work                                                                      | Estimate |
| ----- | ------------------------------------------------------------------------- | -------- |
| 1     | Installer constants block, `.env.example`, `WebRootGeneratorTest`         | 2 h      |
| 2     | `S3UploadsEndpoint` opt-in hook class, with tests                         | 2 h      |
| 3     | MinIO in the demo's ddev, smoke assertions, provider notes, documentation | 4 h      |

**About a day**, against 6 to 8 for building it.

## 11. Open questions

1. **Is the demand real, or anticipated?** Unchanged from the first draft and still the question that matters most — every roadmap item so far closed a gap someone had hit. At a day's work the answer matters less than it did at six, and phases 1 and 2 are worth doing on speculation in a way that a package never was.
2. **Which providers do we claim?** R2 and Scaleway are the plausible ones for our projects; AWS is the one everyone tests against. Each is a row in a matrix somebody maintains.
3. **Before or after `0.5.0`?** Phase 1 touches the installer, which is released with the framework, so it wants to be in a tag rather than trailing one. It does not block anything.
