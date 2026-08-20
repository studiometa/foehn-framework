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

if [ -f "$dump" ]; then
	printf '→ importing %s\n' "$dump"
	ddev import-db --file="$dump" >/dev/null
	printf '✓ database imported\n'
else
	printf '→ no dump found, seeding from scratch\n'
	ddev exec 'cd /var/www/html && wp eval-file database/seed.php'
fi

# The bucket is not in the repository, so the originals are copied back to whatever
# path each attachment claims and the sub-sizes are rebuilt from them.
ddev exec 'cd /var/www/html && wp eval-file database/restore-media.php'

# Registered rules do nothing until WordPress has flushed them once, and a freshly
# imported database has whatever the dump's site had.
ddev exec 'cd /var/www/html && wp foehn rewrite:flush' >/dev/null
printf '✓ rewrite rules flushed\n'

url="$(ddev exec 'cd /var/www/html && wp option get home' 2>/dev/null | tail -n1 | tr -d '\r')"
printf '\n  %s\n' "$url"
