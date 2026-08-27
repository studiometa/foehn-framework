# syntax=docker/dockerfile:1

# The demo, as one machine that carries its own database.
#
# Three stages, because the three things a Føhn site is made of are built by
# three different toolchains and only the result of each belongs in the image:
# Vite compiles the theme's assets, Composer resolves the framework and
# generates the web root, and the runtime image already knows how to serve what
# comes out. The project configures no webserver and no PHP.
#
# @see ../../docker/wordpress/README.md

# The embedded-database variant: MariaDB in this container, supervised beside
# PHP. One app instead of an `<app>`/`<app>-db` pair, which at the size of this
# site is the right trade — the reference measurement is 208 MiB peak for the
# whole container under load. Pinned to a release rather than `latest-db`, so a
# rebuild of the demo two months from now produces the site that was tested and
# not the site the image drifted into.
ARG FOEHN_IMAGE=ghcr.io/studiometa/foehn-wordpress:0.5.10-db

###############################################################################
# The theme's assets.
#
# Vite writes into `theme/dist/`, not into the package root: only `theme/` is
# symlinked into the web root, so anything built beside it would never be
# served. @see vite.config.js
FROM node:22-alpine AS assets

WORKDIR /app

# `npm install` and not `npm ci`, because this package has no lockfile of its
# own: the monorepo keeps one at its root, for the workspace, and the root is
# outside this build context. `npm ci` refuses to run without one.
COPY package.json ./
RUN npm install --no-audit --no-fund

# After the install, so a change to a stylesheet does not re-resolve the whole
# dependency tree.
COPY vite.config.js ./
COPY theme/ ./theme/

RUN npm run build

###############################################################################
# The framework, and the web root it generates.
#
# `studiometa/foehn-installer` runs as a Composer plugin during the install and
# is what produces `web/` — `index.php`, `wp-config.php`, the theme symlink, the
# mu-plugin loader and the page cache drop-in. None of it is committed, so this
# stage is the only place it exists.
#
# `composer:2` carries PHP 8.5, which is the floor `studiometa/foehn` requires;
# an older image could not install the framework at all.
FROM composer:2 AS vendor

WORKDIR /app

# `theme/` before the install, because the installer symlinks
# `web/wp-content/themes/demo-theme` at it and a symlink to a directory that is
# not there yet is one the installer skips with a warning and a working build.
COPY composer.json ./
COPY theme/ ./theme/

# No lockfile, on purpose: the demo resolves `studiometa/foehn: ^0.5` from
# Packagist, so the deployed site shows the last released framework — which is
# what a real project starting today would get. Pinning it here would make the
# demo a record of the day it was built.
#
# `--no-dev`, because the test suites are not served; `--optimize-autoloader`,
# because a classmap is one file read instead of a directory walk per class, and
# nothing in a built image is going to add a class.
#
# The `rm` is part of the same step and not an afterthought. The installer writes
# the eight WordPress security keys into `.env` when it finds none in the
# environment — it has to, since a site with guessable keys is a site whose login
# cookies can be forged. In an image that file is the wrong answer twice over: it
# would bake secret material into a layer, and it would be read by `Dotenv`
# before the container's environment is consulted for anything it does not
# already define. The keys come from Fly secrets instead, which is the
# arrangement `wp-config.php` documents.
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --optimize-autoloader \
    && rm -f .env

###############################################################################
# The site.
FROM ${FOEHN_IMAGE}

WORKDIR /app

COPY --from=vendor /app/vendor ./vendor
COPY --from=vendor /app/web ./web

# `wp-cli.yml` sets `path: web/wp`, which is what lets every `wp` command in the
# entrypoint be written without repeating it.
COPY composer.json wp-cli.yml ./
COPY theme/ ./theme/
COPY --from=assets /app/theme/dist ./theme/dist

# The portfolio itself: the SQL dump, the thirty photographs and the scripts that
# turn them into a site. Shipped in the image rather than fetched at boot,
# because a container that needs the network to become itself is a container
# that fails for reasons unrelated to the demo.
COPY database/ ./database/

COPY docker/entrypoint.d/ /opt/docker/provision/entrypoint.d/

# The code belongs to root and PHP cannot rewrite it: `application` is the user a
# compromised plugin gets. Only what is written at runtime belongs to it.
RUN chmod +x /opt/docker/provision/entrypoint.d/35-demo-seed.sh \
    && mkdir -p web/wp-content/cache web/wp-content/uploads \
    && chown -R root:root /app \
    && chown -R application:application web/wp-content/cache web/wp-content/uploads
