#!/bin/sh
###############################################################################
# Schedules WordPress's cron, because the page cache stops it running itself.
#
# WordPress's pseudo-cron fires on page loads. This image serves cache hits from
# NGINX without reaching PHP, so the better the cache works the less often cron
# runs — on a quiet site behind a warm cache, it stops. That is not a tuning
# problem to leave to each project: it is caused by the image, so the image
# fixes it.
#
# On unless a site says otherwise, unlike the backups beside it. Backups need a
# bucket, credentials and a password that does not exist yet, so they cannot
# work until someone sets them up; this needs nothing, and a site that does not
# get it is quietly worse off. `FOEHN_CRON_ENABLED=false` opts out.
#
# The same mechanism as the backups: a symlink into `/etc/periodic/`, which is
# already called by busybox's stock crontab under the `crond` the base image
# already supervises. Nothing to write, and switching it off is an empty
# directory rather than a flag some script has to remember to honour.
#
# `85-`, after `80-backup.sh`, so the two read in the order they run.
#
# @see /opt/docker/bin/foehn-cron
###############################################################################
# The upstream entrypoint *sources* these scripts (`. "$FILE"`), it does not run
# them. So `exit` here would end the whole boot, and `set -e`/`set -u` would
# outlive this file and change how every later script behaves. Everything is
# therefore wrapped in a function, which can `return`, and nothing is set
# globally. A container that quietly stops after "-> Executing …" is this
# mistake.

foehn_wp_cron() {
    script_name="wp-cron"

    schedules="15min hourly daily weekly monthly"

    # A clean slate: a site that turns this off, or moves its cadence, must not
    # keep the job it had before.
    for period in $schedules; do
        rm -f "/etc/periodic/$period/foehn-cron"
    done

    if [ "${FOEHN_CRON_ENABLED:-true}" != "true" ]; then
        echo "ℹ️ NOTICE ($script_name): FOEHN_CRON_ENABLED=false, WordPress runs its own cron on page loads."
        return 0
    fi

    schedule="${FOEHN_CRON_SCHEDULE:-15min}"

    # Checked against the directories busybox's crontab actually calls. An
    # unrecognised value would otherwise be a symlink somewhere nothing runs: a
    # site that believes it has cron and does not.
    case " $schedules " in
        *" $schedule "*) ;;
        *)
            echo "🛑 ERROR ($script_name): FOEHN_CRON_SCHEDULE=\"$schedule\" is not one of: $schedules. The container will not start." >&2
            return 1
            ;;
    esac

    ln -sf /opt/docker/bin/foehn-cron "/etc/periodic/$schedule/foehn-cron"

    # Exported, not merely defaulted. `wp-config.php` reads this to decide
    # whether to set `DISABLE_WP_CRON`, and it is PHP-FPM that reads it — so the
    # value has to reach PHP, not just this script. This shell goes on to exec
    # supervisord, which is PHP-FPM's parent, so exporting here is what carries
    # it. Without this the default is on in the image and off in wp-config, and
    # both mechanisms run: the scheduled job doing the work and the pseudo-cron
    # still spawning a loopback request on every uncached page load.
    export FOEHN_CRON_ENABLED=true

    echo "ℹ️ NOTICE ($script_name): scheduled events run every $schedule, and WordPress's own cron is off."
}

foehn_wp_cron
