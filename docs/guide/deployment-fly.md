# Deploying to Fly.io

[The WordPress runtime image](./docker-image) describes what a project inherits. This page describes one project that inherits it and is actually running: **<https://foehn-demo.fly.dev>** is `packages/demo`, on Fly, as one app with its own database and a bucket for the photographs.

It is the worked example rather than a template. Everything below is in the repository and readable there — [`packages/demo/Dockerfile`](https://github.com/studiometa/foehn-framework/blob/main/packages/demo/Dockerfile), [`packages/demo/fly.toml`](https://github.com/studiometa/foehn-framework/blob/main/packages/demo/fly.toml) and [`packages/demo/docker/entrypoint.d/35-demo-seed.sh`](https://github.com/studiometa/foehn-framework/blob/main/packages/demo/docker/entrypoint.d/35-demo-seed.sh) each carry the reasoning for the lines they contain, and a copy on this page would drift away from them. Read the files; this page says why they are shaped that way and what it cost to find out.

## What the site is made of

| Resource        | What                                                    | Why it exists                                                                      |
| --------------- | ------------------------------------------------------- | ---------------------------------------------------------------------------------- |
| One app         | `foehn-demo`, one machine, `shared-cpu-1x` with 1 GB    | The `-db` image variant puts MariaDB beside PHP, so there is no second app         |
| One volume      | `data`, 1 GB, mounted at `/var/lib/mysql`               | MariaDB's data directory, and the whole of what the site keeps on disk             |
| One bucket      | `foehn-demo-media` on Tigris, public read               | `web/wp-content/uploads/` is the one directory a generated web root cannot rebuild |
| Sixteen secrets | database, admin, bucket credentials, the eight WP salts | Everything a leak would matter for. Everything else is `[env]` in `fly.toml`       |

The machine builds the site itself. A boot that finds an empty volume installs WordPress, runs `database/seed.php`, restores the thirty photographs and flushes the rewrite rules; a boot that finds a volume already holding the portfolio does nothing. There is no import step, no first-run wizard and no state anybody has to carry from one deploy to the next.

## Creating it

Five commands, in this order, and the order is the whole of it — each one produces something the next needs.

```sh
fly apps create foehn-demo --org ikko

# Tigris, public read. It sets AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY,
# AWS_REGION, AWS_ENDPOINT_URL_S3 and BUCKET_NAME as secrets on the app itself.
fly storage create -a foehn-demo -n foehn-demo-media -p -y

# --stage, because there is no machine yet to restart and an unstaged set would
# try to deploy an app that has no image.
fly secrets set -a foehn-demo --stage \
  S3_UPLOADS_BUCKET=foehn-demo-media \
  DB_PASSWORD=… \
  DEMO_ADMIN_PASSWORD=… \
  AUTH_KEY=… SECURE_AUTH_KEY=… LOGGED_IN_KEY=… NONCE_KEY=… \
  AUTH_SALT=… SECURE_AUTH_SALT=… LOGGED_IN_SALT=… NONCE_SALT=…

fly volumes create data --size 1 --region cdg --snapshot-retention 14 -a foehn-demo -y

fly deploy --ha=false
```

**The volume before the deploy.** `fly.toml` has a `[[mounts]]` block, and a machine whose mount names a volume that does not exist is a machine that does not start. `--snapshot-retention 14` because Fly keeps volume snapshots for five days by default, which is short for the only physical copy of anything.

**The secrets before the first boot.** The entrypoint refuses to start the container while any of the eight WordPress security keys is missing, on purpose: `wp-config.php` answers a production request with an HTTP 500 while they are unset and exempts WP-CLI from that check, so every seeding step would succeed, `/healthcheck` — which PHP-FPM answers and which says nothing about WordPress — would pass, and the deploy would go green over a site where every page is a 500. Failing the boot instead fails the deploy, and Fly leaves the machine already serving in place.

**`--ha=false`.** Fly's default is two machines. The second one has no volume, so on a site with `[[mounts]]` it is not a spare, it is a machine that cannot start a database.

The generated `wp-config.php` reads all eight from the environment, so nothing writes them into a file: <https://api.wordpress.org/secret-key/1.1/salt/> prints a fresh set, and `openssl rand -base64 48` eight times does as well.

## Secrets, and what is not one

The rule the demo follows: a value printed on every page of the site is not a secret, and putting it in `fly.toml` is what makes the app reproducible by somebody who only has this repository.

So `WP_HOME`, `DB_NAME`, `DB_USER` and the `S3_UPLOADS_*` switches live in `[env]`, where they are read, reviewed and changed in a commit. `WP_HOME` in particular: WordPress writes that origin into every permalink, menu item and guid the seed creates, so it is the one value to change first when the demo moves to a domain of its own.

The sixteen secrets:

| Secret                                                                                       | Set by                         | Read as                                                               |
| -------------------------------------------------------------------------------------------- | ------------------------------ | --------------------------------------------------------------------- |
| `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`                                                 | `fly storage create`           | `S3_UPLOADS_KEY` and `S3_UPLOADS_SECRET` in the generated wp-config   |
| `AWS_REGION`, `AWS_ENDPOINT_URL_S3`, `BUCKET_NAME`                                           | `fly storage create`           | Fly's own naming. The site reads its `S3_UPLOADS_*` pair instead      |
| `S3_UPLOADS_BUCKET`                                                                          | by hand, copying `BUCKET_NAME` | The bucket, by the name [`humanmade/s3-uploads`](./uploads) reads     |
| `DB_PASSWORD`                                                                                | by hand                        | The `-db` variant creates the database and the user from it           |
| `DEMO_ADMIN_PASSWORD`                                                                        | by hand                        | `wp core install`. Without it the site is left uninstalled            |
| `AUTH_KEY`, `SECURE_AUTH_KEY`, `LOGGED_IN_KEY`, `NONCE_KEY` and their four matching `_SALT`s | by hand                        | WordPress. A site with guessable keys is a site with forgeable logins |

`fly storage create` does five of them by itself. The only one that has to be typed twice is `S3_UPLOADS_BUCKET`, which holds the same string as `BUCKET_NAME` under the name the plugin actually looks for.

## Backups are off here

`BACKUP_ENABLED` is not set, so no dump is scheduled. That is a decision about this site and not a default worth copying: the demo's content is reproducible from the repository — `database/seed.php` writes it and the photographs are committed — so the worst a lost volume costs is one boot spent seeding. The fourteen days of volume snapshots cover the machine layer.

For a site whose database is the only copy of anything, turn them on: [Database backups](./docker-image#database-backups) is one `BACKUP_ENABLED=true` and a second bucket, and the guide's own warning applies — the media bucket is public and a database dump must never share it.

## Two failures the first deployment found

Neither was guessable from the configuration, and both cost real time.

### Tigris refuses the SDK's integrity headers, and says something else

AWS SDK 3.337 began sending integrity headers by default. Tigris rejects them, and rejects them **only on streamed writes** — which is exactly the shape that makes the symptom useless:

```sh
wp s3-uploads verify        # passes. It puts a small string.
```

Sideloading a photograph fails instead, and fails three times over on its way out:

```
Stream is not seekable
Failed to open stream: "S3_Uploads\Stream_Wrapper::stream_open" call failed
Error: could not import corridors-01-….jpg: Sorry, you are not allowed to upload this file type
```

The last line is the one that reaches you, and it is about the file type, for a problem that has nothing to do with the file type. The object never lands; WordPress then reads back the temp file the plugin moved into the bucket, finds nothing there, and reports the only failure it has a message for.

The fix is one variable:

```toml
[env]
  S3_UPLOADS_CHECKSUMS = 'false'
```

It is read by [`Studiometa\Foehn\Hooks\S3UploadsEndpoint`](./uploads#anything-that-is-not-aws), which the demo opts into from `theme/app/foehn.config.php`. MinIO rejects the headers too, which is why `packages/demo/.env.example` already carried the same line for the ddev environment — the deployment simply met the same wall on a different provider. **AWS itself needs the checksums left on**, so this is not a setting to turn off everywhere.

### A green deploy over a site that never finished building itself

The seeding script first asked `wp core is-installed` before deciding whether to build the portfolio. That is the wrong question, and the difference is a state the first real deployment reached: `wp core install` succeeded, a later step failed, the machine restarted — and `core is-installed` was now true on a site with no content. Every boot after that skipped the content steps. The container came up healthy, `/healthcheck` answered, and Fly reported a successful deploy of a portfolio site with no portfolio.

The guard is now a marker option, written after the **last** step and nowhere else:

```sh
wp option get foehn_demo_seeded          # "did this finish", not "is there a WordPress"
wp option update foehn_demo_seeded 1 --autoload=no
```

Installed-but-unmarked is treated as the interrupted case it is, and the steps run again — they are idempotent, finding their posts by slug and their photographs by Unsplash id, so recovering costs a boot rather than a clean volume somebody has to produce by hand.

This one generalises past the demo. **Any container that provisions itself needs its guard on the last step of provisioning, not the first**, and a health check that only proves PHP answers will never catch the difference. The runtime image's `/healthcheck` is answered by PHP-FPM by design — it tells you PHP is up, and it is not a statement about WordPress.

## Redeploying

`.github/workflows/deploy-demo.yml` deploys the demo after a release, and `workflow_dispatch` runs it by hand. It needs one repository secret, `FLY_API_TOKEN` — an app-scoped deploy token, so it can deploy this app and nothing else.

The ordering is the interesting part. `packages/demo/Dockerfile` builds `FROM ghcr.io/studiometa/foehn-wordpress:<version>-db`, and that image is published by `docker.yml` on the same tag push. A deploy triggered directly by the tag would race an image that does not exist yet, so the workflow waits on `workflow_run` instead: it starts when the Docker workflow has finished successfully, checks out the commit that run built, and passes the tag through as a build argument. The pinned `ARG FOEHN_IMAGE` in the Dockerfile stays what a local `docker build` and a manual dispatch get.

```sh
# By hand, from packages/demo, with flyctl authenticated:
fly deploy --ha=false
```

A deploy replaces the machine's filesystem and restarts MariaDB with it. The volume survives, so the site comes back with its content and the seeding script does nothing; `kill_timeout = '30s'` is what gives InnoDB time to finish flushing, and cutting it short moves that work to the next boot as crash recovery — measured on this image, a 1 s grace period produced `Starting crash recovery from checkpoint LSN=…` on the following start and a 30 s one did not.

## Checking it

```sh
curl -sI https://foehn-demo.fly.dev/ | grep -i x-foehn-cache
# x-foehn-cache: HIT
# x-foehn-cache-via: nginx

curl -s -o /dev/null -w '%{http_code}\n' https://foehn-demo.fly.dev/_health
# 200 — the site's own #[AsRewriteRule], which only answers once the rules are flushed
```

`x-foehn-cache-via: nginx` is the one worth reading. It says the [page cache](./page-cache) rules were generated at boot and PHP is not starting for that request; `drop-in` there would mean generating them failed and the site fell back to `advanced-cache.php`, which serves the same HTML a few milliseconds slower.

`fly logs -a foehn-demo` shows the seeding decision on every boot, in one line either way — the volume already holds the portfolio, or an empty volume is being built and it takes about half a minute.

## What this sizing is and is not

One shared CPU and 1 GB, because PHP and a database in one container do not fit the preset's own 256 MB. Measured on this image: **208 MiB peak for the whole container** serving 3562 requests at 20 concurrency against an uncached WordPress front page, MariaDB's 192 MB buffer pool included.

`min_machines_running = 1`, and not as a tuning choice. Scale-to-zero cannot work here for two reasons that end in the same place: WordPress's scheduled events cannot fire on a machine that is stopped, and MariaDB owns the volume, so a stopped machine is a database that is not there to be asked.

This is the shape for a small, read-dominated, single-machine site. A larger project stays on the plain image and points `DB_HOST` at a database of its own — see [Which sites this is for](./docker-image#which-sites-this-is-for).

## See Also

- [The WordPress Runtime Image](./docker-image) — the image this site inherits, the `-db` variant, and the backups it is not using
- [Static Page Cache](./page-cache) — what the generated NGINX rules do, and what is never cached
- [Uploads and Object Storage](./uploads) — the plugin, the endpoint hook, and the two ways of serving media
- `packages/demo/README.md` in the repository, for the demo itself
