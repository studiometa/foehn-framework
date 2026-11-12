#!/usr/bin/env bash
#
# Integration smoke test for the demo theme against a real WordPress.
#
# Run from packages/demo with a started ddev project:
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

# Every location: the framework, foehn-acf and the theme. Counted rather than named, so
# a package added to the demo does not make this a false failure.
grep -qE 'Locations cached: ([0-9]+)/\1$' <<<"$(tr -d '\r' <<<"$cache_status")" || fail "the request did not warm the discovery cache
$cache_status"

printf '✓ the request warmed the discovery cache\n'

# Uploads go to the RustFS service, so the assertions need something uploaded. The
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

# AGENTS.md carries the block `wp foehn agents-md` writes, and the command reads it
# back: a block whose markers were edited away would be appended a second time.
grep -q '<!-- foehn:begin -->' AGENTS.md || fail "AGENTS.md has no block from wp foehn agents-md"
agents="$(ddev exec 'cd /var/www/html && wp foehn agents-md --dry-run' 2>/dev/null || true)"
case "$agents" in
*"Updated the Føhn block"* | *"current"*) ;;
*) fail "wp foehn agents-md does not find the block in AGENTS.md
$agents" ;;
esac
printf '✓ AGENTS.md carries the generated Føhn block\n'

# discovery:list is the only thing that can say what discovery found, so a run
# against stubs proves nothing about it. This asserts the whole path at once: the
# command is registered, the filter matches, the item is described and the
# attribute's arguments are read back off it.
listing="$(ddev exec 'cd /var/www/html && wp foehn discovery:list --discovery=PostType' 2>/dev/null || true)"

case "$listing" in
*"AsPostType(name: project"*) ;;
*) fail "wp foehn discovery:list did not describe the demo's post types
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
# /wp-content/uploads/, proxies it to RustFS, and hands back the bytes.
image="$(ddev exec "cd /var/www/html && wp eval 'echo wp_get_attachment_url($attachment);'" 2>/dev/null | tr -d '\r' | tail -n1)"

served="$(curl -sk -o /dev/null -w '%{http_code} %{content_type}' "$image")"

case "$served" in
"200 image/jpeg") ;;
*) fail "uploads are not being served

    url:      $image
    response: $served

Either .ddev/nginx/uploads-proxy.conf is not mapping /wp-content/uploads/ to the
bucket, or RustFS is refusing the read: it ignores the public-read ACL the plugin
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
check_page "/" "All 6 series, " "the homepage counts the series with Project::count()"
if curl -sk "$url/" | grep -q 'global-styles-inline-css'; then
	fail "the homepage prints the theme.json global styles DisableGlobalStyles removes"
fi
printf '✓ %s\n' "DisableGlobalStyles removes the global styles of a theme with no theme.json"
# CleanContent strips the type from an archive title.
check_page "/category/notes/" '<h1 class="title"[^>]*>Notes</h1>' "CleanContent removes the Category: prefix from an archive title"

# SecurityHeaders sends its headers on every page PHP renders.
home_headers="$(curl -sk -D- -o /dev/null "$url/" | tr -d '\r')"
grep -qi '^x-content-type-options: nosniff$' <<<"$home_headers" || fail "the homepage has no X-Content-Type-Options header from SecurityHeaders
$home_headers"
grep -qi '^permissions-policy: ' <<<"$home_headers" || fail "the homepage has no Permissions-Policy header from SecurityHeaders"
printf '✓ %s\n' "SecurityHeaders sends its headers"

# RestApiAuth hides the user list from visitors, and only that list.
users_status="$(curl -sk -o /dev/null -w '%{http_code}' "$url/wp-json/wp/v2/users")"
[ "$users_status" = "404" ] || fail "GET /wp-json/wp/v2/users answered a visitor with HTTP $users_status, not 404"
printf '✓ %s\n' "RestApiAuth removes the users route for a visitor"

# The footer prints Env::get(), the environment that also picks foehn.local.config.php.
check_page "/" '>Environment: local<' "the footer prints the environment"
# SettingsSeeder turns the banner on, so the public demo says it is one.
check_page "/" 'class="banner' "the banner from the theme settings is on every page"
check_page "/projects/" "index-row__title" "the projects index lists the series"
check_page "/projects/page/2/" "<html" "project pagination keeps a full-page fallback"
# /projects/series/<slug>/ was taken by the rule WordPress adds for the attachments
# of a project, so a series archive was a 404. It lives at /series/<slug>/ now.
check_page "/series/architecture/" "index-row__title" "a series archive lists its projects"
check_page "/project-tags/commission/" "index-row__title" "a project tag archive lists its projects"
# video_embed() turns the watch URL in the `film` meta into a no-cookie embed.
check_page "/projects/editions/" 'src="https://www.youtube-nocookie.com/embed/aqz-KE-bpKQ' "a project's film is embedded with video_embed()"
check_page "/projects/corridors/" "plate--" "a project page shows its photographs"
# The theme's own js-toolkit components are mounted by file name, from app.js.
check_page "/projects/corridors/" 'data-component="CopyLink"' "a project page mounts a component of the theme"
check_page "/projects/corridors/" 'href="https://example.com/contrejour/corridors"' "a project page shows its press quote from the ACF field group"
check_page "/about/" "prose" "the about page renders its copy"
# The blocks, the binding and the pattern are only worth registering if a visitor
# sees them: PageSeeder writes the About page as block markup.
check_page "/about/" 'class="hero hero--small"' "the about page opens with the hero block"
check_page "/about/" 'callout callout--success' "the about page holds the commission pattern, callout included"
check_page "/about/" 'minute read' "the reading-time binding computes its value on the about page"
check_page "/projects/corridors/" '<p class="wp-block-paragraph">Hasselblad 500C/M' "core/post-meta binds the camera meta in a project's content"
# The footer links every page to it, so a missing page is a 404 on every page.
check_page "/legal/" "Unsplash licence" "the legal page the footer links to exists"

# The journal is where the content blocks are seen. Each needle is the block's own
# markup, so a block that rendered nothing fails here.
check_page "/notes-on-corridors/" 'data-wp-interactive="theme/contact-sheet"' "a journal post holds the interactive contact sheet"
check_page "/notes-on-corridors/" "data-wp-context=" "the contact sheet carries its per-instance context"
check_page "/notes-on-corridors/" 'class="facts__row"' "a journal post holds a facts block with its fact rows"
check_page "/notes-on-corridors/" 'class="related-projects"' "a journal post links projects picked with a posts control"
check_page "/editions-press-kit/" '\.pdf" download' "a journal post links the PDF picked with a file control"
# ContentImageHooks rewrites the srcset of an image in content to transformer URLs.
check_page "/notes-on-corridors/" 'srcset="[^"]*/_image/' "an image in content gets its srcset from the transformer"
check_page "/journal/" "Notes on Corridors" "the posts page lists the journal"
check_page "/category/notes/" "index-row__title" "the category archive lists the journal"
check_page "/tag/film/" "index-row__title" "a tag archive lists its posts"
check_page "/studio-contact-sheets/" 'name="post_password"' "the seeded protected post asks for its password"

# The contact sheet only works if its module can import the Interactivity API, which
# WordPress maps only for a declared dependency.
grep -q '"@wordpress/interactivity"' <<<"$(curl -sk "$url/notes-on-corridors/")" ||
	fail "the contact sheet's view module cannot import @wordpress/interactivity: it is not in the import map"
printf '✓ %s\n' "the import map gives the contact sheet the Interactivity API"

# A password-protected post has to answer with WordPress's password form and with
# nothing the password guards. Both controllers send it to pages/password, which
# the demo did not ship, so either one threw "Failed to render template" and
# answered 500. The template must not print `post.content` either: Timber prints
# it in full, password or not.
check_protected() {
	local type="$1" id link status

	id="$(ddev exec "cd /var/www/html && wp post create --post_type=$type --post_status=publish --post_title='Protected $type' --post_password=smoke --post_content=protected-content-marker --porcelain" 2>/dev/null | tr -d '\r' | grep -xE '[0-9]+' | tail -n1 || true)"
	[ -n "$id" ] || fail "could not create a password-protected $type"

	link="$(ddev exec "cd /var/www/html && wp eval 'echo get_permalink($id);'" 2>/dev/null | tr -d '\r' | tail -n1)"
	status="$(curl -sk -o "$body" -w '%{http_code}' "$link")"

	# Removed before any check can fail, so the second run in CI starts from the
	# same site, and the restored dump keeps no trace of the test.
	ddev exec "cd /var/www/html && wp post delete $id --force" >/dev/null 2>&1 || true

	[ "$status" = "200" ] || fail "a password-protected $type returned HTTP $status
$(grep -iE -m3 'Fatal error|Uncaught' "$body" | head -c 800)"

	grep -q 'name="post_password"' "$body" || fail "a password-protected $type rendered without the password form
$(head -c 400 "$body")"

	if grep -q 'protected-content-marker' "$body"; then
		fail "a password-protected $type printed the content its password guards"
	fi

	printf '✓ a password-protected %s shows the password form and nothing else\n' "$type"
}

check_protected post
check_protected page

# #[AsRestRoute]: a public list, and a route whose own permission method refuses a
# visitor. The list goes through Project::query(), so it is also the end-to-end check
# that the builder returns a list.
projects_json="$(curl -sk "$url/wp-json/demo/v1/projects")"
[ "$(grep -o '"id":' <<<"$projects_json" | wc -l | tr -d ' ')" = "6" ] ||
	fail "GET /wp-json/demo/v1/projects did not list the six projects
$(head -c 400 <<<"$projects_json")"
grep -q '"year":2024' <<<"$projects_json" || fail "the projects route does not give each project its year"
drafts_status="$(curl -sk -o /dev/null -w '%{http_code}' "$url/wp-json/demo/v1/projects/drafts")"
[ "$drafts_status" = "401" ] || fail "the drafts route answered a visitor with HTTP $drafts_status, not 401"
printf '✓ %s\n' "the REST routes answer, the public one to everyone and the drafts one to editors only"

home="$(curl -sk "$url/")"
grep -q 'data-foehn-lazy-section' <<<"$home" || fail "the homepage does not demonstrate a lazy section"
grep -q 'data-option-src="/?foehn_sections=testimonials"' <<<"$home" || fail "the lazy testimonial section has no section request URL"

testimonials_section="$(curl -sk "$url/?foehn_sections=testimonials")"
grep -q 'id="foehn-section-testimonials"' <<<"$testimonials_section" || fail "the testimonial section request returned no wrapper"
grep -q '>Words<' <<<"$testimonials_section" || fail "the testimonial section request returned no testimonials"
grep -q 'Rated 5 out of 5' <<<"$testimonials_section" || fail "the testimonial section does not show the rating meta"
printf '✓ %s\n' "the homepage lazy-loads its testimonial section"

projects_index="$(curl -sk "$url/projects/")"
grep -q 'data-component="Fetch"' <<<"$projects_index" || fail "project pagination does not use the base Fetch component"
grep -q 'data-option-history' <<<"$projects_index" || fail "project pagination does not put the page it fetched in the URL"

# This pair used to assert the opposite: that nothing here fetched a section URL, because
# a section response carried `Cache-Control: private, no-store` and fetching one traded a
# page served off a file for a fragment rendered every time. Section responses are stored
# under the same rules as pages now — `foehn_sections` is a cache-key query argument — so
# the archive fetches fragments, and both URLs below are cached.
#
# Spelled out in full rather than matched loosely, because the selection *is* the cache
# key: a name added or dropped here is a different file, and a reader that disagrees about
# which one silently misses.
grep -q 'data-option-src="/projects/page/2/?foehn_sections=project-count,project-index"' <<<"$projects_index" ||
	fail "project pagination does not fetch the count and the index as one section request"

# The filter form is a section too, and it asks for itself: its facet counts, its dead-end
# options, its ticked boxes and its Reset link are all answers to the current URL that only
# the server can give.
grep -q 'data-option-src="/projects/?foehn_sections=project-count,project-filters,project-index"' <<<"$projects_index" ||
	fail "the filter form does not request its own section alongside the count and the index"
grep -q 'id="foehn-section-project-filters"' <<<"$projects_index" || fail "the filter form is not a section"
# `morph` is load-bearing, not cosmetic. The default `replace` calls replaceWith, which
# would destroy the form node the visitor is using on every change — taking focus to
# <body>, and taking with it the Fetch instance running the request.
grep -q 'data-option-mode="morph"' <<<"$projects_index" || fail "the filter form would be replaced rather than morphed, which loses focus mid-interaction"

project_section="$(curl -sk "$url/projects/page/2/?foehn_sections=project-count,project-index")"
grep -q 'id="foehn-section-project-index"' <<<"$project_section" || fail "the page-two section request returned no project index wrapper"
grep -q 'id="foehn-section-project-count"' <<<"$project_section" || fail "the page-two section request returned no count wrapper, so the count would go stale on paging"
grep -q '<html' <<<"$project_section" && fail "the page-two section request returned a full page"
# `href` only. Pagination inside a section response now carries a section URL in
# `data-option-src` on purpose; what must stay clean is the link a visitor without
# JavaScript follows, and the one `history` pushes.
grep -qE 'href="[^"]*foehn_sections=' <<<"$project_section" && fail "pagination inside a section response retained the section control parameter in its fallback URL"
printf '✓ %s\n' "project pagination fetches a section URL and gets a fragment back"

# The form rendered for two different URLs, which is the whole reason it is a section.
filtered_form="$(curl -sk "$url/projects/?project_category%5B%5D=still-life&foehn_sections=project-filters" | tr '\n' ' ')"
grep -qE 'value="still-life" +checked' <<<"$filtered_form" || fail "the filter form does not reflect the filter it was asked to render"
grep -q 'filters__count' <<<"$filtered_form" || fail "the filter form renders no facet counts"
grep -q 'Reset' <<<"$filtered_form" || fail "the filter form offers no way out of a filter"

bare_form="$(curl -sk "$url/projects/?foehn_sections=project-filters" | tr '\n' ' ')"
grep -qE 'value="still-life" +checked' <<<"$bare_form" && fail "the filter form shows a filter the URL did not ask for"
grep -q 'Reset' <<<"$bare_form" && fail "the filter form offers a reset on an unfiltered archive, so it is not tracking the query"
printf '✓ %s\n' "the filter form is a section the server re-renders for the current URL"

# An active series is a chip whose link removes it and keeps the other one.
two_series="$(curl -sk "$url/projects/?project_category%5B%5D=still-life&project_category%5B%5D=portrait&foehn_sections=project-filters")"
grep -q 'class="filters__chip" href="/projects/?project_category=portrait"' <<<"$two_series" ||
	fail "an active filter chip does not link to the archive without its own series"
grep -q 'filters__chip' <<<"$bare_form" && fail "the unfiltered archive shows active filter chips"
printf '✓ %s\n' "query_url_toggle() builds a chip that removes one active series"

search="$(curl -sk "$url/?s=series&post_type=project")"
grep -q '<input type="hidden" name="post_type" value="project">' <<<"$search" ||
	fail "a new search from the results page drops the post_type the URL holds"
grep -q 'href="/?s=series&#038;post_type=post"' <<<"$search" || fail "the search page does not offer the other post types with query_url()"
printf '✓ %s\n' "the search page keeps and changes its arguments with the query helpers"

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

# The counts on the homepage are cached under a tag, and saving a project flushes it.
# shellcheck disable=SC2016  # the container expands it, not this shell
cached_stats() {
	ddev exec 'cd /var/www/html && wp eval "echo Studiometa\\Foehn\\Kernel::get(Studiometa\\Foehn\\Contracts\\CacheInterface::class)->has(Demo\\Controllers\\FrontPageController::STATS_KEY) ? \"yes\" : \"no\";"' 2>/dev/null | tr -d '\r' | tail -n1
}

curl -sk -o /dev/null "$url/"
[ "$(cached_stats)" = "yes" ] || fail "the homepage did not cache its counts under the projects tag"
corridors_id="$(ddev exec "cd /var/www/html && wp eval 'echo get_page_by_path(\"corridors\", OBJECT, \"project\")->ID;'" 2>/dev/null | tr -d '\r' | tail -n1)"
ddev exec "cd /var/www/html && wp post update $corridors_id --menu_order=1" >/dev/null 2>&1
[ "$(cached_stats)" = "no" ] || fail "saving a project did not flush the projects cache tag"
printf '✓ %s\n' "a tagged cache entry is flushed when a project is saved"

# The same save queued a #[AsJob]: dispatch() stores it in Action Scheduler, and the
# real cron runs it. Run here by hand, it requests the plates of the project.
# shellcheck disable=SC2016  # the container expands it, not this shell
queued="$(ddev exec "cd /var/www/html && wp action-scheduler action list --hook=foehn/demo/jobs/warm_plates --status=pending --format=json" 2>/dev/null | tr -d '\r' | tail -n1)"
grep -q "\"projectId\":$corridors_id}" <<<"$queued" || fail "saving a project did not queue the WarmPlates job
$queued"
ddev exec 'cd /var/www/html && wp action-scheduler run --hooks=foehn/demo/jobs/warm_plates' >/dev/null 2>&1 ||
	fail "the WarmPlates job did not run"
[ "$(ddev exec 'cd /var/www/html && wp action-scheduler action list --hook=foehn/demo/jobs/warm_plates --status=failed --format=count' 2>/dev/null | tr -d '\r' | tail -n1)" = "0" ] ||
	fail "a WarmPlates job failed: see Tools > Scheduled Actions"
printf '✓ %s\n' "saving a project queues a job that warms its plates, and the job runs"

# The project's own commands go under `wp app`, the default cliNamespace.
stats="$(ddev exec 'cd /var/www/html && wp app projects:stats' 2>/dev/null | tr -d '\r' || true)"
grep -q '^6 series, [0-9]* photographs\.$' <<<"$stats" || fail "wp app projects:stats did not list the six series
$stats"
printf '✓ %s\n' "a project command runs under wp app"

# A command that writes stops in production unless it is asked for a dry run. The
# environment variable wins over .env, which Dotenv does not let overwrite it.
order_before="$(ddev exec "cd /var/www/html && wp post list --post_type=project --orderby=ID --order=ASC --field=menu_order" 2>/dev/null | tr -d '\r' | tr '\n' ' ')"
refused="$(ddev exec 'cd /var/www/html && WP_ENVIRONMENT_TYPE=production wp app projects:reorder' 2>&1 | tr -d '\r' || true)"
grep -q '`wp app projects:reorder` changes content or files' <<<"$refused" || fail "wp app projects:reorder ran in production without --force or --dry-run
$(tail -5 <<<"$refused")"
dry_run="$(ddev exec 'cd /var/www/html && WP_ENVIRONMENT_TYPE=production wp app projects:reorder --dry-run' 2>&1 | tr -d '\r' || true)"
grep -q 'Nothing was written' <<<"$dry_run" || fail "wp app projects:reorder --dry-run did not run in production
$(tail -5 <<<"$dry_run")"
order_after="$(ddev exec "cd /var/www/html && wp post list --post_type=project --orderby=ID --order=ASC --field=menu_order" 2>/dev/null | tr -d '\r' | tr '\n' ' ')"
[ "$order_before" = "$order_after" ] || fail "a refused run or a dry run of projects:reorder changed the order"
printf '✓ %s\n' "a command that writes stops in production, and runs there with --dry-run"

# The seeders in theme/app/Seeders write the whole portfolio, and they must be
# idempotent: on a site that already holds it, they find every post by slug and
# every photograph by its Unsplash id, and write no copy. The site here came from
# the dump, which is a seeded site, so no count may move.
content_counts() {
	# shellcheck disable=SC2016  # the container expands $type, not this shell
	ddev exec 'cd /var/www/html && for type in project testimonial page post nav_menu_item; do wp post list --post_type=$type --post_status=any --format=count; done; wp post list --post_type=attachment --post_status=inherit --meta_key=unsplash_id --format=count' 2>/dev/null |
		tr -d '\r' | grep -xE '[0-9]+' | tr '\n' ' '
}

before="$(content_counts)"
seed_output="$(ddev exec 'cd /var/www/html && wp foehn seed' 2>&1)" ||
	fail "wp foehn seed failed
$(printf '%s\n' "$seed_output" | grep -v Deprecated | tail -12)"
after="$(content_counts)"

[ -n "$before" ] && [ "$before" = "$after" ] || fail "a second seed changed the amount of content
  before: $before (projects, testimonials, pages, posts, menu items, photographs)
  after:  $after"

printf '✓ wp foehn seed runs again and duplicates nothing\n'
