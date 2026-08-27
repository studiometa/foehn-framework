#!/bin/sh
###############################################################################
# Builds the demo site on a volume that does not have one yet.
#
# The demo is a portfolio — six series, thirty photographs, four pages, three
# menus — and all of it is in this repository: `database/seed.php` writes the
# content, `database/restore-media.php` puts the photographs where the database
# says they are, and a rewrite flush is what makes the registered rules answer a
# URL. So there is nothing to import from anywhere, and no first-run wizard for
# anyone to click through: a machine with an empty volume becomes the site by
# itself, and a machine whose volume already holds it does nothing at all.
#
# The content steps are `database/restore.sh`'s, in its order and for its
# reasons, with one of them dropped. Seeding rather than importing
# `demo.sql.gz`, because a dump remembers the host it was taken on —
# `foehn-demo.ddev.site` — and every guid, menu item and serialized option in it
# names that host. `restore.sh` follows an import with `fix-urls.php` for exactly
# that, and says itself that the step is a no-op on the seed path: seeding builds
# against `WP_HOME` and leaves nothing to move. So it is not here.
#
# `35-`, and the number is the whole design of this file:
#
#   - after `30-mariadb.sh`, which starts the provisioning MariaDB, creates the
#     database and the user from the `DB_*` secrets, and points `DB_HOST` at the
#     socket. Before it there is no database to install into.
#   - before `40-nginx-page-cache.sh`, which generates the NGINX page cache rules
#     by booting WordPress. On a first boot that only produces rules if there is
#     a site to boot, so seeding first is what gives the demo its fast path from
#     the first request rather than from the next deploy.
#   - well before `90-mariadb-handover.sh`, which stops that server again. After
#     it, and until supervisord has started the one that lasts, there is no
#     database in this container. This script has to be inside that window and
#     this is the only window there is.
#
# @see ../../database/README.md, ../../../../docker/wordpress/README.md
###############################################################################
# The upstream entrypoint *sources* these scripts (`. "$FILE"`), it does not run
# them. So `exit` here would end the whole boot, and `set -e`/`set -u` would
# outlive this file and change how every later script behaves. Everything is
# therefore wrapped in a function, which can `return`, and nothing is set
# globally. A container that quietly stops after "-> Executing …" is this
# mistake.

# Refuses to boot when the site could not serve a page anyway.
#
# `wp-config.php` answers a production request with HTTP 500 and one line of text
# while any of the eight security keys is missing, and exempts WP-CLI from that
# check — which is exactly the combination that makes this worth checking here.
# Everything below this would succeed, `/healthcheck` is answered by PHP-FPM and
# says nothing about WordPress, so the deploy would go green and every page on
# the site would be a 500. Failing the boot instead fails the deploy, and Fly
# rolls back to the machine already serving.
foehn_demo_require_salts() {
    script_name="demo-seed"

    missing=""

    for variable in AUTH_KEY SECURE_AUTH_KEY LOGGED_IN_KEY NONCE_KEY \
        AUTH_SALT SECURE_AUTH_SALT LOGGED_IN_SALT NONCE_SALT; do
        eval "value=\${$variable:-}"

        if [ -z "$value" ]; then
            missing="$missing $variable"
        fi
    done

    if [ -n "$missing" ]; then
        echo "🛑 ERROR ($script_name): these WordPress security keys are unset, and wp-config.php answers every production request with a 500 while they are:$missing. The container will not start." >&2
        return 1
    fi
}

foehn_demo_seed() {
    script_name="demo-seed"

    # As `application`, the user PHP runs as, and never as root. Every command
    # below boots WordPress, and booting WordPress writes Føhn's discovery cache
    # under `wp-content/cache/`; written by root, those files are files PHP-FPM
    # can no longer replace, and the site loses the cache it just built. The
    # photographs land in `wp-content/uploads/` on a site with no bucket, and go
    # the same way. Same reasoning as `40-nginx-page-cache.sh`.
    #
    # The command is single-quoted at every call site, so the container's
    # environment is expanded by the shell `su` starts and not by this one. That
    # is what keeps `DEMO_ADMIN_PASSWORD` out of this script's own command line —
    # and out of the boot log, which is the only place a Fly secret must never
    # reach.
    #
    # Prefixed, like the two functions around it: these scripts are sourced into
    # one shell, so a helper called `run` or `wp` would still be defined when the
    # next entrypoint ran and would shadow whatever that one meant by the name.
    foehn_demo_run() {
        su -s /bin/sh "${APPLICATION_USER:-application}" -c "cd /app && $1"
    }

    # `core is-installed` asks the database whether the tables are there, which is
    # the only honest test: the volume outlives the image, so an image that has
    # never run may well find a site that has been up for months. A restart, a
    # redeploy and a machine rebuilt from the same volume all land here.
    if foehn_demo_run 'wp core is-installed' >/dev/null 2>&1; then
        echo "ℹ️ NOTICE ($script_name): the volume already holds an installed site, seeding nothing."
        return 0
    fi

    # No password, no site. Inventing one would put an admin account with a
    # guessable password on a public demo, and defaulting to something printed in
    # the log would be the same thing more slowly. The site stays uninstalled and
    # says why, which is a state somebody can fix with one command.
    if [ -z "${DEMO_ADMIN_PASSWORD:-}" ]; then
        echo "ℹ️ NOTICE ($script_name): DEMO_ADMIN_PASSWORD is unset, so the site is left uninstalled. Set it with 'fly secrets set DEMO_ADMIN_PASSWORD=...' and deploy again."
        return 0
    fi

    # Measured at 36 s on a 1 GB container with uploads on local disk, most of it
    # in the thirty attachments' sub-sizes; a bucket puts a network round trip
    # behind each of those and costs more. Said out loud because it happens once,
    # on a boot Fly is waiting on, and a silent half-minute in a deploy log is a
    # half-minute somebody spends wondering.
    echo "ℹ️ NOTICE ($script_name): empty volume, building the portfolio. This takes about half a minute; every boot after it takes none."

    # `--url` from `WP_HOME`, which `wp-config.php` has already turned into a
    # constant: WordPress writes that origin into every permalink, menu item and
    # guid it creates below, and a site seeded against the wrong one is a site
    # that redirects visitors somewhere it does not serve.
    if ! foehn_demo_run 'wp core install \
        --url="$WP_HOME" \
        --title="Føhn Demo" \
        --admin_user=admin \
        --admin_email=admin@example.com \
        --admin_password="$DEMO_ADMIN_PASSWORD" \
        --skip-email'; then
        echo "🛑 ERROR ($script_name): WordPress could not be installed. The container will not start." >&2
        return 1
    fi

    # Føhn lives in the theme, so until this runs the site has registered no post
    # type, no block and no rewrite rule, and `wp foehn` is not a command.
    if ! foehn_demo_run 'wp theme activate demo-theme'; then
        echo "🛑 ERROR ($script_name): the demo theme could not be activated. The container will not start." >&2
        return 1
    fi

    # With a bucket, `humanmade/s3-uploads` replaces the uploads directory with an
    # `s3://` stream wrapper, and it has to be active *before* anything is
    # uploaded: `get_attached_file()` returns the path the plugin decides, and a
    # photograph written while it is inactive is a photograph on a disk this
    # container throws away. Without a bucket the plugin has no endpoint to talk
    # to and is left alone — uploads go to local disk, which is what the runtime
    # image's NGINX rules also fall back to.
    if [ -n "${S3_UPLOADS_BUCKET:-}" ] && ! foehn_demo_run 'wp plugin activate s3-uploads'; then
        echo "🛑 ERROR ($script_name): S3_UPLOADS_BUCKET is set but the s3-uploads plugin could not be activated, so the photographs would land on a disk this container discards. The container will not start." >&2
        return 1
    fi

    # The content. Idempotent by slug, though nothing here runs it twice.
    if ! foehn_demo_run 'wp eval-file database/seed.php'; then
        echo "🛑 ERROR ($script_name): the demo content could not be seeded. The container will not start." >&2
        return 1
    fi

    # The photographs, put back at the path each attachment claims and their
    # sub-sizes rebuilt from the original — which is also what proves the original
    # arrived. On this path the seed has just written them, so what this really
    # checks is that they are readable through whatever the uploads directory
    # turned out to be.
    if ! foehn_demo_run 'wp eval-file database/restore-media.php'; then
        echo "🛑 ERROR ($script_name): the photographs could not be restored. The container will not start." >&2
        return 1
    fi

    # Registered rewrite rules do nothing until WordPress has flushed them once,
    # and `#[AsRewriteRule]` is one of the attributes this site exists to
    # demonstrate. Without this, `/_health` is a 404 on a site that looks fine.
    if ! foehn_demo_run 'wp foehn rewrite:flush'; then
        echo "🛑 ERROR ($script_name): the rewrite rules could not be flushed. The container will not start." >&2
        return 1
    fi

    echo "ℹ️ NOTICE ($script_name): the portfolio is up at ${WP_HOME:-the configured URL}."
}

foehn_demo_require_salts && foehn_demo_seed
