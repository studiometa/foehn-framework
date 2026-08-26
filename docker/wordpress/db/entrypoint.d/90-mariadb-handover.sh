#!/bin/sh
###############################################################################
# Stops the provisioning database, so supervisord starts the only one.
#
# `30-mariadb.sh` leaves a server running because the entrypoints between here
# and there need one — `40-nginx-page-cache.sh` generates its rules by booting
# WordPress. This is the other end of that: the last hook in the directory, once
# nothing else is going to ask for a database.
#
# It exists as its own hook, rather than as the first thing
# `service.d/mariadb.sh` does, because of *when* rather than what. supervisord
# starts programs in priority order but does not wait for one to be ready before
# starting the next, so a shutdown-then-start inside the service script runs
# beside PHP-FPM coming up rather than before it. Measured, that left about
# 1.5 s where the healthcheck was already green — where Fly would route real
# traffic — and the database was still restarting underneath it. Visitors get
# "Error establishing a database connection" for those seconds of a deploy.
#
# Stopping here instead means supervisord starts a database that is not fighting
# a shutdown, and its ~2 s of InnoDB startup overlaps the ~3.5 s PHP-FPM and
# NGINX take to answer at all. The database is then ready before anything can
# ask it a question, and the window is gone rather than merely short.
#
# @see 30-mariadb.sh, ../service.d/mariadb.sh
###############################################################################
# The upstream entrypoint *sources* these scripts (`. "$FILE"`), it does not run
# them. So `exit` here would end the whole boot, and `set -e`/`set -u` would
# outlive this file and change how every later script behaves. Everything is
# therefore wrapped in a function, which can `return`, and nothing is set
# globally. A container that quietly stops after "-> Executing …" is this
# mistake.

foehn_mariadb_handover() {
    script_name="mariadb-handover"

    socket="/run/mysqld/mysqld.sock"

    # `--no-defaults --user=root`: a MariaDB client reads `/etc/my.cnf` and
    # everything it includes before its own arguments, so a stray `[client]`
    # section decides who this connects as. A shutdown arriving as the site's
    # database user is refused — it has no SHUTDOWN privilege — and the
    # provisioning instance would still be holding the data directory when
    # supervisord tried to start a second server on it.
    if ! mariadb-admin --no-defaults --protocol=socket --socket="$socket" --user=root ping >/dev/null 2>&1; then
        return 0
    fi

    mariadb-admin --no-defaults --protocol=socket --socket="$socket" --user=root shutdown

    # Waited for, not assumed. Two servers on one data directory is how a data
    # directory is corrupted, and InnoDB's shutdown flushes the buffer pool
    # first — which is the whole reason it is a clean one.
    waited=0

    while [ -S "$socket" ] && [ "$waited" -lt 60 ]; do
        sleep 1
        waited=$((waited + 1))
    done

    if [ -S "$socket" ]; then
        echo "🛑 ERROR ($script_name): the provisioning database did not stop within 60s. The container will not start." >&2
        return 1
    fi

    echo "ℹ️ NOTICE ($script_name): provisioning database stopped, supervisord takes it from here."
}

foehn_mariadb_handover
