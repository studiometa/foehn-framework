# The WordPress Runtime Image

Føhn generates webserver rules — `wp foehn cache:config --server=nginx` — and spells its image transforms into a path a webserver can assemble. Neither is worth anything until something installs them, and until this image that was every project, by hand, once each. `ghcr.io/studiometa/foehn-wordpress` is that something: a project inherits it in one `FROM` line, copies its own code, and configures nothing.

|              |                                                                                    |
| ------------ | ---------------------------------------------------------------------------------- |
| **Image**    | `ghcr.io/studiometa/foehn-wordpress`                                               |
| **Tags**     | `latest`, a release number to pin one, and `-db` on either for the variant         |
| **Base**     | [`webdevops/php-nginx`](https://github.com/webdevops/Dockerfile) (alpine), PHP 8.5 |
| **Web root** | `/app/web`                                                                         |
| **Health**   | `GET /healthcheck`, answered by PHP-FPM                                            |

One PHP version, because Føhn has one: its Composer constraint is `^8.5`, so an image below that could not install the framework it exists to run.

## Using it

```dockerfile
FROM ghcr.io/studiometa/foehn-wordpress:latest

WORKDIR /app

COPY --from=vendor /app/vendor ./vendor
COPY --from=vendor /app/web ./web
COPY . .

# The code belongs to root and PHP cannot rewrite it: `application` is the user a
# compromised plugin gets. Only what is written at runtime belongs to it.
RUN mkdir -p web/wp-content/cache web/wp-content/uploads \
    && chown -R root:root /app \
    && chown -R application:application web/wp-content/cache web/wp-content/uploads
```

That is the whole of it. Nothing about NGINX, no entrypoint, no PHP settings.

The ownership line is the one part the image cannot do for you, because only the project image knows where its code is. Everything under `/app` should belong to `root` and stay unwritable by `application`, the user PHP runs as; only the two paths written at runtime are handed over.

## What it carries

| Piece                             | What it does                                                                      |
| --------------------------------- | --------------------------------------------------------------------------------- |
| Proxy scheme and real IP          | Recovers the scheme and the client's address from the proxy in front              |
| Page cache rules                  | Generated at boot from the site's own configuration, so PHP never starts on a hit |
| Media proxy                       | `/wp-content/uploads/` served from object storage under your own domain           |
| Image transform cache             | `/_image/` served from the bucket, without booting WordPress                      |
| Scheduled events                  | WordPress's cron run from the container's crontab rather than from page loads     |
| Database backups                  | Scheduled dumps to a Restic repository on object storage, off unless asked for    |
| Health endpoint                   | `GET /healthcheck`, answered by PHP-FPM, so it fails when PHP is down             |
| WP-CLI                            | `/usr/local/bin/wp`                                                               |
| `mariadb-client`                  | `mysql` and `mysqldump`, which every `wp db` subcommand shells out to             |
| MariaDB (the `-db` tag only)      | A database in the same container, supervised beside PHP                           |
| Project rules from `config/nginx` | Included in the `server` block, after the image's own                             |

## Why the scheme matters

Behind a TLS terminator — Fly, a load balancer, Cloudflare — NGINX listens in the clear, so `$https` is empty and `fastcgi_params` tells PHP nothing. WordPress's `is_ssl()` then returns false and `set_url_scheme()` rewrites **every** asset URL to `http://`, which a browser on an `https://` page blocks as mixed content. The site renders with no stylesheet and no script, and every status code is still 200. It is a silent failure, and this image exists partly so nobody meets it again.

`$remote_addr` is the other half: without `real_ip`, it is the proxy's address for every visitor, which turns a `limit_req` zone into a site-wide ceiling rather than a per-client limit.

## The page cache

Nothing to install and nothing to commit. The rules are generated at boot from the site's own `page-cache.config.php`, by the same `wp foehn cache:config --server=nginx` you would run by hand — see [Static Page Cache](./page-cache) for what the cache does and what it never caches.

Generated rather than shipped, because they are not fixed: the cache path, the query arguments that are keyed and the ones ignored all come from that configuration, and the generated file carries a hash of it. A static copy baked into the image would be right for the default and quietly wrong for anything else, and serving one visitor's page to another is the failure a page cache has.

If generating fails — a database that is not up yet, most likely — the site falls back to the drop-in Føhn installs at `wp-content/advanced-cache.php`, which serves the same stored files a few milliseconds slower and needs no webserver configuration at all. Measured on one site: 0.9 ms through NGINX against 2.8 ms through the drop-in, both against ~100 ms of network. The fast path is worth having and worth nobody's afternoon.

Set `FOEHN_PAGE_CACHE_CONFIG=false` to leave it to the drop-in. A project that generates the rules itself into `config/nginx/` keeps them: the image generates none when it finds one, because two copies of the same `location` is a configuration NGINX refuses to start with.

## Media and image transforms

`/wp-content/uploads/` and `/_image/` are both proxied to your bucket, so neither costs a PHP process after the first request. Both are rendered at boot from the `S3_UPLOADS_*` variables [`humanmade/s3-uploads`](./uploads) already reads — one image serves every site, so a bucket's hostname cannot be baked in.

Leave `S3_UPLOADS_BUCKET` unset and neither rule is written: uploads stay on local disk and `/_image/` is answered by PHP every time. That is what makes the same image work in a development environment.

`S3_UPLOADS_PROXY_ENABLE=false` turns the media proxy off and leaves `/wp-content/uploads/` on local disk with the bucket still configured. That is the setup where the plugin rewrites media URLs to the bucket or a CDN, so nothing has to go through your own domain.

## Scheduled events

WordPress's pseudo-cron fires on page loads. This image serves cache hits from NGINX without reaching PHP, so the better the cache works the less often cron runs — on a quiet site behind a warm cache, it stops. That failure is caused by the image, so the image fixes it: a real schedule in the container's crontab, on by default, and `DISABLE_WP_CRON` defined so the two mechanisms do not both run.

| Variable              | Default | Role                                                                             |
| --------------------- | ------- | -------------------------------------------------------------------------------- |
| `FOEHN_CRON_ENABLED`  | `true`  | `false` gives WordPress its pseudo-cron back                                     |
| `FOEHN_CRON_SCHEDULE` | `15min` | `15min`, `hourly`, `daily`, `weekly` or `monthly`. Anything else refuses to boot |

Cron cannot fire on a machine that is stopped, so a site using scale-to-zero gets no scheduled events and no error about it.

### The heartbeat

A cron job that stops working is silent by nature: nothing is due, nothing complains, and the site looks fine until somebody notices the newsletter has not gone out since March. So each successful tick records a timestamp:

| Option                | Value          | Autoload |
| --------------------- | -------------- | -------- |
| `foehn_cron_last_run` | Unix timestamp | off      |

Not autoloaded, because a value only a deployment script reads should not be fetched on every page load. `wp foehn verify --profile=production` reads it and fails a deploy whose heartbeat is missing or older than the configured cadence allows.

**A tick that found nothing due still refreshes it.** Nothing being due is the normal case on a site behind a warm cache, so a heartbeat that only moved when work happened would go stale on the quietest, healthiest sites and get them rejected for it. What the heartbeat claims is that the runner completed, not that it had something to do.

If the option write itself fails, the tick fails with it. A run whose events fired but whose heartbeat never landed is a working site that verification declares dead, and that is worth a visible failure rather than a quiet one.

Four cases deliberately leave the previous value alone, which is what makes a stale heartbeat mean something: no WordPress in the container, an unreachable database, failed event execution, and a tick that could not take the overlap lock because the previous one was still running. The first, second and fourth exit `0` — they are ordinary — and only failed execution exits non-zero.

The heartbeat does not prove that every scheduled callback succeeded internally. It proves the runner reached the end of a run. An event whose callback throws makes the tick fail and is caught; an event whose callback quietly does the wrong thing is not this mechanism's job.

## Database backups

Off unless a site asks for them. `BACKUP_ENABLED=true` schedules a `mariadb-dump` into a [Restic](https://restic.net) repository on S3-compatible storage, with grandfather-father-son retention, a weekly prune and a weekly restore drill.

```sh
fly secrets set \
  BACKUP_ENABLED=true \
  RESTIC_REPOSITORY="s3:https://fly.storage.tigris.dev/<project>-db" \
  RESTIC_PASSWORD="$(openssl rand -base64 32)" \
  AWS_ACCESS_KEY_ID=… AWS_SECRET_ACCESS_KEY=…
```

Nothing else. The database is reached through the `DB_*` secrets the site already defines, which is what makes this work the same whether the database is a separate app or a unix socket in this container.

| Variable                      | Default | Role                                                                    |
| ----------------------------- | ------- | ----------------------------------------------------------------------- |
| `BACKUP_ENABLED`              | `false` | The switch. Everything below is inert until this is `true`              |
| `BACKUP_SCHEDULE`             | `daily` | `hourly`, `daily`, `weekly` or `monthly`. Anything else refuses to boot |
| `BACKUP_KEEP_HOURLY`          | `12`    | Hourly snapshots to keep. `0` drops the tier from the policy            |
| `BACKUP_KEEP_DAILY`           | `7`     | Daily snapshots to keep                                                 |
| `BACKUP_KEEP_WEEKLY`          | `8`     | Weekly snapshots to keep                                                |
| `BACKUP_KEEP_MONTHLY`         | `12`    | Monthly snapshots to keep                                               |
| `BACKUP_HEARTBEAT_URL`        | —       | Fetched after a snapshot is stored, and only then                       |
| `BACKUP_MAINTENANCE_ENABLED`  | `true`  | The weekly `restic prune` and `restic check`                            |
| `BACKUP_VERIFY_ENABLED`       | `true`  | The weekly restore drill                                                |
| `BACKUP_VERIFY_TOLERANCE`     | `10`    | How far the restored row count may drift from live, in percent          |
| `BACKUP_VERIFY_HEARTBEAT_URL` | —       | Fetched after a snapshot restores and checks out                        |
| `RESTIC_REPOSITORY`           | —       | e.g. `s3:https://fly.storage.tigris.dev/<project>-db`                   |
| `RESTIC_PASSWORD`             | —       | The encryption key. Losing it loses the backups                         |
| `AWS_ACCESS_KEY_ID`           | —       | Read by Restic directly                                                 |
| `AWS_SECRET_ACCESS_KEY`       | —       | Read by Restic directly                                                 |

### One dump, four tiers

A snapshot taken at midnight on the first of the month is the hourly, the daily, the weekly _and_ the monthly backup at once — `restic forget` decides which tiers each one satisfies after the fact. So there is one dump stream at `BACKUP_SCHEDULE`'s cadence and no per-tier jobs, and setting a tier finer than the schedule degrades oddly rather than usefully: `--keep-hourly 12` against a daily stream keeps twelve dailies.

The dump is not gzipped, on purpose. Gzip's output shifts entirely when an early byte changes, so two dumps of a database that barely moved would share nothing and Restic could deduplicate neither. Plain SQL goes in and Restic compresses it: measured on a 1.1 MB dump of the test fixture, the second snapshot added 9 KB to the repository.

### Two buckets, never one

| Bucket            | Contents                   | Access                |
| ----------------- | -------------------------- | --------------------- |
| `<project>-media` | uploads served to visitors | public read           |
| `<project>-db`    | the Restic repository      | private, never public |

A `backups/` prefix inside the media bucket would leave the bucket policy as the only thing between a database dump and the public internet. This is a security boundary, not a preference.

### Restoring

```sh
fly ssh console -a <app>

restic snapshots                                  # find the one you want
restic dump <id> /<DB_NAME>.sql > /tmp/restore.sql

wp db query "DROP DATABASE \`$DB_NAME\`; CREATE DATABASE \`$DB_NAME\`;" --path=/app/web/wp
wp db import /tmp/restore.sql --path=/app/web/wp
wp cache flush --path=/app/web/wp
```

`restic dump latest …` takes the newest. The weekly drill runs this same path into a scratch schema, so if it has been green the command above has been exercised.

### The weekly restore drill

Every other check in this feature tells you a backup _ran_. Only a restore tells you a backup is a database. A dump that captured an empty schema, credentials that quietly lost a privilege, a repository nobody can decrypt any more — none of them raise anything at backup time, and `restic check` will not catch them either, because it verifies that the repository holds what it claims to hold, not that what it holds is usable.

So once a week the newest snapshot is restored into `<DB_NAME>_foehn_verify`, compared against the live schema, and dropped. It needs two things:

- `CREATE` and `DROP` on `<DB_NAME>_foehn_verify` for the `DB_USER`. This is the one part a tightly scoped database user may not have.
- room for a second copy of the database while it runs, which on an embedded database is room on the volume.

`BACKUP_VERIFY_ENABLED=false` if you cannot give it those. Switch it off last.

### Three things that will bite

- **cron cannot fire on a machine that is stopped.** A site using scale-to-zero gets no backups and no error about it. `min_machines_running = 1` is a precondition, not a tuning choice.
- **Losing `RESTIC_PASSWORD` loses the backups.** There is no recovery path and no support to call. It belongs in a password manager before the first snapshot, not after.
- **A misconfiguration stops the container.** `BACKUP_ENABLED=true` with a missing secret or an unknown schedule fails the boot rather than shipping a site that silently has no backups. On Fly that fails the deploy and rolls back to the machine already serving, so the site stays up — but read the log.

### When this is the wrong system

This targets the fleet it was written for: WordPress sites with small, read-dominated databases, tens of megabytes each. Logical dumps are the right artifact at that size.

**Above roughly 5 GB, or with real transactional writes, it is not.** That calls for `mariadb-backup` and binlog shipping, which give point-in-time recovery. Restic gives snapshot granularity and nothing finer: whatever happened since the last dump is gone.

Physical backups are deliberately out of scope — volume snapshots cover that layer.

## The `-db` variant: a database in the same container

A second tag, `-db`, adds MariaDB to the image and starts it beside PHP. A site using it is one app instead of an `<app>`/`<app>-db` pair: one machine, one deploy, one thing to reason about.

```dockerfile
FROM ghcr.io/studiometa/foehn-wordpress:0.5.10-db    # its own database
FROM ghcr.io/studiometa/foehn-wordpress:0.5.10       # an external one
```

That is the only difference in the project's Dockerfile. The variant reads the same `DB_NAME`, `DB_USER` and `DB_PASSWORD` a site already defines, creates the database and the user on first boot, and points `DB_HOST` at its own socket — so a site keeps its secrets, its `wp-config.php` and its backups exactly as they were.

The server listens on a unix socket and on no TCP port at all. `DB_HOST=localhost` finds it because the client library already looks there and the image points PHP at the same path.

### Why a tag and not a flag

The MariaDB server is 187 MB installed. In the base image that is charged to every site, including every site that will never start a database. A runtime `DB_EMBEDDED` flag would also put a topology decision somewhere it can be set wrong — a typo starts nothing, or starts a database nobody expected. A tag cannot be half-set, and the version coupling is visible in the string.

### What a site has to change in `fly.toml`

```toml
[[mounts]]
  source      = "data"
  destination = "/var/lib/mysql"

# InnoDB has to finish flushing. Cut short, the next boot spends its time on
# crash recovery instead — measured: a 1 s grace period produced "Starting crash
# recovery from checkpoint LSN=..." on the following start, a 30 s one did not.
kill_timeout = "30s"

[http_service.checks]
  # supervisord orders its programs but does not wait for one to be ready before
  # starting the next, so PHP-FPM can answer before MariaDB has finished
  # starting — a few seconds in which the site would serve "Error establishing a
  # database connection". A grace period covers it.
  grace_period = "15s"
```

Create the volume with a retention worth having. Fly keeps volume snapshots for five days by default, and volume snapshots are the physical backup layer here:

```sh
fly volumes create data --size 1 --snapshot-retention 14
```

`min_machines_running = 1` if the site also runs scheduled backups or scheduled events — cron cannot fire on a stopped machine.

### What it costs

**The app gains a volume**, and a volume pins the machine to one host. On a site that had none, Fly could replace the machine freely; now it cannot. This is the real price of the change.

**Deploys restart the database.** With a separate database app, a site deploy leaves it alone. Co-located, every deploy stops and starts MariaDB with the site.

**MariaDB no longer follows its own image's patch cadence.** The version is whatever the Alpine base carries — 11.8 today. The variant can be rebuilt and republished without cutting a Føhn release, so the fix is cheap, but somebody has to notice the CVE.

### Sizing

| Variable                   | Default | Role                 |
| -------------------------- | ------- | -------------------- |
| `MARIADB_BUFFER_POOL_SIZE` | `192M`  | InnoDB's buffer pool |

192 MB is several times the size of the databases this is meant for, and it leaves the rest of a 1 GB machine to PHP. Measured on a 1 GB container serving 3562 requests at 20 concurrency against an uncached WordPress front page: **208 MiB peak for the whole container**, MariaDB included.

### Which sites this is for

Small ones: tens of megabytes, read-dominated, one machine. Larger projects stay on the plain image and point `DB_HOST` at an external database. The plain image is the default and the one that must never regress — no site should have to opt out of anything to keep working.

## Project configuration

Anything in the project's `config/nginx/*.conf` is included in the `server` block, after this image's own rules. That is the seam for a redirect, a header, or a `location` the image knows nothing about.

## Environment

Read from the same variables WordPress and `humanmade/s3-uploads` already read.

| Variable                    | Default             | Role                                                                |
| --------------------------- | ------------------- | ------------------------------------------------------------------- |
| `S3_UPLOADS_BUCKET`         | —                   | The site's bucket. Unset means media is served from local disk      |
| `S3_UPLOADS_ENDPOINT`       | —                   | The S3 endpoint, from which the bucket's host is derived            |
| `S3_UPLOADS_PATH_STYLE`     | `false`             | `true` addresses `<endpoint>/<bucket>/<key>` — MinIO in development |
| `S3_UPLOADS_PROXY_ENABLE`   | `true`              | `false` turns the media proxy off                                   |
| `S3_UPLOADS_PROXY_HOST`     | derived             | To go through a CDN, or an endpoint that follows neither style      |
| `S3_UPLOADS_PROXY_RESOLVER` | `1.1.1.1 8.8.8.8`   | The resolver NGINX uses for the bucket's host                       |
| `FOEHN_PAGE_CACHE_CONFIG`   | —                   | `false` generates no page cache rules and leaves it to the drop-in  |
| `FOEHN_CRON_ENABLED`        | `true`              | `false` gives WordPress its pseudo-cron back                        |
| `FOEHN_CRON_SCHEDULE`       | `15min`             | How often scheduled events run                                      |
| `FOEHN_NGINX_CONF_DIR`      | `/app/config/nginx` | Where the project's own NGINX rules are read from                   |

The `DB_*` and `BACKUP_*` variables are listed above, with the features that read them.

## See Also

- [Static Page Cache](./page-cache) — what the generated rules do, and what is never cached
- [Uploads and Object Storage](./uploads) — the plugin and the constants the proxy rules read
- `docker/wordpress/README.md` in the repository, for building or editing the image itself
