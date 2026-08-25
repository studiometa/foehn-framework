#!/bin/sh
###############################################################################
# Renders the NGINX block that relays /wp-content/uploads/ to object storage.
#
# A bucket's hostname is per-site while one image serves them all, so it cannot
# be baked in. The template is rendered at boot from the same variables
# `humanmade/s3-uploads` and Føhn already read — nothing new to configure.
#
# `sed` rather than `envsubst`: the alpine base ships no `gettext`, and three
# variables do not justify another package. It also avoids `envsubst`'s trap of
# replacing NGINX's own variables ($uri, $upstream_cache_status…) unless every
# one is enumerated.
###############################################################################
# The upstream entrypoint *sources* these scripts (`. "$FILE"`), it does not run
# them. So `exit` here would end the whole boot, and `set -e`/`set -u` would
# outlive this file and change how every later script behaves. Everything is
# therefore wrapped in a function, which can `return`, and nothing is set
# globally. A container that quietly stops after "-> Executing …" is this
# mistake.

foehn_nginx_uploads_proxy() {
    script_name="nginx-uploads-proxy"

    template_file="/opt/docker/etc/nginx/templates/uploads-proxy.conf.template"
    output_file="/opt/docker/etc/nginx/vhost.common.d/60-uploads-proxy.conf"

    # A clean slate: with no bucket, media is served from local disk, which is the
    # development mode with no access to storage.
    rm -f "$output_file"

    if [ "${S3_UPLOADS_PROXY_ENABLE:-}" = "false" ]; then
        echo "ℹ️ NOTICE ($script_name): S3_UPLOADS_PROXY_ENABLE=false, /wp-content/uploads/ stays on local disk."
            return 0
    fi

    if [ -z "${S3_UPLOADS_BUCKET:-}" ]; then
        echo "ℹ️ NOTICE ($script_name): S3_UPLOADS_BUCKET is unset, /wp-content/uploads/ stays on local disk."
            return 0
    fi

    proxy_prefix=""

    # `S3_UPLOADS_PROXY_HOST` is the way through a CDN, or to an endpoint that
    # follows neither addressing style.
    if [ -n "${S3_UPLOADS_PROXY_HOST:-}" ]; then
        proxy_host="$S3_UPLOADS_PROXY_HOST"
        proxy_scheme="${S3_UPLOADS_PROXY_SCHEME:-https}"
    else
        if [ -z "${S3_UPLOADS_ENDPOINT:-}" ]; then
            echo "🛑 ERROR ($script_name): S3_UPLOADS_BUCKET is set but neither S3_UPLOADS_ENDPOINT nor S3_UPLOADS_PROXY_HOST is." >&2
            return 1
        fi

        proxy_scheme=$(echo "${S3_UPLOADS_ENDPOINT}" | sed -n 's|^\([a-z][a-z0-9+.-]*\)://.*|\1|p')
        proxy_scheme="${proxy_scheme:-https}"

        endpoint_host=$(echo "${S3_UPLOADS_ENDPOINT}" | sed -e 's|^[a-z][a-z0-9+.-]*://||' -e 's|/.*$||')

        # Two ways to address an object, and development does not use the same one as
        # production. MinIO is path-style: the bucket is the first path segment.
        # Tigris, OVH and AWS are virtual-hosted: the bucket is a subdomain.
        # `S3_UPLOADS_PATH_STYLE` is the variable s3-uploads and Føhn already read,
        # so there is nothing new to set.
        if [ "${S3_UPLOADS_PATH_STYLE:-}" = "true" ]; then
            proxy_host="$endpoint_host"
            proxy_prefix="/$S3_UPLOADS_BUCKET"
        else
            proxy_host="$S3_UPLOADS_BUCKET.$endpoint_host"
        fi
    fi

    # The upstream is a hostname resolved per request, so NGINX needs a resolver.
    proxy_resolver="${S3_UPLOADS_PROXY_RESOLVER:-1.1.1.1 8.8.8.8}"

    sed \
        -e "s|\${UPLOADS_PROXY_SCHEME}|$proxy_scheme|g" \
        -e "s|\${UPLOADS_PROXY_HOST}|$proxy_host|g" \
        -e "s|\${UPLOADS_PROXY_PREFIX}|$proxy_prefix|g" \
        -e "s|\${UPLOADS_PROXY_RESOLVER}|$proxy_resolver|g" \
        "$template_file" > "$output_file"

    echo "ℹ️ NOTICE ($script_name): /wp-content/uploads/ relayed to $proxy_scheme://$proxy_host$proxy_prefix"

    if [ "${LOG_OUTPUT_LEVEL:-}" = "debug" ]; then
        cat "$output_file"
    fi
}

foehn_nginx_uploads_proxy
