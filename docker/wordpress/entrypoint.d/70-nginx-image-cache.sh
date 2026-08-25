#!/bin/sh
###############################################################################
# Renders the nginx block that serves built image transforms from the bucket.
#
# The same shape as `60-nginx-uploads-proxy.sh`, and the same variables: one
# image serves every site, so a bucket hostname cannot be baked in.
#
# Rendered for every site that has a bucket, with nothing to opt into. Føhn's
# Glide route is what writes transforms there; a site that does not use it never
# receives a `/_image/` request, so the block simply never matches. Rendering it
# unconditionally is the cheaper mistake — a project that forgets a flag gets a
# working site, not one that boots WordPress for every image.
#
# `70-` so it renders after the uploads proxy, which is the block it sits beside.
#
# `sed` and not `envsubst`: the alpine base ships no `gettext`, and `envsubst`
# would eat nginx's own `$uri`, `$arg_w` and friends unless every one were
# enumerated.
###############################################################################
# The upstream entrypoint *sources* these scripts (`. "$FILE"`), it does not run
# them. So `exit` here would end the whole boot, and `set -e`/`set -u` would
# outlive this file and change how every later script behaves. Everything is
# therefore wrapped in a function, which can `return`, and nothing is set
# globally. A container that quietly stops after "-> Executing …" is this
# mistake.

foehn_nginx_image_cache() {
    script_name="nginx-image-cache"

    template_file="/opt/docker/etc/nginx/templates/image-cache.conf.template"
    output_file="/opt/docker/etc/nginx/vhost.common.d/70-image-cache.conf"

    # A clean slate: with no bucket there is nowhere to serve transforms from, and
    # every request should reach PHP, which is the local-disk arrangement.
    rm -f "$output_file"

    if [ -z "${S3_UPLOADS_BUCKET:-}" ]; then
        echo "ℹ️ NOTICE ($script_name): S3_UPLOADS_BUCKET is unset, /_image/ is answered by PHP every time."
            return 0
    fi

    # Derived exactly as the uploads proxy derives its own host, and overridable by
    # the same variable, so a CDN in front of one is a CDN in front of both.
    cache_prefix=""

    if [ -n "${S3_UPLOADS_PROXY_HOST:-}" ]; then
        cache_host="$S3_UPLOADS_PROXY_HOST"
        cache_scheme="${S3_UPLOADS_PROXY_SCHEME:-https}"
    else
        if [ -z "${S3_UPLOADS_ENDPOINT:-}" ]; then
            echo "🛑 ERROR ($script_name): S3_UPLOADS_BUCKET is set but neither S3_UPLOADS_ENDPOINT nor S3_UPLOADS_PROXY_HOST is." >&2
            return 1
        fi

        cache_scheme=$(echo "${S3_UPLOADS_ENDPOINT}" | sed -n 's|^\([a-z][a-z0-9+.-]*\)://.*|\1|p')
        cache_scheme="${cache_scheme:-https}"

        endpoint_host=$(echo "${S3_UPLOADS_ENDPOINT}" | sed -e 's|^[a-z][a-z0-9+.-]*://||' -e 's|/.*$||')

        # Comme le proxy de médias : MinIO en développement adresse par le chemin,
        # Tigris et OVH par le sous-domaine.
        if [ "${S3_UPLOADS_PATH_STYLE:-}" = "true" ]; then
            cache_host="$endpoint_host"
            cache_prefix="/$S3_UPLOADS_BUCKET"
        else
            cache_host="$S3_UPLOADS_BUCKET.$endpoint_host"
        fi
    fi

    cache_resolver="${S3_UPLOADS_PROXY_RESOLVER:-1.1.1.1 8.8.8.8}"

    sed \
        -e "s|\${IMAGE_CACHE_SCHEME}|$cache_scheme|g" \
        -e "s|\${IMAGE_CACHE_HOST}|$cache_host|g" \
        -e "s|\${IMAGE_CACHE_PREFIX}|$cache_prefix|g" \
        -e "s|\${IMAGE_CACHE_RESOLVER}|$cache_resolver|g" \
        "$template_file" > "$output_file"

    echo "ℹ️ NOTICE ($script_name): /_image/ served from $cache_scheme://$cache_host$cache_prefix"

    if [ "${LOG_OUTPUT_LEVEL:-}" = "debug" ]; then
        cat "$output_file"
    fi
}

foehn_nginx_image_cache
