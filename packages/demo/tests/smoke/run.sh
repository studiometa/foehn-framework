#!/usr/bin/env bash
#
# Integration smoke test for the starter theme against a real WordPress.
#
# Run from packages/starter with a started ddev project:
#
#     ./tests/smoke/run.sh
#
# The unit suites run against WordPress function stubs, so they stay green when a
# discovery registers nothing at all. This drives a real request instead.

set -euo pipefail

cd "$(dirname "$0")/../.."

fail() {
	printf '\n✗ %s\n' "$1" >&2
	exit 1
}

url="$(ddev exec 'cd /var/www/html && wp option get home' 2>/dev/null | tail -n1 | tr -d '\r')"
[ -n "$url" ] || fail 'could not read the site URL from WordPress'

printf '→ %s\n' "$url"

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

# discovery:list is the only thing that can say what discovery found, so a run
# against stubs proves nothing about it. This asserts the whole path at once: the
# command is registered, the filter matches, the item is described and the
# attribute's arguments are read back off it.
listing="$(ddev exec 'cd /var/www/html && wp foehn discovery:list --discovery=PostType' 2>/dev/null || true)"

case "$listing" in
*"AsPostType(name: product"*) ;;
*) fail "wp foehn discovery:list did not describe the starter's post types
$listing" ;;
esac

case "$listing" in
*"Locations:"*"Demo\\"*) ;;
*) fail "wp foehn discovery:list did not report where it looked
$listing" ;;
esac

printf '✓ wp foehn discovery:list reports what was found, and from where\n'

# A rewrite rule only exists once WordPress has flushed the rules, which is the
# whole difficulty the flush hash exists for. Nothing about that is visible to
# the unit suite: it asserts what was registered, not what a URL answers.
health="$(curl -sk -w '\n%{http_code}' "$url/_health")"

case "$health" in
*'{"status":"ok"}'*200) ;;
*) fail "GET /_health did not reach the #[AsRewriteRule] handler
$health" ;;
esac

printf '✓ a rewrite rule answers its URL\n'

