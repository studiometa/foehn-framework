#!/usr/bin/env bash
#
# Mount the demo site: database, photographs, rewrite rules.
#
#     ./database/restore.sh
#
# Run from packages/demo with ddev started. Takes a site that has WordPress
# installed and nothing else, and leaves it serving the portfolio.
#
# Three steps, because the site is in three places:
#
#   demo.sql.gz     posts, meta, options, menus — everything WordPress stores
#   media/          the photographs themselves, which the dump cannot carry
#                   because the demo offloads uploads to object storage
#   rewrite rules   never dumped, since they are derived from what is registered
#
# Whatever the URL of the site the dump came from, the site is served at whatever
# this ddev configuration answers on, so both `.env` and the database are moved onto
# it first.
#
# Without the dump it seeds from scratch instead, which produces the same site and
# takes longer.

set -euo pipefail

cd "$(dirname "$0")/.."

dump="database/demo.sql.gz"

fail() {
	printf '\n✗ %s\n' "$1" >&2
	exit 1
}

ddev describe >/dev/null 2>&1 || fail 'ddev is not running here — start it first'

# What this project is reachable at, which depends on its name and on the router
# domain — neither of which the repository can know. Only the container has it, and
# wp-cli prints a deprecation notice ahead of everything, hence the tail.
# shellcheck disable=SC2016  # the container expands it, not this shell
primary="$(ddev exec 'printf %s "$DDEV_PRIMARY_URL"' 2>/dev/null | tail -n1 | tr -d '\r')"
[ -n "$primary" ] || fail 'ddev did not report a DDEV_PRIMARY_URL'

# wp-config defines WP_HOME from .env and a constant beats the stored option, so the
# database alone cannot move the site. WP_SITEURL is written as ${WP_HOME}/wp and
# follows on its own.
[ -f .env ] || fail 'no .env here — copy .env.example first'

if ! grep -qx "WP_HOME=$primary" .env; then
	sed -i "s|^WP_HOME=.*|WP_HOME=$primary|" .env
	printf '✓ WP_HOME set to %s\n' "$primary"
fi

if [ -f "$dump" ]; then
	printf '→ importing %s\n' "$dump"
	ddev import-db --file="$dump" >/dev/null
	printf '✓ database imported\n'
else
	printf '→ no dump found, seeding from scratch\n'
	ddev exec 'cd /var/www/html && wp eval-file database/seed.php'
fi

# A dump remembers the host it was taken on, and it is not this one unless the
# project happens to be configured the same way. Seeding from scratch builds against
# WP_HOME and leaves nothing to move, so this is a no-op there.
ddev exec 'cd /var/www/html && wp eval-file database/fix-urls.php'

# The bucket is not in the repository, so the originals are copied back to whatever
# path each attachment claims and the sub-sizes are rebuilt from them.
ddev exec 'cd /var/www/html && wp eval-file database/restore-media.php'

# Registered rules do nothing until WordPress has flushed them once, and a freshly
# imported database has whatever the dump's site had.
ddev exec 'cd /var/www/html && wp foehn rewrite:flush' >/dev/null
printf '✓ rewrite rules flushed\n'

url="$(ddev exec 'cd /var/www/html && wp option get home' 2>/dev/null | tail -n1 | tr -d '\r')"
printf '\n  %s\n' "$url"
