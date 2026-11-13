#!/usr/bin/env bash
#
# The page cache in bucket storage, against the demo's RustFS service.
#
# Run from packages/demo with a started ddev project, after ./tests/smoke/run.sh:
#
#     ./tests/smoke/page-cache-bucket.sh
#
# The demo ships its cache on disk and production-only, so this turns bucket storage
# on for the run, and puts everything back when it ends, failed or not:
#
# 1. app/page-cache.local.config.php with `storage: PageCacheStorage::Bucket`;
# 2. the generated nginx include in .ddev/nginx/, read through Docker's own DNS,
#    because the snippet resolves the bucket host per request;
# 3. a miss that PHP renders and writes to the bucket after the response, a hit that
#    nginx proxies from the bucket, a purge, and a section fragment with its noindex.

set -euo pipefail

cd "$(dirname "$0")/../.."

local_config="theme/app/page-cache.local.config.php"
include=".ddev/nginx/foehn-page-cache.conf"
written="config/nginx/foehn-page-cache.conf"

fail() {
	printf '\n✗ %s\n' "$1" >&2
	exit 1
}

[ -e "$local_config" ] && fail "$local_config already exists — this run would replace it"
[ -e "$include" ] && fail "$include already exists — this run would replace it"

reload_nginx() {
	ddev exec 'sudo nginx -t && sudo nginx -s reload' >/dev/null 2>&1 || fail 'nginx refused the configuration'
	sleep 1
}

cleanup() {
	# While the bucket configuration is still in place, so the objects go too.
	ddev exec 'cd /var/www/html && wp foehn cache:clear' >/dev/null 2>&1 || true
	rm -f "$local_config" "$include" "$written"
	rmdir config/nginx config 2>/dev/null || true
	ddev exec 'sudo nginx -s reload' >/dev/null 2>&1 || true
}

trap cleanup EXIT

url="$(ddev exec 'cd /var/www/html && wp option get home' 2>/dev/null | tail -n1 | tr -d '\r' || true)"
[ -n "$url" ] || fail 'could not read the site URL — WordPress did not boot'

cat >"$local_config" <<'PHP'
<?php

declare(strict_types=1);

// Written by tests/smoke/page-cache-bucket.sh, removed when it finishes.
return new Studiometa\Foehn\Config\PageCacheConfig(
    enabled: true,
    storage: Studiometa\Foehn\Config\PageCacheStorage::Bucket,
    environments: ['local'],
    debugHeaders: true,
);
PHP

# opcache.file_update_protection: PHP-FPM ignores a file younger than 2 seconds.
sleep 3

status="$(ddev exec 'cd /var/www/html && wp foehn cache:status' 2>/dev/null | tr -d '\r' || true)"

case "$status" in
*"Storage: bucket"*"does not read in bucket storage"*"The page cache is active"*) ;;
*) fail "cache:status does not report an active bucket cache
$status" ;;
esac

printf '✓ cache:status reports bucket storage\n'

ddev exec 'cd /var/www/html && S3_UPLOADS_PROXY_RESOLVER=127.0.0.11 wp foehn cache:config --server=nginx --write' >/dev/null 2>&1 ||
	fail "cache:config could not generate the bucket include
$(ddev exec 'cd /var/www/html && wp foehn cache:config --server=nginx' 2>&1 | grep -v Deprecated | tail -5)"

grep -q 'proxy_pass $foehn_page_upstream;' "$written" || fail "the generated include does not proxy to the bucket"
cp "$written" "$include"
reload_nginx

printf '✓ nginx accepts the bucket include\n'

ddev exec 'cd /var/www/html && wp foehn cache:clear' >/dev/null 2>&1 || fail 'cache:clear failed'

headers() {
	curl -sk -D- -o /dev/null "$1" | tr -d '\r'
}

header() {
	grep -i "^$2:" <<<"$1" | head -n1 | cut -d' ' -f2- || true
}

first="$(headers "$url/about/")"
[ "$(header "$first" X-Foehn-Cache)" = "MISS" ] || fail "the first request was not a miss
$first"

# The PUT runs after the response is sent, so the object can arrive a moment later.
hit=""
for _ in $(seq 1 20); do
	second="$(headers "$url/about/")"
	if [ "$(header "$second" X-Foehn-Cache-Via)" = "nginx" ]; then
		hit="$second"
		break
	fi
	sleep 0.25
done

[ -n "$hit" ] || fail "nginx did not serve the page from the bucket
$second"
[ "$(header "$hit" X-Foehn-Cache)" = "HIT" ] || fail "the bucket hit says it is not a hit
$hit"
case "$(header "$hit" Content-Type)" in
"text/html; charset=UTF-8") ;;
*) fail "the object was not stored as HTML
$hit" ;;
esac

grep -q 'foehn cache:' <(curl -sk "$url/about/") || fail 'the page from the bucket has no cache marker'

printf '✓ a miss is stored in the bucket, and nginx serves the next request from it\n'

ddev exec "cd /var/www/html && wp foehn cache:clear --url=$url/about/" >/dev/null 2>&1 || fail 'cache:clear --url failed'
purged="$(headers "$url/about/")"
[ "$(header "$purged" X-Foehn-Cache)" = "MISS" ] || fail "the page was still served after its purge
$purged"

printf '✓ a purge removes the object, and the next request reaches PHP\n'

headers "$url/?foehn_sections=testimonials" >/dev/null
section=""
for _ in $(seq 1 20); do
	section="$(headers "$url/?foehn_sections=testimonials")"
	[ "$(header "$section" X-Foehn-Cache-Via)" = "nginx" ] && break
	sleep 0.25
done

[ "$(header "$section" X-Foehn-Cache-Via)" = "nginx" ] || fail "nginx did not serve the section from the bucket
$section"
[ "$(header "$section" X-Robots-Tag)" = "noindex, nofollow" ] || fail "a section from the bucket lost its noindex
$section"

printf '✓ a section fragment is served from the bucket with its noindex\n'
