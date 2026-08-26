#!/bin/sh
###############################################################################
# The supervised database.
#
# By the time supervisord runs this, `90-mariadb-handover.sh` has already
# stopped the server the entrypoints were using, so the normal path is simply
# `exec mariadbd`: one start, overlapping PHP-FPM's own, with nothing to wait
# for.
#
# The shutdown below is for the path that is not normal — supervisord restarting
# this program after `mariadbd` died. A server that was killed rather than
# stopped can leave a socket file behind, and one that is somehow still alive
# must not be joined by a second on the same data directory.
#
# @see ../entrypoint.d/30-mariadb.sh, ../entrypoint.d/90-mariadb-handover.sh
###############################################################################
set -eu

socket="/run/mysqld/mysqld.sock"

# `ping` rather than `[ -S "$socket" ]`: the socket file outlives a killed
# server, and asking a file that nothing is listening on to shut down would
# block until it timed out.
#
# `--no-defaults --user=root` and not a bare invocation. A MariaDB client reads
# `/etc/my.cnf` and everything it includes before it reads its arguments, so any
# `[client]` section anywhere in `/etc/my.cnf.d/` decides who this connects as.
# Left to chance, the shutdown arrives as the site's own database user, which
# has no SHUTDOWN privilege — the handover then fails, supervisord restarts this
# script, and the container spends its life in that loop. `root` over the socket
# is authenticated by the OS user, so there is no password to supply.
if mariadb-admin --no-defaults --protocol=socket --socket="$socket" --user=root ping >/dev/null 2>&1; then
    echo "ℹ️ NOTICE (mariadb): a server is already listening, stopping it first."

    mariadb-admin --no-defaults --protocol=socket --socket="$socket" --user=root shutdown

    waited=0

    while [ -S "$socket" ] && [ "$waited" -lt 60 ]; do
        sleep 1
        waited=$((waited + 1))
    done

    if [ -S "$socket" ]; then
        echo "🛑 ERROR (mariadb): the running server did not stop within 60s." >&2
        exit 1
    fi
fi

# A socket file left behind by a server that was killed. `mariadbd` refuses to
# start beside one it did not create.
rm -f "$socket"

# `exec`, so supervisord supervises `mariadbd` itself and not a shell holding
# its hand. Without it, every signal stops at the shell and a stop request
# leaves the database running — which on the way down is the difference between
# a clean shutdown and the crash recovery the next boot pays for.
exec mariadbd --user=mysql
