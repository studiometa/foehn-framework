###############################################################################
# What the three backup jobs share: the switch, the configuration check, the
# lock and the heartbeat.
#
# Sourced, never executed — so it declares functions and sets variables, and
# does nothing on its own. The jobs that source it are run by cron, not by the
# entrypoint, so unlike `entrypoint.d/*.sh` they are free to `set -eu` and to
# `exit`, and they do.
#
# @see foehn-backup, foehn-backup-maintenance, foehn-backup-verify
###############################################################################

# Where `entrypoint.d/80-backup.sh` renders the credentials, and the lock every
# job takes so two of them never touch the repository at once.
#
# Deliberately *not* `/etc/my.cnf.d/`. That directory is `!includedir`-ed by
# `/etc/my.cnf`, so a `[client]` section in it is not this feature's
# configuration — it is the default identity of every MariaDB client in the
# container. Put the backup credentials there and a bare `mariadb` silently
# connects as the backup user, and so does anything else that shells out to the
# client. Every job here passes `--defaults-file`, which reads this file and no
# other, so it works anywhere; `/run` also means the password is never written
# to an image layer or a volume.
FOEHN_BACKUP_DIR="/run/foehn"
FOEHN_BACKUP_CNF="$FOEHN_BACKUP_DIR/backup.cnf"
FOEHN_BACKUP_LOCK="$FOEHN_BACKUP_DIR/backup.lock"

# cron writes a job's output to the container log with no context of its own, so
# each line says which job wrote it and when. `date -u`, because a container's
# local time is rarely the one reading the logs.
foehn_backup_say() {
    printf '%s [foehn-%s] %s\n' \
        "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" "${FOEHN_BACKUP_JOB:-backup}" "$*"
}

foehn_backup_die() {
    printf '%s [foehn-%s] 🛑 ERROR: %s\n' \
        "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" "${FOEHN_BACKUP_JOB:-backup}" "$*" >&2
    exit 1
}

# Opt-in, and off by default. The jobs are only ever reachable through symlinks
# the entrypoint declines to create when this is false, so this second check is
# redundant on a correctly provisioned container — which is the point. A symlink
# left behind by hand, or a job invoked directly, still respects the switch.
foehn_backup_is_enabled() {
    [ "${BACKUP_ENABLED:-false}" = "true" ]
}

# Restic reads `RESTIC_REPOSITORY`, `RESTIC_PASSWORD` and the AWS credentials
# from the environment by itself. What this adds is failing at the first tick,
# loudly, rather than leaving a site with a repository that never appears and no
# indication of why.
foehn_backup_require_config() {
    [ -n "${RESTIC_REPOSITORY:-}" ] || foehn_backup_die "RESTIC_REPOSITORY is unset."
    [ -n "${RESTIC_PASSWORD:-}" ] || foehn_backup_die "RESTIC_PASSWORD is unset."
    [ -n "${DB_NAME:-}" ] || foehn_backup_die "DB_NAME is unset."
    [ -r "$FOEHN_BACKUP_CNF" ] || foehn_backup_die "$FOEHN_BACKUP_CNF is missing, so the 80-backup entrypoint did not run."
}

# One lock for all three jobs, because they contend for one repository. The
# schedules do collide: busybox's crontab fires the weekly run-parts at 03:00 on
# Saturday and the hourly one at 03:00 too, so an hourly backup and the weekly
# maintenance meet once a week by construction.
#
# The caller chooses what to do about it — `-n` to give up, `-w` to wait — since
# a backup skipping a tick is nothing and maintenance skipping a week is a
# repository that never gets pruned.
#
# `-w` is why the image installs util-linux's `flock` over the busybox applet
# that already answered to that name: busybox's understands `-sxun` and nothing
# else, so it can fail at once or block for ever, with nothing in between. A
# weekly job that blocks for ever is worse than one that skips.
foehn_backup_lock() {
    exec 9>"$FOEHN_BACKUP_LOCK"
    flock "$@" 9
}

# Restic's notion of which machine a snapshot came from. On Fly the machine ID
# changes with every deploy while the app name does not, and a `--host` that
# changes every deploy would put each snapshot in its own `forget` group, where
# a retention policy can never expire anything.
foehn_backup_host() {
    echo "${FLY_APP_NAME:-$(hostname)}"
}

# Pinged last and only on success — a heartbeat sent before the work is what
# turns a monitor into decoration. Its own failure is not the backup's failure:
# the snapshot exists either way, and exiting non-zero here would report a good
# backup as a broken one.
foehn_backup_heartbeat() {
    url="$1"

    [ -n "$url" ] || return 0

    if wget -q -T 10 -O /dev/null "$url"; then
        foehn_backup_say "heartbeat pinged."
    else
        foehn_backup_say "⚠️ the heartbeat could not be reached; the backup itself succeeded."
    fi
}
