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

# ──────────────────────────────────────────────
# Static page cache
# ──────────────────────────────────────────────
#
# This is the part unit tests cannot reach. They run against function stubs, so they
# cannot show that nginx reads the file PHP wrote, and a broken nginx snippet hides
# behind a working PHP drop-in indefinitely — both answer HIT. Every assertion below
# therefore checks which reader answered, and checks the body as well as the header: the
# `<!-- foehn cache: … -->` marker differs between two renders, so identical bodies are
# the only proof a page was not rendered again.

wp() {
	# One quoted argument, deliberately: `$*` would drop the quoting inside it and
	# silently turn `--post_content="a b"` into three arguments.
	ddev exec "cd /var/www/html && $1" 2>/dev/null | grep -v '^Deprecated' | grep -v '^$' || true
}

cache_config='theme/app/page-cache.local.config.php'
nginx_include='.ddev/nginx/foehn-page-cache.conf'
cache_root='web/wp-content/cache/foehn/pages'
headers="$(mktemp)"
page="$(mktemp)"
other="$(mktemp)"
permalinks="$(wp 'wp option get permalink_structure' | tail -n1 | tr -d '\r')"
post_id=''

cleanup() {
	rm -f "$body" "$headers" "$page" "$other" "$cache_config"

	if [ -f "${nginx_include}.off" ]; then
		mv "${nginx_include}.off" "$nginx_include"
		ddev exec 'sudo nginx -s reload' >/dev/null 2>&1 || true
	fi

	if [ -n "$post_id" ]; then
		wp "wp post delete ${post_id} --force" >/dev/null 2>&1
	fi

	wp "wp rewrite structure '${permalinks}' --hard" >/dev/null 2>&1
	wp 'wp foehn cache:clear' >/dev/null 2>&1
}

trap cleanup EXIT

# The cache is enabled for production only in the starter, which is the right default —
# caching while editing templates is nobody's idea of a good local setup. The config
# loader reads an environment's own file in preference to the plain one, so writing this
# is how the feature is switched on for the duration of the test.
cat >"$cache_config" <<'PHP'
<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\PageCacheConfig;

// Written by tests/smoke/run.sh, removed when it exits.
return new PageCacheConfig(
    enabled: true,
    ttl: 0,
    environments: ['local'],
    debugHeaders: true,
);
PHP

# `wp core install` leaves permalinks plain, and a plain permalink is a query string,
# which this cache bypasses by design. Restored on exit.
wp "wp rewrite structure '/%postname%/' --hard" >/dev/null

request() {
	local target="$1"
	shift
	curl -sk -D "$headers" -o "$page" "$@" "$target" >/dev/null
}

header_of() {
	local line
	line="$(grep -i "^$1:" "$headers" | tail -n1 | tr -d '\r')"
	printf '%s' "${line#*: }"
}

# `nginx -s reload` returns as soon as the signal is sent: the old workers keep serving
# until they have drained, so the next request may still be answered by the config that
# was just replaced. Poll rather than sleep on a guess.
await_via() {
	local want="$1" target="$2" attempt=0

	while [ "$attempt" -lt 40 ]; do
		request "$target"

		if [ "$(header_of 'X-Foehn-Cache-Via')" = "$want" ]; then
			return 0
		fi

		attempt=$((attempt + 1))
		sleep 0.25
	done

	return 1
}

expect_cache() {
	# expect_cache <label> <state> <via>
	local state via
	state="$(header_of 'X-Foehn-Cache')"
	via="$(header_of 'X-Foehn-Cache-Via')"

	[ "$state" = "$2" ] && [ "$via" = "$3" ] || fail "$1
	expected: $2 via $3
	actual:   ${state:-none} via ${via:-none} (reason: $(header_of 'X-Foehn-Cache-Reason'))"
}

wp 'wp foehn cache:clear' >/dev/null

# 1. The homepage, twice: rendered once, then served by nginx without PHP starting.
request "$url/"
expect_cache 'the first homepage request should have missed' MISS php
cp "$page" "$other"

request "$url/"
expect_cache 'the second homepage request should have hit through nginx' HIT nginx

cmp -s "$other" "$page" ||
	fail 'the cached homepage is not byte-identical to the one that was stored'

printf '✓ the homepage is stored, then served by nginx\n'

# 2. At the path every reader computes for it.
host="${url#*://}"
[ -f "${cache_root}/${host}/index.html" ] ||
	fail "no cache file at ${cache_root}/${host}/index.html
$(find "$cache_root" -type f 2>/dev/null | head -20)"

printf '✓ the file is where all four readers look for it\n'

# 3. A visitor with a login cookie is never served or stored from the cache.
before="$(find "$cache_root" -type f | wc -l)"
request "$url/" -b 'wordpress_logged_in_smoke=1'
expect_cache 'a logged-in request should have bypassed' BYPASS php

[ "$(find "$cache_root" -type f | wc -l)" = "$before" ] ||
	fail 'a logged-in request wrote to the cache'

printf '✓ a login cookie bypasses, and writes nothing\n'

# 4 and 5. A non-ASCII permalink, which is where this class of feature usually breaks.
# WordPress stores the slug with lowercase percent escapes and a browser sends uppercase
# ones, so the recorder and the purger are handed two spellings of one URL. Both have to
# resolve to one file, or every accented page goes stale on the first edit and stays
# stale — wp-super-cache #1080 and #1081.
post_id="$(wp 'wp post create --post_title="Ұlytau oblysy" --post_status=publish --porcelain' | tail -n1 | tr -d '\r')"
[ -n "$post_id" ] || fail 'could not create the non-ASCII post'

accented="${url}/%D2%B1lytau-oblysy/"
decoded="${cache_root}/${host}/ұlytau-oblysy/index.html"

request "$accented"
expect_cache 'the first request for the accented permalink should have missed' MISS php
cp "$page" "$other"

request "$accented"
expect_cache 'the second request for the accented permalink should have hit' HIT nginx

cmp -s "$other" "$page" || fail 'the cached accented page is not the one that was stored'

[ -f "$decoded" ] || fail "the accented page was not stored at its decoded path
$(find "$cache_root" -type d -name '*lytau*' 2>/dev/null)"

# The escaped spelling must not exist as well, or the two readers are keying differently
# and one of them will never find what the other wrote.
[ -z "$(find "$cache_root" -type d -name '*%*' 2>/dev/null)" ] ||
	fail 'a percent-escaped directory was written alongside the decoded one'

printf '✓ an accented permalink is one file, at its decoded path\n'

wp "wp post update ${post_id} --post_content='edited by the smoke test'" >/dev/null

if [ -f "$decoded" ]; then
	fail 'editing the post did not purge its cached page'
fi

request "$accented"
expect_cache 'the request after an edit should have missed' MISS php

printf '✓ editing a post purges the page it was serving\n'

# 6. Tracking parameters reach the cache; anything else is a bypass. The edit above
# purged the front page along with the post, which is the point of it — so the homepage
# is rendered once more before the query-string assertions have anything to hit.
request "$url/"
request "$url/?utm_source=smoke"
expect_cache 'a tracking parameter should still have hit' HIT nginx
cp "$page" "$other"

request "$url/?foo=bar"
expect_cache 'an unknown query arg should have bypassed' BYPASS php

if cmp -s "$other" "$page"; then
	fail 'the bypassed response is byte-identical to the cached one, so nothing was rendered'
fi

printf '✓ tracking parameters hit, a real query string bypasses\n'

# 7. The order the ignored args arrive in cannot change which file is read. nginx has no
# way to sort a query string, so the two must agree by construction rather than by luck.
request "$url/?utm_source=a&utm_medium=b"
expect_cache 'the first argument order should have hit' HIT nginx
cp "$page" "$other"

request "$url/?utm_medium=b&utm_source=a"
expect_cache 'the reversed argument order should have hit' HIT nginx

cmp -s "$other" "$page" ||
	fail 'the two argument orders were served different bodies, so they read different files'

printf '✓ two ignored args in either order read the same file\n'

# 8. The same, through the PHP drop-in. Without this the nginx assertions above could be
# passing on a snippet that never matches, with the drop-in quietly covering for it.
mv "$nginx_include" "${nginx_include}.off"
ddev exec 'sudo nginx -t && sudo nginx -s reload' >/dev/null 2>&1 ||
	fail 'nginx refused to reload without the page cache include'

await_via php "$url/?utm_source=a&utm_medium=b" ||
	fail 'nginx kept answering after its page cache include was removed'

expect_cache 'the drop-in should have served the page nginx was serving' HIT php
cp "$page" "$other"

request "$url/?utm_medium=b&utm_source=a"
expect_cache 'the drop-in should have hit for the reversed order too' HIT php

cmp -s "$other" "$page" ||
	fail 'the drop-in served different bodies for the two argument orders'

mv "${nginx_include}.off" "$nginx_include"
ddev exec 'sudo nginx -s reload' >/dev/null 2>&1 ||
	fail 'nginx refused to reload with the page cache include restored'

await_via nginx "$url/" || fail 'nginx did not resume serving the cache'
expect_cache 'nginx should be serving again' HIT nginx

printf '✓ the drop-in serves the same file, and nginx is the one that was answering\n'
