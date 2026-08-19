# Uploads and Object Storage

`web/` is generated and `web/wp/` comes from Composer, so a deploy can throw the whole document root away and rebuild it. `web/wp-content/uploads/` is the exception: it is the only directory holding something neither the repository nor Composer can reproduce.

On a VPS with a persistent disk that does not matter. On anything that replaces the container on release — a PaaS, Kubernetes, a scale-to-zero host — every image uploaded since the last deploy goes with it.

Føhn does not implement the offload. [`humanmade/s3-uploads`](https://github.com/humanmade/S3-Uploads) does, and has done for 2.5 million installs; what Føhn adds is the configuration, from `.env` like everything else about a deploy.

## Setup

```bash
composer require humanmade/s3-uploads
wp plugin activate s3-uploads
```

Then in `.env`:

```dotenv
S3_UPLOADS_BUCKET=my-project-media
S3_UPLOADS_REGION=eu-west-3
S3_UPLOADS_BUCKET_URL=https://media.example.com
AWS_ACCESS_KEY_ID=…
AWS_SECRET_ACCESS_KEY=…
```

The generated `wp-config.php` defines the `S3_UPLOADS_*` constants the plugin reads **only when `S3_UPLOADS_BUCKET` is set**. Leave it out and nothing is defined at all — uploads stay on local disk whether or not the plugin is installed, which is what makes a development environment work with the same `wp-config.php` as production.

Nothing is written to `.env` for you. Credentials come from the environment, and on a platform that supplies real environment variables you need no `.env` at all.

### Instance profiles

Leave `AWS_ACCESS_KEY_ID` and `AWS_SECRET_ACCESS_KEY` out and neither constant is defined, which is what lets the AWS SDK fall back to an IAM instance profile. Add `S3_UPLOADS_USE_INSTANCE_PROFILE` to `config/wordpress.config.php` to make that explicit.

### Serving from a CDN

`S3_UPLOADS_BUCKET_URL` is what appears in the page — `<img src>`, every `srcset` candidate, every `wp_get_attachment_url()`. Point it at the CDN and the bucket never takes front-end traffic.

Leave it out and the plugin derives an AWS bucket URL, which is right for AWS and wrong for everything else.

## Anything that is not AWS

R2, Scaleway, DigitalOcean Spaces, Ceph and MinIO all speak the S3 API at an endpoint of their own, and the plugin's constants have no way to say so — its documentation tells you to write the filter yourself, in an mu-plugin. Føhn ships that filter as an opt-in hook class:

```php
<?php
// app/foehn.config.php

use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Hooks\S3UploadsEndpoint;

return new FoehnConfig(hooks: [S3UploadsEndpoint::class]);
```

```dotenv
S3_UPLOADS_ENDPOINT=https://s3.fr-par.scw.cloud
S3_UPLOADS_PATH_STYLE=false
S3_UPLOADS_CHECKSUMS=true
```

| Variable                | What it decides                                                                                                             |
| ----------------------- | --------------------------------------------------------------------------------------------------------------------------- |
| `S3_UPLOADS_ENDPOINT`   | The API endpoint. Nothing else here applies without it, and without it the plugin talks to AWS.                             |
| `S3_UPLOADS_PATH_STYLE` | Bucket in the path (`endpoint/bucket/key`) rather than in the hostname. MinIO and Ceph want `true`; R2 and Scaleway do not. |
| `S3_UPLOADS_CHECKSUMS`  | Set `false` against a provider that rejects the integrity headers AWS SDK 3.337 made the default.                           |

Getting `S3_UPLOADS_CHECKSUMS` wrong shows up as uploads failing with an error that never mentions checksums, so it is worth trying first when a provider that should work does not.

## Verifying

```bash
wp s3-uploads verify
```

That checks the credentials and a round trip. It does **not** check that `S3_UPLOADS_BUCKET_URL` serves what was written, and that is the failure that costs an afternoon: the media library looks perfect and every image on the site 404s. Upload something and request its URL:

```bash
curl -I "$(wp eval 'echo wp_get_attachment_url(ID);')"
```

A `403` on a bucket that accepted the write usually means the bucket policy denies anonymous reads. Object ACLs are not enough on their own on most S3-compatible providers — see `packages/demo/tests/smoke/provision-bucket.php` for the policy the demo applies to MinIO.

## Migrating an existing library

```bash
wp s3-uploads upload-directory /path/to/uploads/ uploads
wp s3-uploads ls
```

Both come from the plugin. Do not delete the local copies until a request for a public URL returns the bytes.

## What this does not do

**It is a stream wrapper**, so `wp-content/uploads` stops being a real directory. Code that writes into it with `fopen()` and expects `fseek()` to behave keeps working, over the network, slowly. Code that builds a path by hand from `WP_CONTENT_DIR` does not.

Every intermediate sub-size write is a network round trip, `#[AsImageSize]` sizes included. Correct, and slower per upload than writing them locally.

**It is a plugin**, so someone with admin access can deactivate it and put uploads back on local disk without touching a deploy.

**Private files are out of scope.** Everything served is public. A media library with access control is a different feature with a different threat model.

## Seeing it work

`packages/demo` runs the whole path against MinIO in a ddev service: a real upload, the original and every sub-size in the bucket, nothing left on local disk, and a request to the public URL that returns the bytes. `packages/demo/tests/smoke/run.sh` is the shortest description of what "working" means here.
