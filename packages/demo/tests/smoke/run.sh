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
check_page "/projects/page/2/" "<html" "project pagination keeps a full-page fallback"
check_page "/projects/corridors/" "plate--" "a project page shows its photographs"
check_page "/about/" "prose" "the about page renders its copy"

home="$(curl -sk "$url/")"
grep -q 'data-foehn-lazy-section' <<<"$home" || fail "the homepage does not demonstrate a lazy section"
grep -q 'data-option-src="/?foehn_sections=testimonials"' <<<"$home" || fail "the lazy testimonial section has no section request URL"

testimonials_section="$(curl -sk "$url/?foehn_sections=testimonials")"
grep -q 'id="foehn-section-testimonials"' <<<"$testimonials_section" || fail "the testimonial section request returned no wrapper"
grep -q '>Words<' <<<"$testimonials_section" || fail "the testimonial section request returned no testimonials"
printf '✓ %s\n' "the homepage lazy-loads its testimonial section"

projects_index="$(curl -sk "$url/projects/")"
grep -q 'data-component="Fetch"' <<<"$projects_index" || fail "project pagination does not use the base Fetch component"
grep -q 'data-option-src="/projects/page/2/?foehn_sections=project-index"' <<<"$projects_index" || fail "project pagination has no section request URL"

project_section="$(curl -sk "$url/projects/page/2/?foehn_sections=project-index")"
grep -q 'id="foehn-section-project-index"' <<<"$project_section" || fail "the page-two section request returned no project index wrapper"
grep -q '<html' <<<"$project_section" && fail "the page-two section request returned a full page"
grep -qE 'href="[^"]*foehn_sections=' <<<"$project_section" && fail "pagination inside a section response retained the section control parameter in its fallback URL"
printf '✓ %s\n' "project pagination swaps a section and keeps full-page fallbacks"

# Image transforms, end to end. The plate crops to two ratios, which is where
# #[AsImageSize] stops being the answer: a registered size is one shape, and one
# registered today applies to nothing already uploaded. So photograph.twig asks
# for the crop and GlideTransformer produces it.
#
# `sed` because the URL arrives HTML-escaped, and `&amp;` in a request is four
# parameters named wrongly rather than one named `fm`.
transform="$(curl -sk "$url/projects/corridors/" | grep -oE 'https?://[^" ]+/_image/[^" ]+' | head -n1 | sed 's/&amp;/\&/g')"

[ -n "$transform" ] || fail "the project page emitted no image transforms

Either no ImageTransformer is configured — in which case image_url() correctly
returned the source URL and this assertion is the only thing that noticed — or
league/glide is missing. See theme/app/foehn.config.php.

$(curl -sk "$url/projects/corridors/" | grep -oE '<img[^>]*src=\"[^\"]*\"' | head -3)"

base="${transform%%\?*}"

case "$transform" in
*"s="*) fail "a transform URL carries a signature, which nothing produces any more:

    $transform" ;;
esac

served="$(curl -sk -o /dev/null -w '%{http_code} %{content_type}' "$transform")"

case "$served" in
"200 image/webp") ;;
*) fail "a transform was not produced

    url:      $transform
    response: $served

The route runs on a cache miss and reads the original out of the bucket. A 404
here is usually GlideConfig reaching a different bucket than the uploads went
to — its client comes from the s3-uploads plugin so that cannot drift, but a
site without the plugin builds one from the S3_UPLOADS_* constants alone.

$(curl -sk "$transform" | head -c 300)" ;;
esac

printf '✓ an image transform is produced and served\n'

# The point of caching a transform is that the second request never reaches PHP:
# booting WordPress costs more than the transform saves. That only works because
# the cache path spells the transform out — nginx assembles it from named
# arguments. Glide's own xxh3 key is one nginx cannot compute, and a rule pointed
# at that would miss every time while the pages looked perfectly correct.
cached="$(curl -sk -D- -o /dev/null "$transform" | tr -d '\r' | grep -ci '^x-foehn-image-cache: HIT' || true)"

[ "$cached" = "1" ] || fail "the second request for a transform still reached PHP

    url: $transform

.ddev/nginx/image-cache.conf maps /_image/<path>?w=&h=&fit=&fm= onto the cached
object at cache/glide/<path>/<w>x<h>-<fit>-<fm>. If PHP is answering every
request, either the rule is not loaded or the object is not where the rule looks.

$(curl -sk -D- -o /dev/null "$transform" | head -12)"

printf '✓ a cached transform is served without booting WordPress\n'

# Nothing is signed, so what stands between the site and `?w=9999` is that the
# route refuses to build it. Each of these is a different way of asking for a
# transform outside the bounds, and each must be turned down rather than served.
for bad in "w=9999" "w=600&fit=stretch" "w=600&fm=gif" "fit=crop"; do
	refused="$(curl -sk -o /dev/null -w '%{http_code}' "$base?$bad")"

	[ "$refused" = "400" ] || fail "an out-of-bounds transform was answered with HTTP $refused, not 400

    url: $base?$bad

GlideConfig::normalise() bounds sizes to a grid and fit/fm to a short list. If
this is a 200, the cache an image can have is no longer finite."
done

printf '✓ a transform outside the bounds is refused\n'

# The one that matters most, and the least obvious. Glide's getAllParams() ends
# with array_merge($all, $params), so any parameter that survives to it overrides
# what the server configured — an unknown key is not ignored, it wins. Smuggling
# `q=5` past the route would mean the cache key says one thing and the bytes are
# another; `blur=90` would mean arbitrary CPU on demand.
#
# Byte-for-byte identical to the clean request is the proof: same file, so the
# extra parameters reached nothing.
clean_bytes="$(curl -sk -o /dev/null -w '%{size_download}' "$transform")"
smuggled_bytes="$(curl -sk -o /dev/null -w '%{size_download}' "$transform&q=5&blur=90&p=x")"

[ "$clean_bytes" = "$smuggled_bytes" ] && [ "$clean_bytes" != "0" ] || fail "a smuggled parameter changed the image

    clean:    $clean_bytes bytes — $transform
    smuggled: $smuggled_bytes bytes — with &q=5&blur=90 appended

Only GlideConfig::PARAMS may reach Glide. Anything else overrides the server."

printf '✓ a parameter outside the allowlist reaches nothing\n'

# studiometa/ui, both halves. The markup can only exist if the @ui Twig namespace
# resolved, which only happens when StudiometaUi is opted in and the package is
# installed — the framework's own unit test for that path is skipped, because there
# the package is a `suggest` and absent.
check_page "/about/" 'data-component="Accordion"' "an @ui component renders through the Twig namespace"

# Unsplash asks that photographers be credited. The credit is stored on the
# attachment at import and printed under every plate, so its absence is a licensing
# problem rather than a cosmetic one.
credits="$(curl -sk "$url/projects/corridors/" | grep -c 'class="credit"')"
plates="$(curl -sk "$url/projects/corridors/" | grep -c 'class="plate ')"

[ "$credits" -ge 5 ] && [ "$credits" = "$plates" ] || fail "every photograph must carry a credit
  plates:  $plates
  credits: $credits"

printf '✓ every photograph is credited\n'
