# Section Rendering

Føhn can render declared parts of a normal page as HTML. Add `?foehn_sections=name` to a page URL to run the same WordPress query, template controller, page Twig template, and section context as a full-page request.

There is no REST endpoint and no section configuration. A page declares the sections it owns in Twig.

## Migrate from the Render API

The Render API, `RenderApiConfig`, `RenderApiHook`, and `/wp-json/foehn/v1/render` route were removed. Section rendering replaces browser-facing partial rendering, but it deliberately does not expose arbitrary Twig paths or client-supplied template context.

| Render API                                                  | Section rendering                                                                                 |
| ----------------------------------------------------------- | ------------------------------------------------------------------------------------------------- |
| Allow template paths in `render-api.config.php`             | Declare fixed names with `foehn_section()` in the page that owns them                             |
| Send `template` or a keyed `templates` object to a REST URL | Add one name or up to five comma-separated names to the normal page URL                           |
| Send scalar context values in the query string              | Build context in the page controller, context providers, and explicit server-side section context |
| Parse JSON and read `html` or keyed values                  | Read the HTML response directly; each section has a stable wrapper ID                             |
| Set `cacheMaxAge` on the endpoint                           | Section responses are cached by the Føhn page cache under the same rules as pages, with nothing to configure |
| Enable debug details in the response                        | Read exception details from the server log; public error HTML does not expose them                |

Move each public partial to `templates/sections/{name}.twig`, declare it in its normal page template, and update any context-provider target from the old template path, such as `partials/results`, to the conventional section path, `sections/results`. Replace REST requests such as `/wp-json/foehn/v1/render?template=partials/results` with the page URL, such as `/products/?foehn_sections=results`. A former multi-template request becomes one ordered selection such as `?foehn_sections=filters,results`.

Do not copy old request parameters directly into Twig context. Make each supported value part of the normal page URL, validate it in the controller, and pass only the normalized value to the page template:

```php
$sort = match (filter_input(INPUT_GET, 'sort', FILTER_UNSAFE_RAW)) {
    'price' => 'price',
    'title' => 'title',
    default => 'recent',
};

return $this->view->render('pages/products', $context->with('sort', $sort));
```

```twig
{{ foehn_section('results', { posts, sort }) }}
```

Both `/products/?sort=price` and `/products/?sort=price&foehn_sections=results` then run the same controller validation and rebuild the same server-owned context.

## Declare a section

Use `foehn_section()` in a page template:

```twig
{# templates/pages/archive.twig #}
{% extends 'layouts/base.twig' %}

{% block content %}
  {{ foehn_section('archive-results', { posts }) }}
{% endblock %}
```

The helper renders `templates/sections/archive-results.twig`:

```twig
{# templates/sections/archive-results.twig #}
<div class="grid">
  {% for post in posts %}
    {% include 'components/card.twig' with { post } only %}
  {% endfor %}
</div>
```

A normal page request returns this boundary:

```html
<div id="foehn-section-archive-results" data-foehn-section="archive-results">
  <!-- section template HTML -->
</div>
```

Names use lowercase letters, numbers, and single hyphens. A name must start and end with a letter or number and can contain at most 64 characters. Each name should be declared once on a page. A normal page logs and skips a duplicate; a selected request rejects it because the context would be ambiguous. Sections cannot be nested because only declarations in the page shell can be selected directly.

## Context

`foehn_section()` receives the active Twig context. Its explicit context is merged on top, so explicit values replace values with the same key:

```twig
{{ foehn_section('card-grid', { posts: filtered_posts, heading: 'Results' }) }}
```

The section template also uses normal [context providers](/guide/context-providers). A provider registered for `sections/card-grid` runs when that section template renders.

The active Twig context is captured when `foehn_section()` runs. This capture is shallow. Pass loop-sensitive values such as `post`, `posts`, pagination, and loop indexes in the section context. Section templates and their context providers must not depend on mutable WordPress loop globals such as `get_the_ID()` because a selected section renders after the page shell finishes.

On a selected request, Føhn first validates the request syntax, then runs the normal controller and page template to rebuild page-local state. Calls to `foehn_section()` declare matching sections but do not render their templates or context providers during this collection pass. Føhn checks that every requested declaration exists before it renders any selected section template. If one declaration or render fails, it returns one error and no partial section HTML.

## Request sections

Request one section with the normal page URL:

```text
GET /projects/?type=web&foehn_sections=archive-results
```

Request up to five sections with one comma-separated parameter:

```text
GET /projects/?type=web&foehn_sections=filters,archive-results
```

The response is `text/html; charset=UTF-8`. Sections are concatenated in request order. Føhn supports `GET` and `HEAD`; a `HEAD` response has no body.

The base `Fetch` component can keep a link or form destination as the no-JavaScript fallback and override only its AJAX request with `data-option-src`:

```html
<a
  href="/projects/?page=2"
  data-component="Fetch"
  data-option-src="/projects/?page=2&foehn_sections=archive-results"
>
  Next page
</a>
```

For a `GET` form, Fetch keeps the fixed section selection from `data-option-src` and adds the current form fields:

```html
<form
  action="/projects/"
  method="get"
  data-component="Fetch"
  data-option-src="/projects/?foehn_sections=filters,archive-results"
>
  <!-- filters -->
</form>
```

Use `foehn_section_url()` when the section request targets the current page and must preserve its query state:

```twig
<button
  data-component="Action Fetch"
  data-option-src="{{ foehn_section_url('archive-results') }}"
  data-on:click="Fetch.fetch()">
  Refresh results
</button>
```

Pass a normal target URL as the second argument for pagination or other links to another page. Føhn keeps the target path and query state, forces the URL onto the current origin, and adds the section selection:

```twig
<a
  href="{{ page.link }}"
  data-component="Fetch"
  data-option-src="{{ foehn_section_url('archive-results', page.link) }}">
  {{ page.title }}
</a>
```

Register the base `Fetch` component from `@studiometa/ui` with the action component used above:

```js
import { registerComponent } from "@studiometa/js-toolkit";
import { Action, Fetch } from "@studiometa/ui";

registerComponent(Action);
registerComponent(Fetch);
```

`Fetch` reads the returned HTML and replaces the element whose `id` matches the response wrapper. No JSON parsing and no Datastar integration are required.

The current `Fetch` history option records the fetched URL as-is. If you enable Fetch history, the browser history entry includes `?foehn_sections=...`. Do not enable it when the address must stay a normal full-page URL, or update history in application code after the fetch.

## Lazy sections

Pass `lazy: true` to defer a section on a normal page request:

```twig
{{ foehn_section('related-projects', { post }, lazy: true) }}
```

Føhn does not render the section template or its context providers during the full-page render. The page controller still runs and Twig still evaluates the context passed to `foehn_section()`; lazy mode defers section rendering, not data loading or context creation. It emits a neutral `LazyInclude` placeholder with `data-option-src`, a loading ref, and a hidden error ref. The placeholder does not use the final section ID, so the fetched wrapper cannot create nested duplicate IDs.

Register `LazyInclude` in the existing application entry:

```js
import { registerComponent } from "@studiometa/js-toolkit";
import { LazyInclude } from "@studiometa/ui";

registerComponent(LazyInclude);
```

On a selected request, `lazy` is ignored and Føhn returns the real wrapped section. The placeholder uses inline `display: none` for its error ref because `LazyInclude` shows that ref with an inline display value. `LazyInclude` 1.10 does not hide its loading ref after a rejected network request. Add this rule to hide the loading state when the error appears:

```css
[data-foehn-lazy-section] > [data-ref="error"][style*="display: block"] + [data-ref="loading"] {
  display: none;
}
```

`LazyInclude` also treats a non-success HTTP response body as content. Applications that need custom 404 or 500 feedback must handle the status at the request layer until `LazyInclude` rejects non-success responses.

## Query helpers and page cache

`foehn_sections` is a control parameter. During a valid section request, Føhn temporarily removes it from `$_GET` and `$_SERVER['REQUEST_URI']` while the controller, page template, section templates, and context providers run. Native WordPress pagination and URL helpers therefore create normal page URLs. Føhn restores the original request state before it returns the response.

`query_get()`, `query_has()`, `query_all()`, and `query_hidden_inputs()` also hide the parameter. Query URL helpers remove it when they create normal page URLs. When the page cache is active, section URLs omit its ignored query arguments so cached HTML cannot carry one visitor's tracking parameters into later section requests. Configure an equivalent ignored-argument policy at any CDN that caches the same pages.

Section responses are cached, under exactly the rules a page is. `foehn_sections` is a cache-key query argument on every configuration, with nothing to enable: `/products/?foehn_sections=listing` stores as `index__foehn_sections=listing&.html` beside the page's own `index.html`, and the second request for the same selection is served from that file. nginx serves it directly; Apache cannot key a query argument, so it falls through to the `advanced-cache.php` reader a few milliseconds later.

A request the cache would not store — a logged-in visitor, a `HEAD`, an error status, an environment the cache is off in — still returns `Cache-Control: private, no-store`. That decision is the page cache's own eligibility check rather than a second rule, so the two cannot disagree.

A project cannot add `foehn_sections` to ignored query arguments, and cannot give it a pattern of its own. An ignored one would key a section request onto the whole page's file, so one visitor would ask for a fragment and be handed a page; a project pattern would key values the parser refuses. See [the page cache guide](/guide/page-cache#caching-section-responses).

Every section response carries `X-Robots-Tag: noindex, nofollow`, cached or not.

## Limits and errors

| Status | Cause                                                               |
| ------ | ------------------------------------------------------------------- |
| `400`  | Empty, unsafe, repeated, duplicate, or more than five section names |
| `404`  | Missing controller, `null` controller result, or undeclared section |
| `405`  | A method other than `GET` or `HEAD`                                 |
| `500`  | The page or a selected section could not render                     |

All errors are HTML. Error responses do not include exception details.
