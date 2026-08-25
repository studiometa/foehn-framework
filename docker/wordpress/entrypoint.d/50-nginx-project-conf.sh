#!/bin/sh
###############################################################################
# Picks up the NGINX configuration a project leaves in config/nginx/.
#
# A convention rather than one Dockerfile line per file: a project writes its
# rules into `config/nginx/*.conf` and they are served, with nothing declared
# anywhere. That is also where `wp foehn cache:config --server=nginx --write`
# writes the page cache rules, so enabling the page cache is generating a file.
#
# Included in the `server` context, after the upstream's `10-*` blocks
# (`location /`, PHP) and before the `60-`/`70-` blocks the later entrypoints
# render — so a project can add locations, not rewrite ours.
###############################################################################
# The upstream entrypoint *sources* these scripts (`. "$FILE"`), it does not run
# them. So `exit` here would end the whole boot, and `set -e`/`set -u` would
# outlive this file and change how every later script behaves. Everything is
# therefore wrapped in a function, which can `return`, and nothing is set
# globally. A container that quietly stops after "-> Executing …" is this
# mistake.

foehn_nginx_project_conf() {
    script_name="nginx-project-conf"

    source_dir="${FOEHN_NGINX_CONF_DIR:-/app/config/nginx}"
    target_dir="/opt/docker/etc/nginx/vhost.common.d"

    # A clean slate: a file from an earlier build must not outlive its deletion from
    # the repository.
    rm -f "$target_dir"/50-project-*.conf

    if [ ! -d "$source_dir" ]; then
            return 0
    fi

    count=0

    for file in "$source_dir"/*.conf; do
        [ -e "$file" ] || continue
        cp "$file" "$target_dir/50-project-$(basename "$file")"
        count=$((count + 1))
    done

    if [ "$count" -gt 0 ]; then
        echo "ℹ️ NOTICE ($script_name): $count file(s) taken from ${source_dir#/app/}."
    fi
}

foehn_nginx_project_conf
