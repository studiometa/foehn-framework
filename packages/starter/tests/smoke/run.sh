#!/usr/bin/env bash
#
# Integration smoke test for the starter theme against a real WordPress.
#
# Run from packages/starter with a started ddev project:
#
#     ./tests/smoke/run.sh
#
# This asks the one question the starter has to answer: does a project created
# from it boot and serve a page? On 2026-08-19 the answer was no, with 1409 unit
# tests passing — those run against WordPress function stubs, so a discovery that
# registers nothing at all still passes them.
#
# Everything else Foehn can do is exercised in packages/demo, which is where the
# features live. This file stays short on purpose: it is the check that has to
# keep working when the starter has almost nothing in it.

set -euo pipefail

cd "$(dirname "$0")/../.."

fail() {
	printf '\n✗ %s\n' "$1" >&2
	exit 1
}

# `|| true` because a WordPress that cannot boot makes wp exit non-zero, and
# `set -o pipefail` would then end the run here with status 255 and no message —
# the fail below is what has something to say about it.
url="$(ddev exec 'cd /var/www/html && wp option get home' 2>/dev/null | tail -n1 | tr -d '\r' || true)"
[ -n "$url" ] || fail 'could not read the site URL — WordPress did not boot

$(ddev exec "cd /var/www/html && wp option get home" 2>&1 | grep -v Deprecated | tail -12)'

printf '→ %s\n' "$url"

# A warm page cache would answer every request below with HTML rendered before the
# change under test, so the run would pass on yesterday's page. The demo ships the
# cache production-only and .env.example says local, but an .env that drifted is
# exactly the sort of thing that makes a smoke suite lie.
ddev exec 'cd /var/www/html && wp foehn cache:clear' >/dev/null 2>&1 || true

body="$(mktemp)"
trap 'rm -f "$body"' EXIT

status="$(curl -sk -o "$body" -w '%{http_code}' "$url/")"

# A PHP fatal inside a template still returns 200 in some configurations, so the
# body is checked as well as the status.
[ "$status" = "200" ] || fail "homepage returned HTTP $status
$(head -c 800 "$body")"

if grep -qiE 'Fatal error|Uncaught|Parse error' "$body"; then
	fail "homepage contains a PHP error
$(grep -iE -m3 'Fatal error|Uncaught|Parse error' "$body" | head -c 800)"
fi

printf '✓ homepage returns 200 with no PHP error\n'

# That request found no cache and had to scan, so it should have written one. This
# is what removes the deploy step: composer install clears, the next request fills.
#
# The output is captured rather than piped into grep: `grep -q` closes the pipe on
# its first match, the writer takes SIGPIPE, and `set -o pipefail` then reports the
# whole pipeline as failed even though the match succeeded.
cache_status="$(ddev exec 'cd /var/www/html && wp foehn discovery:status' 2>/dev/null || true)"

case "$cache_status" in
*"Locations cached: 2/2"*) ;;
*) fail "the request did not warm the discovery cache
$cache_status" ;;
esac

printf '✓ the request warmed the discovery cache\n'

ddev exec 'cd /var/www/html && wp eval-file tests/smoke/assertions.php' ||
	fail 'integration assertions failed'

# The framework's own WP-CLI commands come from the vendor package, and WP-CLI only
# builds a namespace once something asks for it — so the only honest check is to run
# one. `wp cli cmd-dump` cannot see them: it never loads WordPress.
ddev exec 'cd /var/www/html && wp foehn discovery:status' >/dev/null 2>&1 ||
	fail '`wp foehn discovery:status` did not run'

printf '✓ wp foehn commands are registered\n'

# A theme that builds assets nobody enqueues serves pages with no stylesheet and
# no error — which is how this starter shipped until ViteManifest existed. The tags
# have to be on the page, and the files behind them have to resolve.
page="$(curl -sk "$url/")"

for asset in $(printf '%s' "$page" | grep -oE "https://[^\"']*/dist/[^\"']*\.(css|js)" | sort -u); do
	code="$(curl -sk -o /dev/null -w '%{http_code}' "$asset")"
	[ "$code" = "200" ] || fail "the built asset $asset returned HTTP $code"
	found=1
done

[ -n "${found:-}" ] || fail "no built asset on the page — the theme enqueued nothing

Vite writes theme/dist; AssetHooks enqueues from it through ViteManifest. An
unbuilt theme (no theme/dist/.vite/manifest.json) looks exactly like this.

$(printf '%s' "$page" | grep -iE 'stylesheet|<script' | head -5)"

printf '✓ the built stylesheet and script are on the page\n'
