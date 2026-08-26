#!/bin/sh
###############################################################################
# Schedules the database backup, and renders the credentials it runs with.
#
# Two things a cron job cannot do for itself: know when it should run, and read
# a password without putting it somewhere `ps` can see. Both are settled here,
# at boot, from the variables the site already defines.
#
# Scheduling is a symlink into `/etc/periodic/<schedule>/`. There is no crontab
# to write and no `crond` to start — the base image already runs one under
# supervisord, and busybox's stock root crontab already calls `run-parts` on
# those four directories. A symlink is the whole mechanism, which is also why
# turning backups off is a directory with nothing in it rather than a flag some
# script has to remember to honour.
#
# Nothing here is specific to where the database is. It reads `DB_HOST`,
# `DB_NAME`, `DB_USER` and `DB_PASSWORD`, the same secrets a site defines for
# WordPress, so a site keeps its backups unchanged whether the database is
# across the network or a unix socket in this container.
#
# A misconfiguration here stops the container, which is deliberate and is what
# the `60-` hook beside it already does. It reads as disproportionate — a typo
# in a backup variable taking a site off the air — until you price the other
# option: booting anyway leaves an operator who asked for backups looking at a
# green deploy and a site that has none, and finding out months later. Failing
# the boot instead fails the *deploy*, and a failed deploy on Fly rolls back to
# the machine that was already serving, so nothing goes down that was up. The
# only way to reach this is to have set `BACKUP_ENABLED=true` and not finished
# the job.
#
# @see /opt/docker/bin/foehn-backup
###############################################################################
# The upstream entrypoint *sources* these scripts (`. "$FILE"`), it does not run
# them. So `exit` here would end the whole boot, and `set -e`/`set -u` would
# outlive this file and change how every later script behaves. Everything is
# therefore wrapped in a function, which can `return`, and nothing is set
# globally. A container that quietly stops after "-> Executing …" is this
# mistake.

foehn_backup() {
    script_name="backup"

    # Not `/etc/my.cnf.d/`: `/etc/my.cnf` includes that directory, so a
    # `[client]` section left in it becomes the default identity of every
    # MariaDB client in the container — a bare `mariadb` would connect as the
    # backup user, and so would anything shelling out to the client. The jobs
    # pass `--defaults-file`, so this can live anywhere, and `/run` keeps the
    # password out of any image layer or volume.
    cnf_dir="/run/foehn"
    cnf_file="$cnf_dir/backup.cnf"
    schedules="hourly daily weekly monthly"

    # A clean slate. These are the switches: a site that sets `BACKUP_ENABLED`
    # back to false, or moves its schedule, must not keep the job it had before
    # — and on a container with a volume, yesterday's symlink would otherwise
    # outlive the configuration that asked for it.
    for period in $schedules; do
        rm -f "/etc/periodic/$period/foehn-backup" \
            "/etc/periodic/$period/foehn-backup-maintenance" \
            "/etc/periodic/$period/foehn-backup-verify"
    done

    rm -f "$cnf_file"

    if [ "${BACKUP_ENABLED:-false}" != "true" ]; then
        echo "ℹ️ NOTICE ($script_name): BACKUP_ENABLED is not true, nothing is scheduled."
        return 0
    fi

    schedule="${BACKUP_SCHEDULE:-daily}"

    # Checked against the four directories busybox's crontab actually calls, and
    # not merely used. A plausible-looking `BACKUP_SCHEDULE=fortnightly` would
    # otherwise be a symlink in a directory nothing ever runs: a site carrying a
    # backup job that never fires, and no error anywhere to say so.
    case " $schedules " in
        *" $schedule "*) ;;
        *)
            echo "🛑 ERROR ($script_name): BACKUP_SCHEDULE=\"$schedule\" is not one of: $schedules. The container will not start." >&2
            return 1
            ;;
    esac

    missing=""

    for variable in RESTIC_REPOSITORY RESTIC_PASSWORD DB_NAME DB_USER DB_PASSWORD; do
        eval "value=\${$variable:-}"

        if [ -z "$value" ]; then
            missing="$missing $variable"
        fi
    done

    if [ -n "$missing" ]; then
        echo "🛑 ERROR ($script_name): BACKUP_ENABLED=true but these are unset:$missing. The container will not start." >&2
        return 1
    fi

    # Created empty and locked down *before* anything is written into it, so the
    # password is never briefly readable between the write and the `chmod`.
    mkdir -p "$cnf_dir"
    chmod 700 "$cnf_dir"

    : > "$cnf_file"
    chmod 600 "$cnf_file"

    {
        echo "# Rendered at boot by entrypoint.d/80-$script_name.sh."
        echo "# Edits are lost on restart; change the DB_* variables instead."
        echo "[client]"
    } >> "$cnf_file"

    db_host="${DB_HOST:-localhost}"

    # Parsed the way `wpdb::parse_db_host()` parses it, so the dump connects to
    # the same server the site does. WordPress splits a socket off at the first
    # `:/` — `localhost:/run/mysqld/mysqld.sock` — which has to be recognised
    # before the `host:port` form, since it also contains a colon.
    case "$db_host" in
        *:/*)
            printf 'socket=/%s\n' "${db_host#*:/}" >> "$cnf_file"
            ;;
        /*)
            # A bare path is not something WordPress accepts, but it is what a
            # person writes when asked for a socket. Cheaper to honour than to
            # let a backup fail against a database the site can reach.
            printf 'socket=%s\n' "$db_host" >> "$cnf_file"
            ;;
        \[*\]:*)
            # `[fdaa:0:1::3]:3306`. Brackets are the only way an IPv6 address
            # can carry a port, which is why WordPress requires them too, and
            # the brackets themselves are not part of the address.
            address="${db_host%]:*}"

            printf 'host=%s\n' "${address#[}" >> "$cnf_file"
            printf 'port=%s\n' "${db_host##*]:}" >> "$cnf_file"
            ;;
        *:*:*)
            # Two colons or more and no brackets: an IPv6 address, all of it.
            # `fdaa:0:1::3` ends in digits, so reading it as `host:port` would
            # take the last hextet for a port number and quietly connect to a
            # different address — on Fly, where these are the internal
            # addresses, to a different machine.
            address="${db_host#[}"

            printf 'host=%s\n' "${address%]}" >> "$cnf_file"
            ;;
        *:*)
            # One colon: `host:port`, and only when what follows is a number.
            port="${db_host##*:}"

            case "$port" in
                '' | *[!0-9]*)
                    printf 'host=%s\n' "$db_host" >> "$cnf_file"
                    ;;
                *)
                    printf 'host=%s\n' "${db_host%:*}" >> "$cnf_file"
                    printf 'port=%s\n' "$port" >> "$cnf_file"
                    ;;
            esac
            ;;
        *)
            printf 'host=%s\n' "$db_host" >> "$cnf_file"
            ;;
    esac

    # Quoted and escaped, because an option file is not a shell. An unquoted
    # value ends at the first `#`, which turns a password containing one into a
    # shorter password and an authentication failure nobody can explain by
    # looking at the secret.
    for pair in "user=$DB_USER" "password=$DB_PASSWORD"; do
        key="${pair%%=*}"
        value="${pair#*=}"
        value=$(printf '%s' "$value" | sed -e 's|\\|\\\\|g' -e 's|"|\\"|g')

        printf '%s="%s"\n' "$key" "$value" >> "$cnf_file"
    done

    ln -sf /opt/docker/bin/foehn-backup "/etc/periodic/$schedule/foehn-backup"

    echo "ℹ️ NOTICE ($script_name): $DB_NAME is dumped $schedule into ${RESTIC_REPOSITORY%%\?*}."

    # Weekly whatever the backup's cadence, both of them: pruning is priced in
    # object-storage traffic and a restore drill in a full copy of the database,
    # and neither is worth doing hourly on a site this size.
    if [ "${BACKUP_MAINTENANCE_ENABLED:-true}" = "true" ]; then
        ln -sf /opt/docker/bin/foehn-backup-maintenance /etc/periodic/weekly/foehn-backup-maintenance
    else
        echo "⚠️ NOTICE ($script_name): maintenance is off, so nothing ever reclaims the space forgotten snapshots hold."
    fi

    if [ "${BACKUP_VERIFY_ENABLED:-true}" = "true" ]; then
        ln -sf /opt/docker/bin/foehn-backup-verify /etc/periodic/weekly/foehn-backup-verify
    else
        echo "⚠️ NOTICE ($script_name): the weekly restore check is off, so nothing proves these backups can be restored."
    fi
}

foehn_backup
