# The WordPress runtime image

A WordPress runtime with Føhn's serving conventions already in it, published to
`ghcr.io/studiometa/foehn-wordpress`.

Føhn generates webserver rules and spells its image-transform cache into a path a
webserver can assemble. Both are only worth something if something installs them,
and until now that was every project, by hand, once each. This is that something:
a project inherits it in one `FROM` line, copies its own code, and configures
nothing.

|              |                                                                                    |
| ------------ | ---------------------------------------------------------------------------------- |
| **Image**    | `ghcr.io/studiometa/foehn-wordpress`                                               |
| **Tags**     | `latest`, and a release number to pin one                                          |
| **Base**     | [`webdevops/php-nginx`](https://github.com/webdevops/Dockerfile) (alpine), PHP 8.5 |
| **Web root** | `/app/web`                                                                         |
| **Health**   | `GET /healthcheck`, answered by PHP-FPM                                            |

One PHP version, because Føhn has one: its Composer constraint is `^8.5`, so an
image below that could not install the framework it exists to run. `PHP_VERSION`
is still a build argument — when the floor moves, the move is one line.

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

## What it carries

| Piece                                                 | What it does                                                          |
| ----------------------------------------------------- | --------------------------------------------------------------------- |
| `nginx/conf.d/proxy-scheme.conf`                      | Recovers the scheme and the client's address from the proxy in front  |
| `nginx/fastcgi_params`                                | Upstream's, one line apart: `HTTPS` from `X-Forwarded-Proto`          |
| `nginx/templates/uploads-proxy.conf.template`         | `/wp-content/uploads/` served from object storage                     |
| `nginx/templates/image-cache.conf.template`           | `/_image/` served from the bucket, without booting WordPress          |
| `nginx/conf.d/uploads-cache.conf`, `image-limit.conf` | The cache and rate-limit zones those two need                         |
| `entrypoint.d/50-nginx-project-conf.sh`               | Picks up the project's own `config/nginx/*.conf`                      |
| `entrypoint.d/60`, `70`                               | Render the two templates at boot, from `S3_UPLOADS_*`                 |
| `php/healthcheck.php`                                 | A health endpoint that fails when PHP is down, not only when NGINX is |
| WP-CLI                                                | `/usr/local/bin/wp`                                                   |

### Why the scheme matters

Behind a TLS terminator — Fly, a load balancer, Cloudflare — NGINX listens in the
clear, so `$https` is empty and `fastcgi_params` tells PHP nothing. WordPress's
`is_ssl()` then returns false and `set_url_scheme()` rewrites **every** asset URL
to `http://`, which a browser on an `https://` page blocks as mixed content. The
site renders with no stylesheet and no script, and every status code is still 200. It is a silent failure, and this image exists partly so nobody meets it
again.

`$remote_addr` is the other half: without `real_ip`, it is the proxy's address
for every visitor, which turns a `limit_req` zone into a site-wide ceiling rather
than a per-client limit.

## The page cache

Nothing to install and nothing to commit: the rules are generated at boot from
the site's own `page-cache.config.php`, by the same
`wp foehn cache:config --server=nginx` a person would run.

Generated rather than shipped, because they are not fixed — the cache path, the
query arguments that are keyed and the ones ignored all come from that
configuration, and the file carries a hash of it. A static copy baked into the
image would be right for the default and quietly wrong for anything else, and
serving one visitor's page to another is the failure a page cache has.

If generating fails — a database that is not up yet, most likely — the site
falls back to the drop-in Føhn installs at `wp-content/advanced-cache.php`, which
serves the same stored files a few milliseconds slower and needs no webserver
configuration at all. Measured on one site: 0.9 ms through NGINX against 2.8 ms
through the drop-in, both against ~100 ms of network. The fast path is worth
having and worth nobody's afternoon.

Set `FOEHN_PAGE_CACHE_CONFIG=false` to leave it to the drop-in. A project that
generates the rules itself into `config/nginx/` keeps them: this generates none
when it finds one, because two copies of the same `location` is a configuration
NGINX refuses to start with.

## Project configuration

Anything in the project's `config/nginx/*.conf` is included in the `server`
block, after this image's own rules.

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
| `FOEHN_NGINX_CONF_DIR`      | `/app/config/nginx` | Where the project's own NGINX rules are read from                   |

## Building it locally

```sh
docker build -t foehn-wordpress docker/wordpress/
```

## A note for anyone editing the entrypoints

The upstream entrypoint **sources** these scripts rather than running them. So
`exit` ends the whole boot, and `set -e`/`set -u` outlive the file and change how
every later script behaves. Each script therefore wraps its body in a function
and returns. A container that stops quietly right after `-> Executing …` is this
mistake, and the CI check refuses it.
