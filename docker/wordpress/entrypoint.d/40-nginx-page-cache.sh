#!/bin/sh
###############################################################################
# Installs Føhn's page cache rules, generated from the site's own configuration.
#
# `wp foehn cache:config --server=nginx` is the only thing that knows what those
# rules should say: the cache path, the query arguments that are keyed and the
# ones that are ignored all come from `page-cache.config.php`, and the generated
# file carries a hash of that policy so a drift can be spotted.
#
# Which is why this generates rather than ships a copy. A static file baked into
# the image would be right for the default configuration and quietly wrong for
# any project that changed it — serving one visitor's page to another is exactly
# the failure a page cache has, and the one Føhn's documentation warns about. A
# generated file cannot disagree with the configuration it was generated from.
#
# It is also why nothing has to be committed to a project: no generated file in
# the repository, and no way for it to fall behind the configuration beside it.
#
# `40-`, so a project's own `config/nginx/` — picked up at `50-` — comes after and
# can add to it. A project that has generated the rules itself is left alone
# entirely: two copies of these `location` blocks is a configuration NGINX
# refuses to start with.
#
# Failing is not fatal. Generating means booting WordPress, so a database that is
# not up yet leaves the site without this fast path — and with the drop-in Føhn
# installs at `wp-content/advanced-cache.php`, which needs no webserver
# configuration and serves the same stored files a few milliseconds slower. The
# site is never worse than it would have been.
###############################################################################
# The upstream entrypoint *sources* these scripts (`. "$FILE"`), it does not run
# them. So `exit` here would end the whole boot, and `set -e`/`set -u` would
# outlive this file and change how every later script behaves. Everything is
# therefore wrapped in a function, which can `return`, and nothing is set
# globally.

foehn_nginx_page_cache() {
    script_name="nginx-page-cache"

    target_file="/opt/docker/etc/nginx/vhost.common.d/40-foehn-page-cache.conf"
    web_user="${APPLICATION_USER:-application}"
    project_dir="${FOEHN_NGINX_CONF_DIR:-/app/config/nginx}"

    # A clean slate: rules from an earlier boot must not outlive a configuration
    # that no longer asks for them.
    rm -f "$target_file"

    if [ "${FOEHN_PAGE_CACHE_CONFIG:-}" = "false" ]; then
        echo "ℹ️ NOTICE ($script_name): FOEHN_PAGE_CACHE_CONFIG=false, the page cache is left to the drop-in."
        return 0
    fi

    # A project that generated the rules itself wins: its file is included at
    # `50-`, and a second copy of the same `location` blocks is a configuration
    # NGINX refuses to start with.
    for existing in "$project_dir"/*page-cache*.conf; do
        if [ -e "$existing" ]; then
            echo "ℹ️ NOTICE ($script_name): the project provides its own rules, generating none."
            return 0
        fi
    done

    if ! command -v wp >/dev/null 2>&1; then
        return 0
    fi

    # As `application`, the user PHP runs as, and never as root. Generating means
    # booting WordPress, and booting WordPress writes Føhn's discovery cache under
    # `wp-content/cache/` — as root, those directories come out owned by root, and
    # PHP can no longer write the page cache into them. This step would then
    # disable the very cache it exists to serve, silently, on a site that looks
    # like it is working.
    #
    # `--skip-plugins`, so a plugin that fails to load does not cost the site its
    # fast path — but never `--skip-themes`: Føhn lives in the theme, and skipping
    # it means the `foehn` command is never registered at all.
    generated=$(su -s /bin/sh "$web_user" -c \
        "wp --path=/app/web/wp --skip-plugins foehn cache:config --server=nginx" \
        2>/dev/null) || generated=""

    # Anything other than the real thing — an error, a warning, an empty string
    # — is not written. A half-rendered file is a webserver that will not start.
    if [ -z "$generated" ] || ! printf '%s' "$generated" | grep -q 'foehn_bypass'; then
        echo "ℹ️ NOTICE ($script_name): rules could not be generated, the drop-in serves the cache."
        return 0
    fi

    printf '%s\n' "$generated" > "$target_file"

    echo "ℹ️ NOTICE ($script_name): page cache rules installed."

    if [ "${LOG_OUTPUT_LEVEL:-}" = "debug" ]; then
        cat "$target_file"
    fi
}

foehn_nginx_page_cache
