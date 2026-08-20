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

# Uploads go to the MinIO service, so the assertions need something uploaded. The
# fixture is committed rather than fetched: a smoke test that needs the network to
# reach a stock photo site fails for reasons that have nothing to do with Føhn.
#
# WP-CLI prints PHP deprecations from its own phar on stdout, so --porcelain is only
# the last line. Stripping non-digits from the whole output instead splices the line
# number out of "on line 369" onto the front of the ID.
# `|| true` because a failed import matches nothing, grep exits 1, and `set -o
# pipefail` would end the run right here — with no message, since the fail below
# is what has something to say about it.
attachment="$(ddev exec 'cd /var/www/html && wp media import tests/smoke/fixtures/uploads-probe.jpg --title="Uploads probe" --porcelain' 2>/dev/null | tr -d '\r' | grep -xE '[0-9]+' | tail -n1 || true)"

[ -n "$attachment" ] || fail "could not import the uploads fixture

An upload that reaches no bucket fails here rather than landing on local disk,
because the plugin's stream wrapper is the uploads directory.

$(ddev exec 'cd /var/www/html && wp media import tests/smoke/fixtures/uploads-probe.jpg --porcelain' 2>&1 | grep -v 'Deprecated' | tail -12)"

printf '✓ an image imported into the media library (attachment %s)\n' "$attachment"

ddev exec "cd /var/www/html && wp eval-file tests/smoke/assertions.php $attachment" ||
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
*"AsPostType(name: project"*) ;;
*) fail "wp foehn discovery:list did not describe the starter's post types
$listing" ;;
esac

case "$listing" in
*"Locations:"*"Demo\\"*) ;;
*) fail "wp foehn discovery:list did not report where it looked
$listing" ;;
esac

printf '✓ wp foehn discovery:list reports what was found, and from where\n'

# Plain permalinks bypass rewrite rules entirely, so a site using them answers
# /_health with a redirect and nothing about the rule is wrong. Asked first,
# because "301" on its own sends you looking in the wrong place.
permalinks="$(ddev exec 'cd /var/www/html && wp option get permalink_structure' 2>/dev/null | tail -n1 | tr -d '\r')"
[ -n "$permalinks" ] || fail 'this site uses plain permalinks, which bypass rewrite rules entirely'

# A rewrite rule only exists once WordPress has flushed the rules, which is the
# whole difficulty the flush hash exists for. Nothing about that is visible to
# the unit suite: it asserts what was registered, not what a URL answers.
health="$(curl -sk -w '\n%{http_code}' "$url/_health")"

case "$health" in
*'{"status":"ok"}'*200) ;;
*) fail "GET /_health did not reach the #[AsRewriteRule] handler

response:
$health

headers:
$(curl -skI "$url/_health" | head -12)

rewrite rules WordPress has stored:
$(ddev exec 'cd /var/www/html && wp rewrite list --match=/_health --fields=match,query' 2>&1 | tail -5)" ;;
esac

printf '✓ a rewrite rule answers its URL\n'

# A bucket that accepts writes and serves nothing is the expensive failure: the
# uploads look fine in the media library and every image on the site 404s. The
# assertions above prove the URL was written; only a request proves it resolves,
# and it has to come from out here rather than from the container, because that is
# where a browser stands. The whole path is exercised: nginx takes
# /wp-content/uploads/, proxies it to MinIO, and hands back the bytes.
image="$(ddev exec "cd /var/www/html && wp eval 'echo wp_get_attachment_url($attachment);'" 2>/dev/null | tr -d '\r' | tail -n1)"

served="$(curl -sk -o /dev/null -w '%{http_code} %{content_type}' "$image")"

case "$served" in
"200 image/jpeg") ;;
*) fail "uploads are not being served

    url:      $image
    response: $served

Either .ddev/nginx/uploads-proxy.conf is not mapping /wp-content/uploads/ to the
bucket, or MinIO is refusing the read: it ignores the public-read ACL the plugin
sets unless the bucket policy allows anonymous reads. See
tests/smoke/provision-bucket.php.

$(curl -sk "$image" | head -c 400)" ;;
esac

printf '✓ uploads are offloaded, and served from the site\x27s own domain\n'

# The demo is a site before it is a fixture, so its four pages are checked as pages:
# each must answer 200 and carry the thing that makes it that page. A template that
# renders an empty shell still returns 200, which is why each grep is for content.
check_page() {
	local path="$1" needle="$2" label="$3"
	local body status

	body="$(mktemp)"
	status="$(curl -sk -o "$body" -w '%{http_code}' "$url$path")"

	[ "$status" = "200" ] || {
		rm -f "$body"
		fail "GET $path returned HTTP $status"
	}

	grep -q "$needle" "$body" || {
		local head
		head="$(head -c 400 "$body")"
		rm -f "$body"
		fail "$path rendered without $label

$head"
	}

	rm -f "$body"
	printf '✓ %s\n' "$label"
}

check_page "/" "card__title" "the homepage lists a selection of projects"
check_page "/projects/" "index-row__title" "the projects index lists the series"
check_page "/projects/corridors/" "plate--" "a project page shows its photographs"
check_page "/about/" "prose" "the about page renders its copy"

# Unsplash asks that photographers be credited. The credit is stored on the
# attachment at import and printed under every plate, so its absence is a licensing
# problem rather than a cosmetic one.
credits="$(curl -sk "$url/projects/corridors/" | grep -c 'class="credit"')"
plates="$(curl -sk "$url/projects/corridors/" | grep -c 'class="plate ')"

[ "$credits" -ge 5 ] && [ "$credits" = "$plates" ] || fail "every photograph must carry a credit
  plates:  $plates
  credits: $credits"

printf '✓ every photograph is credited\n'
