# syntax=docker/dockerfile:1

# A WordPress runtime with Føhn's serving conventions already in it.
#
# Føhn generates webserver rules — `wp foehn cache:config --server=nginx` — and
# spells its image-transform cache into a path a webserver can assemble. Both are
# only worth anything if something installs them, and until now that was every
# project, by hand, once each. This is that something.
#
# A project inherits it in a `FROM` line and copies its own code. It configures
# nothing: what the rules need, they read from the same environment WordPress
# already reads.
#
# @see README.md

ARG PHP_VERSION=8.5

# `webdevops/php-nginx` runs NGINX and PHP-FPM under a supervisord that does not
# need to be PID 1 — which is what makes it work on Fly, where init always is. It
# also ships `mysqli`, `gd`, `exif`, `intl` and `bcmath`, and its default
# `try_files` is already the one WordPress wants.
FROM webdevops/php-nginx:${PHP_VERSION}-alpine AS base

# WP-CLI. Clearing a cache, rewriting a URL, flushing rewrite rules: none of it
# has a URL, all of it is done over a shell, and it is the same binary on every
# site.
RUN curl -fsSL -o /usr/local/bin/wp \
        https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x /usr/local/bin/wp

# The two halves of a database backup: something that reads the database and
# something that stores what it read. Both belong in this image and not in a
# variant, because a backup has to work against a database anywhere — the point
# of the feature is that a site keeps its backups when its database moves.
#
# `mariadb-client` pays for itself a second time over. WP-CLI shells out to
# `mysql` and `mysqldump` for every `wp db` subcommand, so without the package
# each of them fails with `env: can't execute 'mysql'` — which is how the image
# shipped until now. Alpine's package installs those two legacy names alongside
# `mariadb` and `mariadb-dump`, so nothing else is needed to fix it.
#
# `flock` looks redundant — busybox provides one, and `/usr/bin/flock` already
# resolves to it. But busybox's takes only `-sxun`: it can fail immediately on a
# held lock and it can block for ever, and nothing in between. The weekly
# maintenance and restore jobs need to *wait* for the backup they collide with
# once a week, which is `-w`, which is util-linux's. The package is a 26 KB
# binary that replaces the symlink.
RUN apk add --no-cache mariadb-client restic flock

# PHP settings that belong to running in a container (see the file).
COPY php/zz-container.ini /usr/local/etc/php/conf.d/zz-container.ini

# Outside the web root, so it is not served as a page, and passed to PHP-FPM so
# the check fails when PHP is down and not only when NGINX is.
COPY php/healthcheck.php /opt/healthcheck.php

# The upstream `fastcgi_params`, one line apart: `HTTPS` is derived from
# `X-Forwarded-Proto` rather than from `$https` alone, which is empty behind a
# TLS terminator. This file is included inside the upstream's own PHP location,
# which a project cannot reopen — replacing it is the only place to intervene.
COPY nginx/fastcgi_params /etc/nginx/fastcgi_params

# `http` context: cache zones, rate-limit zones and `map` cannot live in a
# `server` block.
COPY nginx/conf.d/ /etc/nginx/conf.d/

# `server` context. The numeric prefixes place these after the upstream's `10-*`
# blocks (`location /`, PHP, logs).
COPY nginx/vhost.common.d/ /opt/docker/etc/nginx/vhost.common.d/

# Rendered at boot: a bucket's hostname is per-site, and one image serves them
# all.
COPY nginx/templates/ /opt/docker/etc/nginx/templates/
COPY entrypoint.d/ /opt/docker/provision/entrypoint.d/

# Run by cron rather than sourced at boot, which is why they live beside the
# other services' scripts and not in `entrypoint.d/`. `foehn-backup-common.sh`
# is sourced by the three of them and is deliberately left non-executable.
COPY bin/ /opt/docker/bin/

RUN chmod +x /opt/docker/provision/entrypoint.d/*.sh \
    && chmod +x /opt/docker/bin/foehn-backup \
        /opt/docker/bin/foehn-backup-maintenance \
        /opt/docker/bin/foehn-backup-verify \
        /opt/docker/bin/foehn-cron

# The web root is `web/`, not the repository root: `index.php` and
# `wp-config.php` live there, while `vendor/` and `config/` stay out of reach.
#
# `COMPOSER_ALLOW_SUPERUSER=1` is not optional: builds run as root, and without
# it Composer silently disables every plugin — `composer/installers` and
# `johnpbloch/wordpress-core-installer` stop running, `web/` is never created,
# and the command still exits successfully.
ENV WEB_DOCUMENT_ROOT=/app/web \
    PHP_OPCACHE_ENABLE=1 \
    COMPOSER_ALLOW_SUPERUSER=1

# NGINX creates only the last level of a `proxy_cache_path`, so its parent has to
# exist — without it the media cache is a boot-time `emerg` and nothing serves.
RUN mkdir -p /var/cache/nginx \
    && chown -R application:application /var/cache/nginx

WORKDIR /app

# Hardening the file ownership belongs to the project image, which is the only
# one that knows where its code is: the code should belong to `root` and stay
# unwritable by `application`, the user PHP runs as. Only the paths actually
# written at runtime belong to it.
# @see README.md

###############################################################################
# The `-db` variant: the same image, with its own database inside it.
#
# A site is then one app instead of an `<app>`/`<app>-db` pair — one machine,
# one deploy, roughly half the bill — which at the size these sites are is the
# right trade. The web machine already has the memory: the reference site uses
# 114 MB of 962 MB and its whole schema is 35 MB.
#
# A variant and not a `DB_EMBEDDED` flag, because the server is not free. The
# `mariadb` package is 187 MB installed, and putting it in the base image would
# charge that to every site including the ones that will never start a database.
# A flag would also move a topology decision into runtime configuration, where
# it can be set wrong in a way that either starts nothing or starts a database
# nobody expected. A tag cannot be half-set:
#
#     FROM ghcr.io/studiometa/foehn-wordpress:0.5.7-db    # its own database
#     FROM ghcr.io/studiometa/foehn-wordpress:0.5.7       # an external one
#
# Purely additive, on purpose. supervisord's include directory and
# `entrypoint.d` were already the extension points this needs, so the variant
# installs one package and adds three files, and nothing above this line had to
# change to accommodate it. The plain image is the one that must never regress.
#
# Note the stages: `--target base` is the plain image and `--target db` is this
# one. Docker builds the *last* stage by default, so a build with no target
# gives the variant.
FROM base AS db

RUN apk add --no-cache mariadb

# `service.d` and `supervisor.d` follow the base image's own conventions for
# adding a supervised program; `entrypoint.d` is the same boot-time hook
# directory the NGINX rules use.
COPY db/service.d/ /opt/docker/bin/service.d/
COPY db/supervisor.d/ /opt/docker/etc/supervisor.d/
COPY db/entrypoint.d/ /opt/docker/provision/entrypoint.d/

# So that "localhost" means the socket to PHP as well, and not the build-time
# default it would otherwise use (see the file).
COPY db/php/zz-embedded-db.ini /usr/local/etc/php/conf.d/zz-embedded-db.ini

RUN chmod +x /opt/docker/bin/service.d/mariadb.sh \
        /opt/docker/provision/entrypoint.d/30-mariadb.sh \
        /opt/docker/provision/entrypoint.d/90-mariadb-handover.sh
