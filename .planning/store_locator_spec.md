# Føhn store locator package

Status: proposed, design approved for planning. Not implemented. Two upstream changes are prerequisites, see §3.

A reusable `studiometa/foehn-store-locator` package that owns the WordPress store model, native editing fields, spatial search, facets, and server-rendered templates for a store listing and map. The browser uses `@studiometa/ui@next`, `@studiometa/js-toolkit@next`, and `@studiometa/ui-mapbox@next` directly. There is no locator-specific npm package.

The reference implementation is the Shopify Search app store locator in `gitlab.studiometa.dev/packages/shopify/apps/packages/search` (`dev-theme/locator/main.js` and `dev-theme/sections/store-locator.liquid`). Its useful seam is the same rendered store item acting as both a list row and a `MapboxClusterItem`, and a map that survives list replacements because only the item list is swapped. Its Meilisearch and Shopify metaobject implementation, its custom `StoreLocatorDemo` component, and its client-only geocoder are not carried into WordPress.

## 1. Decisions

- The feature lives in a separate Composer package, `studiometa/foehn-store-locator`, not in Føhn core.
- WordPress is the source of truth. There is no remote search index, custom REST endpoint, or duplicated store database in the first version.
- The package uses native registered post meta and a dedicated WordPress meta box. It does not depend on ACF.
- The store archive is the `store` post type archive. Its slug is configurable and defaults to `store`. A theme owns the full page layout and template controller. The package owns the body fragments and the search.
- The search runs on the WordPress main query. The package narrows it in WordPress hooks, so native pagination, Timber pagination, and Føhn `Facets` work without arguments, as on the demo project archive.
- The list and map are two projections of one `StoreSearchResult`. They use the same query parameters, selected filters, position scope, total, and facet counts.
- Position is a spatial constraint, not a facet group. Every facet count keeps the current spatial constraint.
- Taxonomies are the first supported facet source. They use WordPress's own taxonomy query vars. Free-form post meta is not a facet source until a concrete normalization requirement exists.
- The package renders Føhn sections. `Fetch` morphs filters, summary, results, and pagination in one response. The map element is never inside a swapped section.
- The geocoder is a standalone `MapboxGeocoder` that does not need a map. It works in the list view and the map view.
- List and map are separate pages of the same URL state. The switch is a normal link, not a Fetch swap.
- The package emits declarative `Action`, `Fetch`, and Mapbox markup. It ships no browser implementation of those behaviors.
- The first version targets store sets of approximately 250 records. A larger set is a reason to design a remote search adapter, not to add one speculatively.

## 2. Package boundary

The package is one deep module with this public interface:

```php
Store::query();
Store::locatable();
StoreSearch::current(): StoreSearchResult;
new StoreLocatorConfig(...);
StoreLocatorHooks::class;
```

Twig callers use namespaced templates:

```twig
{% include '@store-locator/Filters.twig' with { result } only %}
```

The package hides:

- custom post type and post meta registration;
- native field rendering and saving;
- coordinate validation and Mapbox coordinate ordering;
- request parsing and normalization;
- text and spatial filtering;
- distance ordering;
- main query narrowing;
- position-aware facet counts;
- list and map result projections;
- the required `@studiometa/ui-mapbox` DOM contract;
- template lookup and override precedence.

The package does not own:

- the theme layout;
- project CSS;
- project-specific facet taxonomies;
- a Mapbox access token management screen;
- Mapbox, Fetch, or Action browser behavior;
- geocoding or distance calculations on a remote service;
- CSV import.

## 3. Dependencies

### Upstream prerequisites

Two changes must ship before Phase 5. Neither is specific to the store locator.

**`@studiometa/ui-mapbox`: standalone `MapboxGeocoder`** ([studiometa/ui#663](https://github.com/studiometa/ui/issues/663)). In `2.0.0-alpha.0`, `MapboxGeocoder` extends `AbstractMapboxMapChild`. It builds its control inside `whenMapReady()`, so without an ancestor `MapboxMap` it waits for `MAPBOX_MAP_CONNECTED` forever and renders nothing. The Shopify demo avoids the problem because its whole interface lives inside the `MapboxMap` element, and its geocoder only moves the camera on the client.

`@mapbox/mapbox-gl-geocoder` does not need a map: `addTo(HTMLElement)` calls `onAdd()` without one, and every map operation is guarded by `if (this._map)`. The change:

- without `addToMap`, the component creates its control on mount, adds it to `$el`, and emits `map-result`, whether or not a parent map exists;
- the access token comes from `options.accessToken`, and falls back to a parent map's token only when a parent exists;
- `mapboxgl` is passed only when a parent map exists, because the library needs it only for the result marker;
- with `addToMap`, the current parent-map behavior is unchanged.

**Føhn core: section requests keep an error status.** `TemplateControllerDiscovery::renderSelectedSections()` sends every rendered selection with status `200`. A controller that calls `status_header(400)` therefore answers `400` for a full page and `200` for the same URL with `?foehn_sections=`. The change: when the controller has set a status of `400` or above, the section request answers `SectionResponse::error()` with that status. This keeps the full page and its sections in agreement and keeps error responses out of the page cache.

### PHP

The Composer package requires the exact matching Føhn version and opts into Tempest discovery:

```json
{
  "name": "studiometa/foehn-store-locator",
  "require": {
    "php": "^8.5",
    "studiometa/foehn": "<exact release version>"
  },
  "extra": {
    "tempest": {
      "can-discover": true
    }
  }
}
```

It does not require `studiometa/foehn-acf`.

### Browser

The integrating theme uses the `next` release line:

| Package                       | Version observed while planning |
| ----------------------------- | ------------------------------- |
| `@studiometa/js-toolkit@next` | `4.0.0-alpha.1`                 |
| `@studiometa/ui@next`         | `2.0.0-alpha.0`                 |
| `@studiometa/ui-mapbox@next`  | `2.0.0-alpha.0`                 |
| `mapbox-gl`                   | `^3.0.0`                        |
| `@mapbox/mapbox-gl-geocoder`  | `^5.0.0`                        |

The integration needs the first `@studiometa/ui-mapbox@next` release with a standalone `MapboxGeocoder`. The lockfile pins the installed prerelease versions.

`Fetch` in `@studiometa/ui@2.0.0-alpha.0` already provides the two behaviors this design relies on: a GET form folds every value of a repeated field onto `src`, and history receives the element's own destination (`historyUrl`), not `src`.

The theme entry imports:

```js
import "@studiometa/ui/autoload";
import "@studiometa/ui-mapbox/autoload";
import "mapbox-gl/dist/mapbox-gl.css";
import "@mapbox/mapbox-gl-geocoder/dist/mapbox-gl-geocoder.css";
```

## 4. Store data model

### Post type

`Store` extends `Studiometa\Foehn\Models\Post` and carries `#[AsPostType]`.

| Property       | Decision                                           |
| -------------- | -------------------------------------------------- |
| Name           | `store`                                            |
| Public         | yes                                                |
| REST           | yes                                                |
| Archive        | yes, at `StoreLocatorConfig::$archiveSlug`         |
| Single rewrite | `/{archiveSlug}/{slug}/`                           |
| Supports       | title, editor, thumbnail, revisions, custom-fields |
| Title          | store name                                         |
| Editor         | optional description                               |
| Featured image | optional store photo                               |

A change of `archiveSlug` needs a rewrite rule flush, as any post type rewrite change does.

The package does not render a full single-store page. A theme can use the normal `single-store` hierarchy and the package item fragments.

### Registered meta

| Key           | Type   | Purpose                        |
| ------------- | ------ | ------------------------------ |
| `street`      | string | Street address                 |
| `postal_code` | string | Postal code                    |
| `city`        | string | City display value             |
| `country`     | string | Country display value          |
| `phone`       | string | Display phone number           |
| `email`       | string | Contact email                  |
| `website`     | string | External store URL             |
| `latitude`    | number | Latitude from `-90` to `90`    |
| `longitude`   | number | Longitude from `-180` to `180` |

All keys are registered for the `store` subtype and exposed through REST. The model treats invalid or incomplete coordinates as absent. It never maps them to `[0, 0]`.

### Model interface

The model provides typed accessors and derived values. `query()` comes from Føhn's `QueriesPostType`.

```php
final class Store extends Post
{
    public function coordinates(): ?StoreCoordinates;

    public function isLocatable(): bool;

    /** @return list<string> */
    public function addressLines(): array;

    public function phone(): ?string;

    public function phoneUri(): ?string;

    public function email(): ?string;

    public function website(): ?string;

    /** @return list<self> */
    public static function locatable(): array;
}
```

`StoreCoordinates` validates latitude and longitude and exposes Mapbox ordering explicitly:

```php
final readonly class StoreCoordinates
{
    /** @return array{0: float, 1: float} [longitude, latitude] */
    public function toLngLat(): array;
}
```

Opening hours stay out of the first version. A free-text field, seven day fields, and structured periods have different interfaces and should not be selected without a project requirement.

## 5. Native WordPress editing

`StoreLocatorHooks` registers a dedicated meta box for the `store` post type. It uses WordPress APIs and native HTML controls.

The meta box has these groups:

1. Location: latitude and longitude.
2. Address: street, postal code, city, and country.
3. Contact: phone, email, and website.

Saving must:

- require the store edit capability;
- require a valid nonce;
- skip autosaves and revisions;
- preserve a valid numeric zero;
- sanitize text, email, URL, and phone values;
- reject latitude outside `-90..90`;
- reject longitude outside `-180..180`;
- avoid deleting stored fields when the meta box is absent from a request;
- report invalid coordinates in the editor without storing false data.

The meta box and REST schema write the same registered keys. The model remains defensive because REST clients and imports can bypass the meta box.

## 6. Configuration

The package ships a default `StoreLocatorConfig` through `store-locator.config.php`. An application overrides it with `app/store-locator.config.php`, as `foehn-acf` does with `acf.config.php`.

The initial interface is equivalent to:

```php
final readonly class StoreLocatorConfig
{
    public function __construct(
        public string $archiveSlug = 'store',
        public string $accessToken = '',
        public string $style = 'mapbox://styles/mapbox/light-v11',
        public array $center = [2.5, 47.0],
        public float $zoom = 5,
        public array $geocoderOptions = [],
        public array $facetTaxonomies = [],
        public int $listPageSize = 24,
        public int $mapResultLimit = 250,
        /** @var list<int> Kilometres offered by the radius control. */
        public array $radiusOptions = [10, 25, 50, 100],
        public int $defaultRadius = 25,
    ) {}
}
```

`defaultRadius` must be one of `radiusOptions`.

The access token is a public, origin-restricted Mapbox token. The package does not store it in WordPress options.

Facet taxonomy names are an allowlist. A name that is absent or not attached to `store` is ignored in production and reported when Føhn debug mode is enabled.

## 7. Archive request contract

The archive uses one GET contract for full pages and section requests.

| Parameter                 | Values                                           | Default         | Meaning                                            |
| ------------------------- | ------------------------------------------------ | --------------- | -------------------------------------------------- |
| `q`                       | text, at most 100 characters                     | absent          | Search store name, description, and address fields |
| `view`                    | `list`, `map`                                    | `list`          | Result projection                                  |
| `near`                    | `latitude,longitude`                             | absent          | Geocoder-selected origin                           |
| `radius`                  | one of `radiusOptions`                           | `defaultRadius` | Radius around `near`, in kilometres                |
| `bbox`                    | `west,south,east,north`                          | absent          | Explicit map viewport scope                        |
| configured taxonomy names | WordPress query var: `name[]=slug` or `name=a,b` | absent          | Facet selections                                   |

Pagination is WordPress's own `/{archiveSlug}/page/{n}/`. There is no `page` parameter. The filter form's action is the archive URL, so every form submission returns to the first page.

There is no `sort` parameter. Results are ordered by distance when `near` is active, and by title otherwise.

Request rules:

- an empty value is absent;
- `radius` without `near` is dropped from the normalized request;
- `near` without `radius` uses `defaultRadius`;
- `near` and `bbox` together are a `400`;
- `near` is rounded to five decimal places before it enters the normalized request or a generated URL;
- `bbox` longitudes are normalized from the raw map bounds: a span of 360° or more removes the longitude constraint; otherwise both longitudes are wrapped into `-180..180`, and a wrapped east below the wrapped west crosses the antimeridian; the normalized values are rounded to five decimal places;
- `bbox` latitudes must be within `-90..90` with south below north;
- a term slug that does not exist in its configured taxonomy is a `400`;
- a repeated scalar parameter (`q`, `view`, `near`, `radius`, `bbox`), an unknown `view`, a `radius` outside `radiusOptions`, an out-of-range coordinate, or a malformed non-empty value is a `400`, not a guessed query.

`StoreSearchRequest::fromQueryString()` owns parsing and normalization. It reads the raw query string, as `SectionRequest` does, so repeated parameters stay visible. Templates and search code receive only the normalized object.

Example URLs:

```text
/store/?q=repair&store_service[]=delivery
/store/?near=45.764,4.8357&radius=25
/store/?bbox=4.70,45.60,5.00,45.90&view=map
/store/page/2/?store_service[]=repair
```

## 8. Search pipeline

### Hooks

`StoreLocatorHooks` attaches the search to the main query of the store archive:

1. The `request` filter parses `StoreSearchRequest` before WordPress parses the main query. An invalid request is recorded, and its taxonomy query vars are removed. An unknown term therefore never turns the archive into a `404` taxonomy view.
2. `pre_get_posts` on the main store archive query sets `post__in` to the eligible IDs, `posts_per_page` to `listPageSize` for the list view or `mapResultLimit` for the map view, and the order. An invalid request sets `post__in` to `[0]`.
3. `template_redirect` sends status `400` for an invalid request. It runs after `WP::handle_404()`, which would otherwise reset the status. The Føhn core change in §3 makes section requests keep it.

`StoreSearch::current()` builds the `StoreSearchResult` from the main query once per request and memoizes it. The theme controller passes it to the page template, and every section receives the same object.

### Eligible IDs

The search first loads published, locatable stores and computes the eligible post IDs from the constraints that WordPress taxonomy queries do not own:

1. `q` is matched case-insensitively against title, description, street, postal code, city, and country.
2. `near/radius` uses the Haversine formula and records distance in metres.
3. `bbox` keeps coordinates inside west, south, east, and north, with the antimeridian rule from §7.
4. With `near`, the eligible IDs are ordered nearest first and the query uses `orderby=post__in`. Without `near`, the query orders by title.

An empty eligible set must become `post__in = [0]`. WordPress interprets an empty `post__in` as no restriction, which would return every store.

WordPress applies the configured taxonomy query vars itself, as it does on the demo project archive.

A later optimization can reduce the candidate set with numeric meta ranges before the Haversine calculation. The observable interface does not change.

### Result

`StoreSearchResult` contains:

- normalized request;
- whether the request was invalid;
- total matching stores;
- current list page and total list pages;
- list stores;
- map stores;
- whether the map limit was exceeded;
- distance by store ID when applicable;
- facet groups and counts;
- selected filters;
- the bounds to frame on the map: the returned map stores, plus the `near` origin when present, or none for a `bbox` request or an empty result.

## 9. Position-aware facets

Position is a base scope, not a facet group.

For the final result, all selected taxonomy facets are applied to the eligible IDs produced by text and position filtering.

For each facet group, counts are computed with:

1. text search retained;
2. position scope retained;
3. all other selected facet groups retained;
4. this facet group's own constraint removed.

Example: 18 stores are within 25 km, 7 offer repairs, and 4 offer delivery. The service facet reports `Repairs (7)` and `Delivery (4)` within that 25 km scope, not across the full database.

Føhn `Facets::for($taxonomy)` provides these counts from the main query with no extra argument. Its query clone keeps the spatial `post__in` while `Facets::varsWithout()` removes only the taxonomy being counted. Its `MAX_COUNTED_POSTS` of 2000 is well above the supported data set.

The first version supports taxonomy facets only. Projects define taxonomies such as `store_service` or `store_type` and list them in `StoreLocatorConfig::facetTaxonomies`. Address fields remain display and text-search data. They are not converted into facet vocabularies automatically.

## 10. List and map views

The two views consume the same `StoreSearchResult`. Each view is a full page of the same URL state. The view switch is a pair of links in the summary section, so their URLs follow every Fetch update and work without JavaScript.

### Listing view

- Uses `listPageSize`, initially 24.
- Renders linked, semantic store cards.
- Shows distance when `near` is active.
- Renders native pagination whose links keep the complete query state.
- Works without JavaScript.

### Map view

- Renders all matching stores up to `mapResultLimit`, initially 250.
- Uses one `MapboxClusterItem` element as both the sidebar item and the map feature. Each item root carries `id="store-{ID}"`, so a morph keeps the node of a store that stays in the result and mounts or destroys the others.
- Uses `StoreLocator`, `MapboxMap`, `MapboxNavigationControl`, `MapboxCluster`, and `MapboxClusterItem` from `@studiometa/ui-mapbox@next`. The geocoder is outside the map (§12).
- Keeps the `MapboxMap` element outside every section. Only the cluster items are inside the `store-results` section, which sits inside `MapboxCluster`. A Fetch update therefore never destroys the map, its canvas, or its camera.
- Does not use `StoreLocator`'s `fitOnUpdate`. It fits raw bounds without a maximum zoom, so a single store zooms to the rooftop. This is the reason the Shopify demo replaced it. Framing is described in §12.
- Uses popup content from the item template.
- Does not paginate inside the map.
- Does not silently truncate. When the total exceeds the map limit, it asks the visitor to refine the search instead of presenting an incomplete map as complete.

## 11. Føhn sections and Fetch

The theme page declares these sections:

```text
store-filters
store-summary
store-results
store-pagination
```

`store-pagination` renders nothing in the map view.

The filter form is inside `store-filters`. It is a normal GET form whose action is the archive URL. It requests all four sections through `data-option-src`:

```twig
{% set archive_url = fn('get_post_type_archive_link', 'store') %}
{% set store_sections = 'store-filters,store-summary,store-results,store-pagination' %}

<form
  id="store-search"
  action="{{ archive_url }}"
  method="get"
  data-component="Action Fetch"
  data-option-history
  data-option-mode="morph"
  data-option-selector="[data-foehn-section]"
  data-option-src="{{ foehn_section_url(store_sections, archive_url) }}"
  data-on:change.debounce300="() => $el.requestSubmit()">
```

The options follow the demo project archive (`sections/project-filters.twig`), with one difference:

- `mode="morph"` keeps the form node, the `Fetch` instance that runs the request, and the focused control while the server's answer is written into them. `replace` would destroy all three.
- `selector="[data-foehn-section]"` instead of the default `[id]`. A section response contains only section wrappers, so both selectors match the same elements there. On back and forward navigation, `Fetch` requests the full page URL. With `[id]`, it would also morph every layout element that has an `id`, and morphdom would remove the Mapbox canvas from the map. With `[data-foehn-section]`, it updates the sections only.
- `history` writes the archive URL with the form fields, not `src`, so `?foehn_sections=` never reaches the address bar.
- `Fetch.fetch()` aborts the request in flight, so the last change wins.

Request state lives in form controls inside the morphed section, rendered by the server from the normalized request:

```twig
<input type="hidden" name="view" value="{{ result.request.view }}">
<input type="hidden" name="near" value="{{ result.request.near }}" {{ result.request.near ? '' : 'disabled' }}>
<input type="hidden" name="bbox" value="{{ result.request.bbox }}" {{ result.request.bbox ? '' : 'disabled' }}>
{% if result.request.near %}
  <select name="radius">…</select>
{% endif %}
```

A position control is `disabled` while it is empty, so the form does not submit `near=` or `bbox=`. The page cache bypasses a request that has any unknown query argument, even an empty one. With the controls disabled, an unpositioned request can still be cached.

Pagination links are `Fetch` links with `foehn_section_url(store_sections, page.link)`, as in the demo pagination component, with the same `mode` and `selector`.

The sections update atomically after the archive controller reads one `StoreSearchResult`. The new filters carry updated counts and selected states. The summary, result view, and pagination cannot disagree with them.

A no-JavaScript submission renders the same state as a full page.

A `400` response is a Fetch error. The page keeps its last valid sections and shows the error state from §15.

## 12. Browser position interactions

The effects below are expressions. A body with statements is an arrow function, which `Action` calls. Effect bindings are `event`, `target`, `action`, `$el`, and each co-located component by name. A `Name(selector) ->` prefix selects the target. `data-option-target` applies only to the `data-option-on`/`data-option-effect` pair, not to `data-on:*` attributes.

### Address selection

The geocoder is a standalone `MapboxGeocoder` (§3) outside the form and outside every section. Its input and suggestions are generated by the Mapbox library, so a morph must never reach them. It is the same element in the list view and the map view.

```twig
<div
  data-component="Action MapboxGeocoder"
  data-option-options="{{ geocoder_options|json_encode|e('html_attr') }}"
  data-on:map-result="Fetch(#store-search) -> () => {
    const [lng, lat] = event.detail.result.center;
    const { near, bbox } = target.$el.elements;
    near.value = `${lat},${lng}`;
    near.disabled = false;
    bbox.value = '';
    bbox.disabled = true;
    target.$el.requestSubmit();
  }">
</div>
```

`geocoder_options` carries `accessToken` and `StoreLocatorConfig::$geocoderOptions`. The radius control is absent when there is no `near`, so the server applies `defaultRadius`. A radius the visitor already selected stays.

The geocoder input text is presentation only. The `near` control is the request state. After a back or forward navigation, the input can show an older address than the restored `near`.

### Map framing

The map fits `result.bounds` with a padding and a maximum zoom:

- on the first load, through the `bounds` and `fitBoundsOptions` of `MapboxMap`'s map options;
- after each Fetch update, through an `Action` on the form, read from the `data-bounds` attribute that `Results.twig` renders when bounds are present.

```twig
data-on:fetch-update-after="MapboxMap(#store-map) -> () => {
  const bounds = document.querySelector('#foehn-section-store-results [data-bounds]')?.dataset.bounds;
  if (bounds) target.map.fitBounds(JSON.parse(bounds), { padding: 60, maxZoom: 12 });
}"
```

In the list view there is no `MapboxMap` instance, so the effect has no target and does not run. A `bbox` request has no bounds, so the map keeps the viewport the visitor selected.

### Search this area

Moving the map does not fetch automatically. Automatic `moveend` requests create request storms, make panning unstable, and can loop when new results refit the map.

The `Search this area` button sits in the map shell, outside every section, and is associated with the form through `form="store-search"`. It is hidden until a map movement made by the visitor. A `map-moveend` event with an `originalEvent` comes from the visitor. A move from `fitBounds()` or `flyTo()` has none, so framing never shows the button.

```twig
<div
  id="store-map"
  data-component="MapboxMap Action"
  data-on:map-moveend="() => {
    if (event.detail.event.originalEvent) {
      document.getElementById('store-search-area').hidden = false;
    }
  }">
  …
  <button
    id="store-search-area"
    type="button"
    form="store-search"
    hidden
    data-component="Action"
    data-on:click="MapboxMap(#store-map) -> () => {
      const { near, radius, bbox } = $el.form.elements;
      const bounds = target.map.getBounds();
      bbox.value = [
        bounds.getWest(),
        bounds.getSouth(),
        bounds.getEast(),
        bounds.getNorth()
      ].join(',');
      bbox.disabled = false;
      near.value = '';
      near.disabled = true;
      if (radius) radius.disabled = true;
      $el.hidden = true;
      $el.form.requestSubmit();
    }">
    Search this area
  </button>
</div>
```

The button sends the raw bounds. With world copies, `getBounds()` can return longitudes outside `-180..180`. The server normalizes them (§7), so the client does not wrap them.

### Clear position

A clear button inside `store-filters`, rendered only while `near` or `bbox` is active, disables and empties `near`, `radius`, and `bbox`, then submits the form. The full unpositioned result and its facet counts return.

### List and map switch

The switch is two links rendered in `store-summary` with the current normalized query state and the other `view`. They are normal navigations. The map loads only on the map page, and each view keeps its own page structure.

## 13. Future `DataScope.patch()` migration

The current native-form implementation remains the approved first implementation.

A proposed `DataScope.patch()` method is tracked in [studiometa/ui#662](https://github.com/studiometa/ui/issues/662). Its intended interface applies several keyed values atomically, updates mounted `DataModel` sources, and notifies subscribers once after the complete patch.

Migration is optional. It must reduce markup and preserve native form submission, Fetch history, no-JavaScript behavior, request normalization, and the rule that an empty position control is not submitted.

## 14. Templates and override seam

`StoreLocatorHooks` registers two Twig namespaces through Timber's `timber/locations` filter:

| Namespace            | Lookup order                                                  |
| -------------------- | ------------------------------------------------------------- |
| `@store-locator`     | child theme override, parent theme override, package template |
| `@store-locator-pkg` | package template only                                         |

This is the first package-registered Twig namespace in Føhn. `TimberViewEngine::exists()` checks theme directories only, so it reports `false` for a namespaced template. Package templates are reached through Twig includes, which use the loader directly.

The initial templates are:

```text
templates/
├── Filters.twig
├── Facet.twig
├── Geocoder.twig
├── Summary.twig
├── ViewSwitch.twig
├── Results.twig
├── StoreList.twig
├── MapItems.twig
├── Map.twig
├── StoreItem.twig
├── StorePopup.twig
├── Pagination.twig
└── Empty.twig
```

`Results.twig` renders `StoreList.twig` or `MapItems.twig` for the request's view, and the `data-bounds` attribute when bounds are present.

`Map.twig` renders the map shell: `StoreLocator`, `MapboxMap` with its `container` ref, `MapboxNavigationControl`, `MapboxCluster`, and the `Search this area` button. It exposes an `items` block inside `MapboxCluster`. The theme fills it with the section declaration, so the package does not declare sections:

```twig
{% embed '@store-locator/Map.twig' with { result, config } %}
  {% block items %}
    {{ foehn_section('store-results', { result }) }}
  {% endblock %}
{% endembed %}
```

Templates accept explicit values instead of running queries. `StoreSearch::current()` runs once, and every section receives the same result.

The package templates own semantic structure, required data attributes, refs, escaping, and accessible fallback text. The theme owns layout, map height, typography, colors, and responsive CSS.

The package does not own a full archive template because it cannot know the theme's base layout. A theme archive controller renders its own page template, which includes the package fragments.

Føhn section names resolve only through `templates/sections/{name}.twig`. The integrating theme therefore owns four thin wrappers:

```text
theme/templates/sections/
├── store-filters.twig
├── store-summary.twig
├── store-results.twig
└── store-pagination.twig
```

Each wrapper includes the matching `@store-locator` package template with the explicit `StoreSearchResult` context. The package does not change Føhn section resolution or expose client-selected template paths.

## 15. Progressive enhancement and errors

- Without JavaScript, the GET form reloads the archive with correct filters and results. Text search covers place names. The geocoder and `Search this area` need JavaScript.
- Without a Mapbox token, the list remains available, the geocoder is not rendered, and the map page renders a configurable unavailable state.
- Without Mapbox network access, server-rendered list links and details remain available.
- Without coordinates, a store is not included in locatable results and is never placed at `[0, 0]`.
- With no matches, all sections render a consistent empty state and the map is not framed.
- An invalid request answers `400` for the full page and for its sections. The full page renders the theme layout with an invalid-search state and a link to the unfiltered archive. It does not silently widen the result.
- A map result count above the configured limit renders a refine-search state. It does not silently omit matching stores.
- Fetch error and loading states use its public lifecycle events and remain inside the page's accessible status region.
- Before Search this area is submitted, `StoreLocator` may hide and reorder sidebar items for the current client viewport. This is presentation state only: the server summary and facets continue to describe the last submitted spatial scope. The interface labels these counts separately and exposes the pending Search this area action.

## 16. Security and output rules

- Every meta-box mutation requires capability, nonce, and valid request context checks.
- Every request parameter has a type, length, count, and range limit.
- Facet taxonomy names come only from configuration, not directly from the request.
- Term values are validated against their configured taxonomy.
- Mapbox options, bounds, and coordinate arrays are JSON encoded and HTML-attribute escaped.
- Store titles, addresses, contacts, URLs, popup content, and facet labels are escaped for their output context.
- The public Mapbox token must be origin restricted. It is not treated as a server secret.
- The search result does not expose private or draft stores.

## 17. Performance characteristics

The first implementation can load and evaluate all published locatable stores in process because the target data set is approximately 250 stores.

The cost is:

- one store candidate read;
- in-process text and spatial filtering;
- the main query over eligible IDs;
- one ID query and one grouped count per taxonomy facet through Føhn `Facets`.

Position requests are not stored by Føhn's static page cache in the first version. `near`, `radius`, and `bbox` are deliberately absent from `PageCacheConfig::cacheQueryArgs`, so the unknown-query rule bypasses both full-page and section caching. The form never submits them empty (§11). Normalization still rounds coordinates so copied URLs are stable.

Unpositioned requests can use the existing cache policy. The integration lists `q` with a pattern, `view` with `['list', 'map']`, and each facet taxonomy with a slug pattern in `cacheQueryArgs`. Pagination is a path, so it needs no entry.

A store set that cannot be loaded and filtered within the response budget is the trigger for a separate remote search design. No repository port is added before that second implementation exists.

## 18. Delivery plan

### Phase 0 — prerequisites

- Land the standalone `MapboxGeocoder` change, [studiometa/ui#663](https://github.com/studiometa/ui/issues/663) (§3), and wait for an `@studiometa/ui-mapbox@next` release.
- Make Føhn section requests keep a controller's error status (§3), with tests for the full page and section paths.

### Phase 1 — package and model

- Add `packages/store-locator` and Composer discovery metadata.
- Add `Store`, `StoreCoordinates`, post type registration with the configurable archive slug, and post meta schemas.
- Add the native meta box and save rules.
- Add model and editor unit tests.

### Phase 2 — request and search

- Add `StoreSearchRequest`, strict query parsing, `bbox` normalization, and the `request`, `pre_get_posts`, and `template_redirect` hooks.
- Add text matching, bounding-box filtering, Haversine radius filtering, and distance ordering.
- Add `StoreSearch::current()` and `StoreSearchResult` with list and map projections and bounds.
- Add empty-ID, invalid-request, and coordinate regression tests.

### Phase 3 — facets

- Validate configured taxonomy query vars.
- Use Føhn `Facets` on the narrowed main query.
- Verify each facet removes only its own constraint.
- Add position-aware, multi-facet, empty, and selected-state tests.

### Phase 4 — templates and sections

- Add the Twig namespace hook and override precedence.
- Add filter, geocoder, summary, view switch, result, list, map items, map shell, item, popup, empty, and pagination templates.
- Add the four page-local Føhn sections.
- Add escaping and no-JavaScript template tests.

### Phase 5 — `next` browser integration

- Update the demo integration to `@studiometa/ui@next`, `@studiometa/js-toolkit@next`, and `@studiometa/ui-mapbox@next`.
- Add geocoder, clear-position, search-area, map framing, loading, and error wiring through declarative components.
- Add browser tests for Fetch morphing, history, back and forward navigation, selection, and position changes.
- Do not add a locator-specific npm package.

### Phase 6 — operations, documentation, and release

- Verify five-decimal coordinate normalization and static page-cache bypass for every positioned full-page and section request, and caching of unpositioned ones.
- Add the package to root Composer repositories, scripts, CI, release checks, and split workflow.
- Add demo fixtures and smoke assertions.
- Document installation, native field editing, configuration, template overrides, query parameters, cache configuration, and limits.
- Update the release checklist so the package carries the same version and exact inter-package constraint as every other Føhn package.

## 19. Tests

Coverage must include:

- cold and cached discovery of the package model and hooks;
- post type, configurable rewrite, REST, support, and post meta registration;
- coordinate bounds, zero, missing halves, and Mapbox `[lng, lat]` order;
- native meta-box nonce, capability, autosave, revision, sanitization, and invalid-value behavior;
- query parsing limits and every invalid parameter combination;
- `radius` dropped without `near`, and `defaultRadius` applied with `near` alone;
- an unknown term answering `400`, not `404`;
- `400` on both the full page and its section request;
- text search over title, description, and address fields;
- radius inclusion at known distances and exact boundary behavior;
- bounding boxes that do and do not cross the antimeridian, raw longitudes outside `-180..180`, and spans of 360° or more;
- nearest-first ordering with `near`, title ordering without it;
- the empty `post__in` short circuit;
- native pagination on a filtered and positioned archive;
- selected taxonomy combinations;
- position-aware disjunctive facet counts;
- map result limits and refine-search behavior;
- result bounds with and without `near`, and none for `bbox`;
- Twig escaping and valid JSON attributes;
- child-theme, parent-theme, and package template precedence;
- missing token, no matches, no JavaScript, and Mapbox failure fallbacks;
- an unpositioned form submission that carries no empty position argument;
- four-section Fetch responses and history URLs;
- back and forward navigation that updates sections and keeps the map canvas;
- geocoder result submission in the list view and the map view;
- explicit Search this area submission without move-triggered request loops, and no button after programmatic framing;
- list and marker selection after a morph that keeps, adds, and removes stores;
- a real starter or demo smoke assertion for registration and rendering.

Do not duplicate the internal component tests owned by `@studiometa/ui` or `@studiometa/ui-mapbox`. Browser tests cover this package's markup and event integration only.

## 20. Expected files

```text
packages/store-locator/
├── composer.json
├── src/
│   ├── Admin/
│   │   └── StoreMetaBox.php
│   ├── Config/
│   │   ├── StoreLocatorConfig.php
│   │   └── store-locator.config.php
│   ├── Hooks/
│   │   └── StoreLocatorHooks.php
│   ├── Models/
│   │   └── Store.php
│   ├── Search/
│   │   ├── StoreSearch.php
│   │   ├── StoreSearchRequest.php
│   │   ├── StoreSearchResult.php
│   │   └── StoreFacet.php
│   └── ValueObjects/
│       ├── BoundingBox.php
│       └── StoreCoordinates.php
├── templates/
│   ├── Empty.twig
│   ├── Facet.twig
│   ├── Filters.twig
│   ├── Geocoder.twig
│   ├── Map.twig
│   ├── MapItems.twig
│   ├── Pagination.twig
│   ├── Results.twig
│   ├── StoreItem.twig
│   ├── StoreList.twig
│   ├── StorePopup.twig
│   ├── Summary.twig
│   └── ViewSwitch.twig
└── tests/
```

The demo adds its own archive controller, page template, four `templates/sections/store-*.twig` wrappers, configured taxonomies, page-cache query arguments, CSS, fixtures, and browser tests.

## 21. Deferred work

- `DataScope.patch()` adoption after `studiometa/ui#662` ships.
- Meta-backed facets.
- Structured opening hours.
- CSV import.
- An admin coordinate picker or address geocoder.
- Automatic synchronization between address values and facet taxonomies.
- Custom marker images and category-dependent layers.
- Server-side Mapbox geocoding.
- A remote search adapter for large store sets.
- A locator-specific npm package.
- Package-owned full page layouts.
- Page caching of positioned requests.

## 22. Acceptance criteria

The first release is complete when:

- an editor can create and update a store with native WordPress fields;
- the archive renders a no-JavaScript searchable and filterable listing;
- the same URL state with `view=map` renders an interactive clustered map;
- list and map use one normalized request and one search result;
- a geocoder result applies a radius scope and refreshes all sections, in both views;
- Search this area applies a bounding-box scope only after explicit visitor action;
- every facet count reflects text, position, and all other selected facet groups;
- Fetch updates filters, summary, results, and pagination together while keeping a normal history URL;
- a Fetch update or a back and forward navigation never rebuilds the map;
- malformed position and facet input cannot widen a query, and answers `400` for the page and its sections;
- missing JavaScript, token, coordinates, or network access leaves a useful server-rendered experience;
- unpositioned listing and facet requests can be served by the page cache;
- theme template overrides work without copying the package implementation;
- the integration runs on the `next` UI and js-toolkit release line without a locator-specific npm package;
- unit, browser, smoke, lint, static analysis, package split, and release checks pass.
