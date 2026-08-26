#!/bin/sh
###############################################################################
# Brings up the database that lives in this container, on the `-db` variant.
#
# Initialises the data directory the first time, creates the database and the
# user from the `DB_*` secrets the site already defines, and points `DB_HOST` at
# the socket so moving to this image is a change of one line in a Dockerfile and
# nothing else. Every boot after the first finds all of it already done.
#
# `30-`, ahead of everything: `40-nginx-page-cache.sh` generates its rules by
# booting WordPress, which needs a database, so the database has to exist before
# it runs. The server started here stays up for the rest of provisioning and is
# stopped again by `90-mariadb-handover.sh`, after which supervisord starts the
# one that lasts.
#
# Only in the `-db` image. The plain image never has this file, which is what
# makes the variant additive rather than a runtime flag: there is no mode to set
# wrong, and no site can start a database by accident.
#
# @see ../service.d/mariadb.sh
###############################################################################
# The upstream entrypoint *sources* these scripts (`. "$FILE"`), it does not run
# them. So `exit` here would end the whole boot, and `set -e`/`set -u` would
# outlive this file and change how every later script behaves. Everything is
# therefore wrapped in a function, which can `return`, and nothing is set
# globally. A container that quietly stops after "-> Executing …" is this
# mistake.

foehn_mariadb() {
    script_name="mariadb"

    datadir="/var/lib/mysql"
    socket="/run/mysqld/mysqld.sock"
    conf_file="/etc/my.cnf.d/zz-foehn-embedded.cnf"

    missing=""

    for variable in DB_NAME DB_USER DB_PASSWORD; do
        eval "value=\${$variable:-}"

        if [ -z "$value" ]; then
            missing="$missing $variable"
        fi
    done

    if [ -n "$missing" ]; then
        echo "🛑 ERROR ($script_name): the -db image needs these to create its database, and they are unset:$missing. The container will not start." >&2
        return 1
    fi

    # `zz-`, because `/etc/my.cnf` reads `/etc/my.cnf.d` in alphabetical order
    # and the last setting of an option wins. Anything the distribution ships
    # comes earlier in the alphabet than this does.
    #
    # Only what has to be per-site or per-container. Alpine's own
    # `mariadb-server.cnf` already sets `skip-networking`, so the server listens
    # on the socket and nothing else — there is no port to reach, from inside
    # the container or outside it.
    {
        echo "# Rendered at boot by entrypoint.d/30-$script_name.sh."
        echo "# Edits are lost on restart."
        echo "[mysqld]"
        # Sized for a container, not a server. The default 128M is a guess made
        # for a machine that does nothing else; this shares 1 GB with PHP-FPM
        # and NGINX. 192M holds several times over the databases this variant is
        # meant for.
        echo "innodb_buffer_pool_size = ${MARIADB_BUFFER_POOL_SIZE:-192M}"
        # Off by default in this package already, and stated anyway because it
        # is the setting that would break the memory budget if a future default
        # flipped: performance_schema costs of the order of a hundred megabytes,
        # which is most of the headroom this whole variant depends on.
        echo "performance_schema = off"
    } > "$conf_file"

    # The socket's directory does not survive in the image, and `/run` is a
    # fresh tmpfs on every boot.
    mkdir -p "$(dirname "$socket")"
    chown mysql:mysql "$(dirname "$socket")"

    # `mysql/` inside the data directory, not the directory itself: a Fly volume
    # arrives mounted and empty but very much existing, and `lost+found` can be
    # in it. The system tables are the thing whose absence means "never
    # initialised".
    if [ ! -d "$datadir/mysql" ]; then
        echo "ℹ️ NOTICE ($script_name): empty data directory, initialising."

        # `--auth-root-authentication-method=socket` so `root` is whoever
        # connects over the socket as root, and there is no root password to
        # store, rotate or leak. Nothing outside this container can reach the
        # socket at all.
        mariadb-install-db \
            --user=mysql \
            --datadir="$datadir" \
            --auth-root-authentication-method=socket \
            --skip-test-db \
            > /dev/null || {
            echo "🛑 ERROR ($script_name): could not initialise $datadir." >&2
            return 1
        }
    fi

    # Started here and left running: the entrypoints after this one need a
    # database, and supervisord has not started anything yet.
    mariadbd --user=mysql &

    waited=0

    while ! mariadb-admin --no-defaults --protocol=socket --socket="$socket" --user=root ping >/dev/null 2>&1; do
        if [ "$waited" -ge 60 ]; then
            echo "🛑 ERROR ($script_name): the database did not accept connections within 60s." >&2
            return 1
        fi

        sleep 1
        waited=$((waited + 1))
    done

    echo "ℹ️ NOTICE ($script_name): the database is up on $socket (${waited}s)."

    # Escaped for SQL, not for a shell. A password is a string literal here, and
    # a backslash or a quote in one would otherwise end the literal early and
    # turn a valid password into a syntax error — or, worse, into a different
    # valid statement.
    escaped_password=$(printf '%s' "$DB_PASSWORD" | sed -e "s|\\\\|\\\\\\\\|g" -e "s|'|\\\\'|g")

    # The scratch schema the weekly restore drill restores into. Granted here
    # because this is the only place that knows the database user was just
    # created, and a drill that cannot create its schema is a drill that never
    # runs. The name is built exactly as `foehn-backup-verify` builds it.
    verify_schema="$(printf '%s' "$DB_NAME" | cut -c1-50)_foehn_verify"

    # Every statement is idempotent, because this runs on every boot and only
    # the first one is a fresh database. `IF NOT EXISTS` creates the user, and
    # the `ALTER` after it means a rotated `DB_PASSWORD` secret takes effect on
    # the next deploy instead of locking the site out of its own database.
    # `--no-defaults`, for the same reason the service script uses it: a
    # client reads `/etc/my.cnf` and its includes before its arguments, and
    # these statements need `root`, not whoever a stray `[client]` section
    # names.
    mariadb --no-defaults --protocol=socket --socket="$socket" --user=root <<SQL || {
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$escaped_password';
ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$escaped_password';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
GRANT ALL PRIVILEGES ON \`$verify_schema\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL
        echo "🛑 ERROR ($script_name): could not create the database or its user." >&2
        return 1
    }

    # The whole point of the variant, in one line: the site keeps the `DB_*`
    # secrets it already had, and this is what makes them point at the database
    # in this container instead of the app that used to hold it.
    #
    # Plain `localhost`, not `localhost:$socket`. Both reach the same server —
    # the client library and `php/zz-embedded-db.ini` agree on where the socket
    # is — but only `localhost` is understood by everything. `wp config create`
    # runs its own connection check that does not parse the `host:/socket` form
    # `wpdb` understands, and fails on it.
    #
    # Exported rather than written anywhere: this shell goes on to exec
    # supervisord, so PHP-FPM, cron and every entrypoint after this one inherit
    # it. That includes `80-backup.sh`, which is how backups follow the database
    # into the container without a site changing anything.
    if [ -n "${DB_HOST:-}" ] && [ "$DB_HOST" != "localhost" ]; then
        echo "ℹ️ NOTICE ($script_name): DB_HOST was \"$DB_HOST\"; the -db image serves the database itself, so it is now localhost over $socket."
    fi

    export DB_HOST="localhost"
}

foehn_mariadb
