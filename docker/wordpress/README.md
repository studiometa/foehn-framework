# The WordPress runtime image

The source of `ghcr.io/studiometa/foehn-wordpress`. This file is for editing the
image; **what it does and how a project uses it is documented in
[the Docker Image guide](../../docs/guide/docker-image.md)**, and that guide is the
one to update when behaviour changes.

|              |                                                                                    |
| ------------ | ---------------------------------------------------------------------------------- |
| **Image**    | `ghcr.io/studiometa/foehn-wordpress`                                               |
| **Tags**     | `latest`, a release number to pin one, and `-db` on either for the variant         |
| **Base**     | [`webdevops/php-nginx`](https://github.com/webdevops/Dockerfile) (alpine), PHP 8.5 |
| **Web root** | `/app/web`                                                                         |
| **Health**   | `GET /healthcheck`, answered by PHP-FPM                                            |

One PHP version, because Føhn has one: its Composer constraint is `^8.5`, so an
image below that could not install the framework it exists to run. `PHP_VERSION`
is still a build argument — when the floor moves, the move is one line.

## What is in here

| File                                          | What it is                                                            |
| --------------------------------------------- | --------------------------------------------------------------------- |
| `Dockerfile`                                  | Both stages: `base` and `db`                                          |
| `nginx/conf.d/proxy-scheme.conf`              | `real_ip` and the `map` that recovers the forwarded scheme            |
| `nginx/conf.d/uploads-cache.conf`             | The `proxy_cache_path` and zone the media proxy needs                 |
| `nginx/conf.d/image-limit.conf`               | The `limit_req` zone `/_image/` needs                                 |
| `nginx/fastcgi_params`                        | Upstream's, one line apart: `HTTPS` from `X-Forwarded-Proto`          |
| `nginx/vhost.common.d/20-healthcheck.conf`    | The `location` that passes `/healthcheck` to PHP-FPM                  |
| `nginx/templates/uploads-proxy.conf.template` | `/wp-content/uploads/` served from object storage                     |
| `nginx/templates/image-cache.conf.template`   | `/_image/` served from the bucket, without booting WordPress          |
| `php/zz-container.ini`                        | PHP settings that belong to running in a container                    |
| `php/healthcheck.php`                         | A health endpoint that fails when PHP is down, not only when NGINX is |
| `entrypoint.d/`                               | Boot-time scripts, in the order below                                 |
| `bin/foehn-backup*`                           | The three backup jobs cron runs, and the file they source             |
| `bin/foehn-cron`                              | The job that runs `wp cron event run --due-now`                       |
| `db/`                                         | Everything the `-db` stage adds: MariaDB service, supervisor, PHP ini |

`entrypoint.d` is ordered, and the numbers carry meaning:

| Script                               | Why there                                                              |
| ------------------------------------ | ---------------------------------------------------------------------- |
| `30-mariadb.sh` (`db` only)          | Before anything that may want a database                               |
| `40-nginx-page-cache.sh`             | Before `50-`, so a project's own rules come after and win              |
| `50-nginx-project-conf.sh`           | Picks up the project's own `config/nginx/*.conf`                       |
| `60-nginx-uploads-proxy.sh`          | Renders `uploads-proxy.conf.template`                                  |
| `70-nginx-image-cache.sh`            | Renders `image-cache.conf.template`                                    |
| `80-backup.sh`                       | Schedules the backup jobs                                              |
| `85-wp-cron.sh`                      | After `80-`, so the two cron features read in the order they run       |
| `90-mariadb-handover.sh` (`db` only) | Last: stops the provisioning server so supervisord starts the only one |

## Building it locally

Two stages in one Dockerfile, so name the one you want. Docker builds the
**last** stage when no target is given, and that is the variant.

```sh
docker build --target base -t foehn-wordpress docker/wordpress/
docker build --target db   -t foehn-wordpress-db docker/wordpress/
```

`--build-arg PHP_VERSION=…` overrides the base tag.

## A note for anyone editing the entrypoints

The upstream entrypoint **sources** these scripts rather than running them. So
`exit` ends the whole boot, and `set -e`/`set -u` outlive the file and change how
every later script behaves. Each script therefore wraps its body in a function
and returns. A container that stops quietly right after `-> Executing …` is this
mistake, and the CI check refuses it.

The backup jobs in `bin/` are the opposite case: cron **runs** them, so they set
`set -eu` and exit non-zero on failure, which is how a failed backup becomes a
visible failure rather than a quiet one. `bin/foehn-backup-common.sh` is sourced
by all three and is deliberately not executable. CI checks both halves of this.

## The cron heartbeat

`bin/foehn-cron` records one WordPress option after each successful tick:

| Option                | Value          | Autoload |
| --------------------- | -------------- | -------- |
| `foehn_cron_last_run` | Unix timestamp | off      |

It is not autoloaded, because nothing but a deployment script ever reads it and an autoloaded option is fetched on every page load of the site. WordPress 6.6 spells that column `on`/`off` rather than the `yes`/`no` older code expects, which is what the CI assertion checks against.

**Success means the tick completed, not that it did work.** Nothing being due is the normal case on a site behind a warm cache, so a tick that ran zero events refreshes the heartbeat too. An option that only moved when work happened would go stale on precisely the quietest, healthiest site, and `wp foehn verify --profile=production` would reject it for being healthy.

Persistence is part of success: if the option write fails, the runner logs and exits non-zero. A run whose events fired but whose heartbeat never landed is a site verification would declare dead while it works, which is worth failing the tick over rather than hiding.

These runs deliberately leave the previous value alone, which is what makes a stale heartbeat mean something:

| Case                          | Exit | Why it does not advance                                                                                  |
| ----------------------------- | ---- | -------------------------------------------------------------------------------------------------------- |
| No WordPress in the container | `0`  | Ordinary during boot or on an image with no site yet, not an emergency                                   |
| Database unreachable          | `0`  | The next tick will do the work; a heartbeat here would report a site that cannot run anything as running |
| Event execution failed        | `1`  | The events did not run, so nothing should say they did                                                   |
| Overlap `flock` not acquired  | `0`  | This tick did no work at all — the previous one is still doing it                                        |

The write uses `wp eval` calling `update_option($key, $value, false)` rather than `wp option update --autoload=no`, although the WP-CLI in this image (2.12) understands that flag. `wp option update` exits `1` when the value it is given is the one already stored, reporting `Could not update option` — the same message and the same exit code as a database that refused the write. Two ticks inside one second would therefore report a healthy run as a broken one, with no way to tell the two apart from outside. Reading the option back is also a stronger claim than `update_option()`'s return value: it says the timestamp is in the database, not merely that nothing objected on the way there.
