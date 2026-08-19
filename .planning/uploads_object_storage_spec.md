# Object storage for uploads

Uploads are the one part of a Føhn site that does not survive a deploy.

Everything else the framework generates is disposable: `web/wp/` comes from Composer, `web/wp-config.php` and the symlinks come from the installer, the discovery cache rebuilds itself on the first request. `web/wp-content/uploads/` is the exception — it is the only directory whose contents are neither in the repository nor reproducible, and the generated web root is exactly the shape people deploy to containers, where local disk does not persist between releases.

Nothing in the monorepo touches `upload_dir`. This has been recorded as a gap twice: [`editor_layer_spec.md`](editor_layer_spec.md) §9 lists it as phase 4, and [`research_responsive_images.md`](research_responsive_images.md) notes that offloaded uploads break local variant generation and need detecting.

| Property             | Decision                                                                                  |
| -------------------- | ----------------------------------------------------------------------------------------- |
| Model                | Copy after upload, not a stream wrapper. See §2.                                          |
| Dependency           | `async-aws/s3` (MIT, PHP ^8.2). See §3.                                                   |
| Where the code lives | A new `studiometa/foehn-uploads`, requiring `studiometa/foehn`.                           |
| Namespace            | `Studiometa\Foehn\Uploads\` — a sub-namespace this package owns alone. See §4.            |
| New attributes       | None. This is configuration, not discovery.                                               |
| Escape hatch         | `humanmade/s3-uploads` stays the answer for sites that need filesystem semantics. See §8. |
| Status               | **Undecided.** §11 lists what needs answering before this is worth approving.             |

## 1. What "offloading uploads" has to solve

WordPress treats uploads as files, in four distinct ways, and any offload has to answer all four:

1. **Writing.** `wp_handle_upload()` calls `move_uploaded_file()` into the directory `upload_dir` names.
2. **Deriving.** `wp_generate_attachment_metadata()` reads that file and writes sub-sizes beside it — which is where `#[AsImageSize]` ends up.
3. **Reading back.** The image editor, "Regenerate thumbnails" and a long tail of plugins call `get_attached_file()` and expect a path they can `fopen()`.
4. **Serving.** `wp_get_attachment_url()`, `wp_get_attachment_image_src()` and `wp_calculate_image_srcset()` build public URLs from the uploads base URL.

Only (4) is purely about URLs. The other three are about paths, and that is where every implementation gets its scars.

## 2. Two models, and why the cheap-looking one is the expensive one

### Model A — stream wrapper

Register an `s3://` PHP stream wrapper and point `upload_dir` at it. `move_uploaded_file()`, `fopen()`, `copy()` and `unlink()` then transparently speak to object storage. This is what `humanmade/s3-uploads` does.

It is elegant, and it is a large amount of load-bearing code: multipart uploads, seek semantics on a non-seekable transport, `rename` as copy-then-delete, `mkdir` against a store with no directories, `stat` caching, and the difference between "file does not exist" and "the network is having a bad day". Every one of those is a place where a bug loses a customer's media library.

**The decisive fact:** there is exactly one maintained S3 stream wrapper in PHP, `Aws\S3\StreamWrapper` in the AWS SDK. [`async-aws/s3` ships none](https://async-aws.com/clients/s3.html) — it is an API client, not a filesystem. So choosing model A means choosing the AWS SDK, and having chosen the AWS SDK and written the WordPress glue, we would have written `humanmade/s3-uploads`.

### Model B — copy after upload

Let WordPress write locally exactly as it does today. Once the attachment's metadata exists — original plus every sub-size — upload the set, rewrite the URLs WordPress serves, and optionally delete the local copies. Fetch a file back to local disk on the rare occasion something demands a real path. This is what WP Offload Media does.

It needs no stream wrapper, no `upload_dir` filter, and no filesystem emulation. It needs an API client, four or five filters, and a decision about local retention.

### Why B

|                                        | Model A — stream wrapper                         | Model B — copy after upload  |
| -------------------------------------- | ------------------------------------------------ | ---------------------------- |
| Code we own                            | A filesystem                                     | Five filters and an uploader |
| Dependency                             | `aws/aws-sdk-php`                                | `async-aws/s3`, MIT, ^8.2    |
| Sub-size generation (`#[AsImageSize]`) | Every intermediate write is a network round trip | Local, then one batch upload |
| Image editor, regenerate thumbnails    | Works, slowly                                    | Needs an explicit fetch-back |
| Failure mode of a bug                  | A write that silently did not happen             | A file that is still local   |
| Already exists, maintained             | **Yes**                                          | No                           |

The last row is the argument against building A at all, and the fourth row is the argument for B being genuinely different rather than a worse copy: **the WordPress image editor cannot use a remote path** — WP Offload Media downloads the file and hands back a local one precisely because of this. Under model B that case is the normal case, not the exception, because the file is local while it is being worked on.

Model B also composes with the responsive-images work rather than fighting it. That research flagged offloaded uploads as breaking local variant generation; under B, generation happens before the upload, on a real filesystem, and the variants offload with everything else.

## 3. The dependency

|                | `async-aws/s3`                                  | `aws/aws-sdk-php`                                                                                               |
| -------------- | ----------------------------------------------- | --------------------------------------------------------------------------------------------------------------- |
| Licence        | MIT                                             | Apache-2.0                                                                                                      |
| PHP            | ^8.2                                            | ^8.0                                                                                                            |
| Installed size | Small; `async-aws/core` and PHP extensions only | 37.5 MB / 3,749 files as installed                                                                              |
| Pruning        | Not needed                                      | `Composer::removeUnusedServices` gets a two-service install to 5.4 MB / 457 files, and S3 cannot be pruned away |
| Stream wrapper | No                                              | Yes                                                                                                             |

The AWS SDK's size is the folklore objection and it is weaker than it sounds — pruned to S3 it is a few megabytes. The real reason to prefer `async-aws/s3` is that under model B we never need the one thing the SDK has that it does not, and a 17.5-million-install MIT client with a small dependency tree is the smaller commitment.

## 4. Shape

A package, following the `studiometa/foehn-acf` precedent: the framework core gains no dependency, and a project opts in with one `composer require`.

Unlike the ACF package, this one takes a **sub-namespace of its own**, `Studiometa\Foehn\Uploads\`. The ACF split kept `Studiometa\Foehn\` so that existing projects changed no imports, and paid for it with two discovery locations answering to the same namespace and a `DiscoveryLocations::label()` to tell them apart. A package with no existing users has no such debt to service, and one package owning one namespace is what we would have chosen there too.

`extra.tempest.can-discover` is set from the first commit, for the reason [`post_meta_and_acf_split_spec.md`](post_meta_and_acf_split_spec.md) §3 gives.

### No new attribute

Nothing here is discovered. Offloading is a property of an environment, not of a class, and it is configured the way every other environment-shaped thing in Føhn is configured — a `*.config.php` read by `ConfigLoader`, with the environment suffix doing the work:

```php
<?php
// app/uploads.production.config.php

use Studiometa\Foehn\Uploads\UploadsConfig;

return new UploadsConfig(
    bucket: env('S3_BUCKET'),
    region: env('S3_REGION', 'auto'),
    endpoint: env('S3_ENDPOINT'),        // null for AWS; set for R2, Scaleway, MinIO
    prefix: 'uploads',
    publicUrl: env('S3_PUBLIC_URL'),      // the CDN or bucket origin URLs are rewritten to
    keepLocalCopy: false,
    pathStyle: false,
);
```

Credentials come from the environment (`AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`) and are never a constructor argument, so nothing puts them in a file that gets committed. The package ships `src/Config/uploads.config.php` returning a disabled default, so a site that installs it and configures nothing behaves exactly as it did before.

### Where it hooks

| Concern           | Hook                                     | Note                                                                |
| ----------------- | ---------------------------------------- | ------------------------------------------------------------------- |
| Upload            | `wp_generate_attachment_metadata` (late) | The one point where the original and every sub-size are known       |
| Upload, non-image | `add_attachment`                         | Images never reach it; PDFs and video do                            |
| Serving           | `wp_get_attachment_url`                  | The single URL                                                      |
| Serving           | `wp_calculate_image_srcset`              | Builds its own URLs from the uploads base; not covered by the above |
| Serving           | `wp_get_attachment_image_src`            | Same                                                                |
| Reading back      | `get_attached_file`                      | Fetch to a temp path when the local copy is gone                    |
| Deletion          | `delete_attachment`, `wp_delete_file`    | Remove the remote objects too                                       |

**The serving set is empirical and has to be pinned during implementation, not assumed.** Three filters are listed because core builds media URLs in more than one place; the test that matters is a page containing a `srcset`, rendered and diffed, not a unit test of one filter.

### CLI

- `wp foehn uploads:migrate` — walk the media library, upload what is missing, with `--dry-run`.
- `wp foehn uploads:verify` — credentials, bucket reachability, a round-trip write and delete, and whether the public URL actually serves what was written. The last of those is the check that catches a misconfigured CDN, which is the failure people spend an afternoon on.
- `wp foehn uploads:status` — how many attachments are offloaded, how many are not.

## 5. Out of scope for v1

- **Private or signed URLs.** Everything served is public. A media library with access control is a different feature with a different threat model.
- **Multisite.**
- **Two-way sync.** Local is the working copy, remote is the truth, and nothing watches the bucket for changes made elsewhere.
- **Serving through the framework.** URLs point at the bucket or a CDN. Føhn does not proxy bytes.
- **Filesystem semantics for third-party code.** A plugin that insists on `WP_CONTENT_DIR . '/uploads'` being real is the case §8 exists for.

## 6. Interaction with the rest of the roadmap

**Page cache (item 1).** Cached HTML contains rewritten media URLs, so changing `publicUrl` invalidates every cached page. The page cache's full-flush trigger list should gain the uploads config, alongside the `updated_option` allowlist it already has. One line there, and it belongs to whoever lands second.

**Responsive images.** The research doc's "offloaded uploads break local file generation" risk is the reason model B was chosen; that note can be closed rather than mitigated. Variants generated at upload time offload with the original. Variants generated lazily at render time would not, and that is an argument for generating at upload time that has nothing to do with S3.

**`#[AsImageSize]`.** Unchanged. Sub-sizes are produced before the upload hook fires and travel with it.

**The installer.** `.env.example` gains the five variables, commented out. `WebRootGenerator` needs no change: nothing about this touches the web root, which is the point.

## 7. Testing

Unit tests over the WordPress stubs cover the filters and the metadata walk, and prove nothing about S3 — the same limitation that made the smoke suite necessary in the first place.

What earns confidence is `packages/demo`: an offload configured against **MinIO in a ddev service**, a real upload through `wp media import`, and assertions that the object exists in the bucket, that the local copy is gone when `keepLocalCopy` is false, that the rendered page's `srcset` points at the public URL, and that deleting the attachment deletes the objects. MinIO in ddev keeps that hermetic — no credentials in CI, no network flakiness, no bill.

Every assertion checked to fail with the feature removed, as with items 2 through 10.

## 8. What we are not replacing

[`humanmade/s3-uploads`](https://github.com/humanmade/S3-Uploads) — 3.0.13, February 2026, GPL-2.0+, 2.5 million installs, actively maintained — remains the right answer for a site that needs uploads to _be_ a filesystem: a plugin that writes into the uploads directory directly, a workflow that treats `WP_CONTENT_DIR` as real. It supports MinIO, Ceph, DigitalOcean Spaces and Scaleway through an `s3_uploads_s3_client_params` filter, and ships `verify`, `ls`, `cp` and `upload-directory` commands.

Føhn should document it as the alternative, in the same breath as this package, and never pretend to be a drop-in replacement for it. The honest framing is a choice between two models, not a better implementation of one.

It is GPL-2.0+ against Føhn's MIT. In a WordPress context that is unremarkable — the surrounding stack is GPL — but it is a reason to document the plugin rather than to `require` it from an MIT package.

## 9. Risks

- **The serving filter set is discovered, not designed.** Core builds media URLs in several places and plugins add more. A `srcset` that half points at S3 is the likely first bug. Mitigated by testing rendered pages rather than filters.
- **Eventual offload.** Between `wp_handle_upload` and the upload hook the file exists only locally. On a multi-container deploy the next request may land elsewhere and 404 an image that was uploaded seconds ago. Either offload synchronously on the request that created the attachment, accepting the latency, or accept a window and say so. **This is the decision that most shapes the implementation and it is not yet made.**
- **`keepLocalCopy: false` makes deletion destructive.** A misconfigured bucket that silently accepts writes and serves nothing turns into data loss on the next deploy. `uploads:verify` checking that the public URL serves what was just written is the guard, and it should run before anything is deleted.
- **S3-compatible is a spectrum.** R2, Scaleway and MinIO each differ in path-style addressing, checksum headers and multipart minimums — `humanmade/s3-uploads` documents having to disable AWS SDK 3.337+ checksums for third-party APIs. Pick the providers we support, test them, and say which they are.
- **The image editor's fetch-back is a cache with no eviction.** Downloading originals to local disk to satisfy `get_attached_file` reintroduces the problem this feature exists to solve, in a smaller form. Temp files, cleaned on shutdown.

## 10. Phases and estimates

| Phase | Work                                                                                | Estimate |
| ----- | ----------------------------------------------------------------------------------- | -------- |
| 1     | Package skeleton, `UploadsConfig`, the client, upload on metadata, `uploads:verify` | 2 d      |
| 2     | URL rewriting across the serving filters, with a rendered-page test                 | 1–2 d    |
| 3     | Fetch-back, deletion, `uploads:migrate`, `uploads:status`                           | 2 d      |
| 4     | MinIO in the demo's ddev, smoke assertions, provider matrix, documentation          | 1–2 d    |

**6 to 8 days**, which is more than the estimate I gave verbally and less than model A would cost.

## 11. Open questions

These are why this is undecided rather than approved.

1. **Is the demand real, or anticipated?** Every item on the roadmap so far closed a gap someone had hit. This one closes a gap someone will hit _if_ Føhn sites are deployed to ephemeral infrastructure. If the projects in flight deploy to a VPS with persistent disk, this is a solution waiting for its problem, and `humanmade/s3-uploads` covers the case that arrives first.
2. **Synchronous or deferred offload?** See §9. Synchronous is simpler and correct and makes uploading a large video slow. Deferred needs `JobDispatcher` and a window during which the file is local-only.
3. **Which providers?** R2 and Scaleway are the plausible ones for our projects; AWS is the one everyone tests against. Each added provider is a row in a test matrix somebody maintains.
4. **Does this belong before or after a `0.5.0` release?** It is additive and lives in its own package, so it does not block a tag. Item 12 remains waiting on the page cache either way.
