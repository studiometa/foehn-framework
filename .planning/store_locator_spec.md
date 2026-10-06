# Føhn store locator package

Status: proposed, design approved for planning. Not implemented.

A reusable `studiometa/foehn-store-locator` package that owns the WordPress store model, native editing fields, spatial search, facets, and server-rendered templates for a `/store/` listing and map. The browser uses `@studiometa/ui@next`, `@studiometa/js-toolkit@next`, and `@studiometa/ui-mapbox@next` directly. There is no locator-specific npm package.

The reference implementation is the Shopify Search app store locator in `packages/shopify/apps/packages/search`. Its useful seam is the same rendered store item acting as both a list row and a `MapboxClusterItem`. Its Meilisearch and Shopify metaobject implementation are not carried into WordPress.

## 1. Decisions

- The feature lives in a separate Composer package, `studiometa/foehn-store-locator`, not in Føhn core.
- WordPress is the source of truth. There is no remote search index, custom REST endpoint, or duplicated store database in the first version.
- The package uses native registered post meta and a dedicated WordPress meta box. It does not depend on ACF.
- The store archive is available at `/store/`. A theme owns the full page layout and template controller; the package owns the body fragments and search module.
- The list and map are two projections of one `StoreSearchResult`. They use the same query parameters, selected filters, position scope, total, and facet counts.
- Position is a spatial constraint, not a facet group. Every facet count keeps the current spatial constraint.
- Taxonomies are the first supported facet source. Free-form post meta is not a facet source until a concrete normalization requirement exists.
- The package renders Føhn sections. `Fetch` replaces filters, summary, results, and pagination in one response.
- The package emits declarative `Action`, `Fetch`, `Data*`, and Mapbox markup. It ships no browser implementation of those behaviors.
- The current integration writes geocoder and map values to native form controls from `Action` effects. A future migration can use `DataScope.patch()` after [studiometa/ui#662](https://github.com/studiometa/ui/issues/662) is released.
- The first version targets store sets of approximately 250 records. A larger set is a reason to design a remote search adapter, not to add one speculatively.

## 2. Package boundary

The package is one deep module with this public interface:

```php
Store::query();
Store::locatable();
StoreSearch::search(StoreSearchRequest $request): StoreSearchResult;
new StoreLocatorConfig(...);
StoreLocatorHooks::class;
```

Twig callers use namespaced templates:

```twig
{% include '@store-locator/Locator.twig' with { result } only %}
```

The package hides:

- custom post type and post meta registration;
- native field rendering and saving;
- coordinate validation and Mapbox coordinate ordering;
- text and spatial filtering;
- distance ordering;
- taxonomy query construction;
- position-aware facet counts;
- list and map result projections;
- the required `@studiometa/ui-mapbox` DOM contract;
- template lookup and override precedence.

The package does not own:

- the theme layout;
- project CSS;
- project-specific facet taxonomies;
- a Mapbox access token management screen;
- Mapbox, Fetch, Action, or Data browser behavior;
- geocoding or distance calculations on a remote service;
- CSV import.

## 3. Dependencies

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

The lockfile pins the installed prerelease versions. `@studiometa/ui-mapbox@next` requires `@studiometa/js-toolkit ^4.0.0-alpha.1` and exports `./autoload`.

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
| Archive        | yes, `/store/`                                     |
| Single rewrite | `/store/{slug}/`                                   |
| Supports       | title, editor, thumbnail, revisions, custom-fields |
| Title          | store name                                         |
| Editor         | optional description                               |
| Featured image | optional store photo                               |

The package does not render a full single-store page. A theme can use the normal `single-store` hierarchy and the package item/detail fragments.

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

The model provides typed accessors and derived values:

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

The package ships a default `StoreLocatorConfig` through `store-locator.config.php`. An application overrides it with `app/store-locator.config.php`.

The initial interface is equivalent to:

```php
final readonly class StoreLocatorConfig
{
    public function __construct(
        public string $accessToken = '',
        public string $style = 'mapbox://styles/mapbox/light-v11',
        public array $center = [2.5, 47.0],
        public float $zoom = 5,
        public array $geocoderOptions = [],
        public array $facetTaxonomies = [],
        public int $listPageSize = 24,
        public int $mapResultLimit = 250,
        public float $defaultRadius = 25,
        public float $maximumRadius = 200,
    ) {}
}
```

The access token is a public, origin-restricted Mapbox token. The package does not store it in WordPress options.

Facet taxonomy names are an allowlist. A name that is absent or not attached to `store` is ignored in production and reported when Føhn debug mode is enabled.

## 7. `/store/` request contract

The archive uses one GET contract for full pages and section requests.

| Parameter                 | Values                      | Default            | Meaning                                            |
| ------------------------- | --------------------------- | ------------------ | -------------------------------------------------- |
| `q`                       | text, bounded length        | empty              | Search store name, description, and address fields |
| `view`                    | `list`, `map`               | `list`             | Result projection                                  |
| `near`                    | `latitude,longitude`        | absent             | Geocoder-selected origin                           |
| `radius`                  | positive kilometres         | configured default | Radius around `near`                               |
| `bbox`                    | `west,south,east,north`     | absent             | Explicit map viewport scope                        |
| `sort`                    | `title`, `distance`         | `title`            | Result order; distance requires `near`             |
| `page`                    | positive integer            | `1`                | Listing page                                       |
| configured taxonomy names | term slug or repeated slugs | absent             | Facet selections                                   |

Request invariants:

- `near` requires a valid `radius`;
- `sort=distance` requires `near`;
- `near/radius` and `bbox` cannot be active together in the normalized request;
- latitude, longitude, bounds, radius, text length, term count, and page have explicit limits;
- changing text, position, view, or a facet resets listing pagination;
- an empty optional control is normalized as absent before dependency checks;
- `near` and each `bbox` coordinate are rounded to five decimal places, and `radius` to one decimal place, before they enter the normalized request or URL;
- a bounding box whose east is below its west crosses the antimeridian and matches longitudes on either side of `180°`;
- unsafe, repeated scalar, or malformed non-empty values become a `400`, not a guessed query.

`StoreSearchRequest::fromQuery()` owns parsing and normalization. Templates and search code receive only the normalized object.

Example URLs:

```text
/store/?q=repair&store_service=delivery&view=list
/store/?near=45.764,4.8357&radius=25&sort=distance&view=list
/store/?bbox=4.70,45.60,5.00,45.90&view=map
```

## 8. Search pipeline

`StoreSearch` is the interface for both the full page and every section request:

```php
StoreSearch::search(StoreSearchRequest $request): StoreSearchResult;
```

The first implementation is in-process because the supported data set is small and WordPress is the only data source.

### Base eligible IDs

The search first loads published, locatable stores and computes a base list of eligible post IDs from constraints that WordPress taxonomy queries do not own:

1. `q` is matched case-insensitively against title, description, street, postal code, city, and country.
2. `near/radius` uses the Haversine formula and records distance in metres.
3. `bbox` keeps coordinates inside west, south, east, and north. When east is below west, the box crosses the antimeridian and longitude matches `>= west OR <= east`.
4. Distance sorting orders the eligible IDs nearest first.

A later optimization can reduce the candidate set with numeric meta ranges before Haversine calculation. The observable interface does not change.

### WordPress query

The package then builds a `WP_Query` with:

- `post_type=store`;
- published status;
- `post__in` set to the base eligible IDs;
- configured taxonomy clauses;
- listing pagination or the map limit;
- `orderby=post__in` when distance order is active.

An empty base ID set must short-circuit to an empty result. WordPress can interpret an empty `post__in` as no restriction, which would incorrectly return every store.

### Result

`StoreSearchResult` contains:

- normalized request;
- total matching stores;
- current list page;
- total list pages;
- list stores;
- map stores;
- whether the map limit was exceeded;
- distance by store ID when applicable;
- facet groups and counts;
- selected filters;
- the bounds of the returned map stores when available.

## 9. Position-aware facets

Position is a base scope, not a facet group.

For the final result, all selected taxonomy facets are applied to the base IDs produced by text and position filtering.

For each facet group, counts are computed with:

1. text search retained;
2. position scope retained;
3. all other selected facet groups retained;
4. this facet group's own constraint removed.

Example: 18 stores are within 25 km, 7 offer repairs, and 4 offer delivery. The service facet reports `Repairs (7)` and `Delivery (4)` within that 25 km scope, not across the full database.

The existing Føhn `Facets` implementation can provide these counts when it receives the constructed query. Its cloned query retains the spatial `post__in` base while `Facets::varsWithout()` removes only the taxonomy being counted.

The first version supports taxonomy facets only. Projects define taxonomies such as `store_service` or `store_type` and list them in `StoreLocatorConfig::facetTaxonomies`. Address fields remain display and text-search data. They are not converted into facet vocabularies automatically.

## 10. List and map views

The two views consume the same `StoreSearchResult`.

### Listing view

- Uses `listPageSize`, initially 24.
- Renders linked, semantic store cards.
- Shows distance when `near` is active.
- Renders pagination with the complete normal query state.
- Works without JavaScript.

### Map view

- Renders all matching stores up to `mapResultLimit`, initially 250.
- Uses one `MapboxClusterItem` element as both the sidebar item and map feature.
- Uses `StoreLocator`, `MapboxMap`, `MapboxGeocoder`, `MapboxNavigationControl`, `MapboxCluster`, and `MapboxClusterItem` from `@studiometa/ui-mapbox@next`.
- Uses popup content from the item template.
- Does not paginate inside the map.
- Does not silently truncate. When the total exceeds the map limit, it asks the visitor to refine the search instead of presenting an incomplete map as complete.

Changing `view` changes the projection only. It preserves text, position, and facet state.

## 11. Føhn sections and Fetch

The theme page declares these top-level sections:

```text
store-filters
store-summary
store-results
store-pagination
```

The filter form is a normal GET form whose action is `/store/`. It requests all four sections through `data-option-src` and enables normal-history updates:

```twig
<form
  id="store-search"
  action="/store/"
  method="get"
  data-component="Action Fetch"
  data-option-history
  data-option-src="{{ foehn_section_url('store-filters,store-summary,store-results,store-pagination', '/store/') }}"
  data-on:change.debounce300="this.elements.page.value = '1'; this.requestSubmit()">
  <input type="hidden" name="page" value="{{ result.request.page }}">
```

`Fetch` from `@studiometa/ui@next` keeps two URLs:

- the section URL is fetched;
- the normal form URL is written to browser history.

The sections update atomically after the normal archive controller rebuilds one `StoreSearchResult`. The new filters carry updated counts and selected states. The summary, result view, and pagination cannot disagree with them.

A no-JavaScript submission renders the same state as a full page.

## 12. Browser position interactions

The current design uses native form controls as request state. `Action` writes the controls, and `Fetch` serializes them. `DataModel` and `DataBind` remain available for visible state that must be mirrored, but they are not used to replace a native form model with another state system.

### Address selection

`MapboxGeocoder` emits `map-result`. The action:

1. reads the result center;
2. writes `near` in `latitude,longitude` order;
3. clears `bbox`;
4. lets the configured or selected radius remain active;
5. submits `Fetch`.

The action targets the form's `Fetch` instance, so it does not use a global selector:

```twig
<div
  data-component="Action MapboxGeocoder"
  data-on:map-result="Fetch(#store-search)->{
    const [lng, lat] = event.detail.result.center;
    target.$el.elements.near.value = `${lat},${lng}`;
    target.$el.elements.bbox.value = '';
    target.$el.elements.page.value = '1';
    target.fetch();
  }">
```

### Search this area

Moving the map does not fetch automatically. Automatic `moveend` requests create request storms, make panning unstable, and can loop when new results refit the map.

A map movement exposes a `Search this area` button. Clicking it:

1. reads `MapboxMap.map.getBounds()`;
2. writes `bbox` as west, south, east, north;
3. clears `near` and `radius`;
4. submits the associated form.

```twig
<button
  type="button"
  form="store-search"
  data-component="Action"
  data-option-target="MapboxMap(#store-map)"
  data-on:click="{
    const bounds = target.map.getBounds();
    const form = action.$el.form;
    form.elements.bbox.value = [
      bounds.getWest(),
      bounds.getSouth(),
      bounds.getEast(),
      bounds.getNorth()
    ].join(',');
    form.elements.near.value = '';
    form.elements.radius.value = '';
    form.elements.page.value = '1';
    form.requestSubmit();
  }">
  Search this area
</button>
```

### Clear position

A clear action empties `near`, `radius`, and `bbox`, resets `page` to `1`, then submits the form. The full unpositioned result and its facet counts return.

### List and map switch

The form carries `view` as a hidden control. Each switch action writes the value through the targeted `Fetch` form and fetches the new sections. This does not depend on the unresolved native submitter behavior in `studiometa/ui#650`.

```twig
<input type="hidden" name="view" value="{{ result.request.view }}">

<button
  type="button"
  data-component="Action"
  data-on:click="Fetch(#store-search)->{
    target.$el.elements.view.value = 'list';
    target.$el.elements.page.value = '1';
    target.fetch();
  }">
  List
</button>

<button
  type="button"
  data-component="Action"
  data-on:click="Fetch(#store-search)->{
    target.$el.elements.view.value = 'map';
    target.$el.elements.page.value = '1';
    target.fetch();
  }">
  Map
</button>
```

## 13. Future `DataScope.patch()` migration

The current native-form implementation remains the approved first implementation.

A proposed `DataScope.patch()` method is tracked in [studiometa/ui#662](https://github.com/studiometa/ui/issues/662). Its intended interface applies several keyed values atomically, updates mounted `DataModel` sources, and notifies subscribers once after the complete patch.

After it ships, the geocoder action could become equivalent to:

```twig
<form
  id="store-search"
  data-component="DataScope Fetch"
  data-option-group="search">
  <input type="hidden" name="near" data-component="DataModel" data-option-immediate>
  <input type="hidden" name="bbox" data-component="DataModel" data-option-immediate>
  <input type="hidden" name="page" value="1" data-component="DataModel" data-option-immediate>
</form>
```

```twig
<div
  data-component="Action MapboxGeocoder"
  data-on:map-result="DataScope(#store-search)->{
    const [lng, lat] = event.detail.result.center;
    target.patch({ near: `${lat},${lng}`, bbox: '', page: 1 });
    target.$el.requestSubmit();
  }">
```

Migration is optional. It must reduce markup and preserve native form submission, Fetch history, no-JavaScript behavior, and request normalization before the package adopts it.

## 14. Templates and override seam

`StoreLocatorHooks` registers two Twig namespaces on a `FilesystemLoader`:

| Namespace            | Lookup order                                                  |
| -------------------- | ------------------------------------------------------------- |
| `@store-locator`     | child theme override, parent theme override, package template |
| `@store-locator-pkg` | package template only                                         |

The initial templates are:

```text
templates/
├── Locator.twig
├── Filters.twig
├── Facet.twig
├── Summary.twig
├── Results.twig
├── StoreList.twig
├── StoreItem.twig
├── StorePopup.twig
├── Pagination.twig
└── Empty.twig
```

Templates accept explicit values instead of running queries. `StoreSearch` runs once in the controller, and every section receives the same result.

The package templates own semantic structure, required data attributes, refs, escaping, and accessible fallback text. The theme owns layout, map height, typography, colors, and responsive CSS.

The package does not own a full archive template because it cannot know the theme's base layout. A theme archive controller renders its own `pages/store` template, which includes the package fragments.

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

- Without JavaScript, the GET form reloads `/store/` with correct filters and results.
- Without a Mapbox token, the list remains available and the map renders a configurable unavailable state.
- Without Mapbox network access, server-rendered list links and details remain available.
- Without coordinates, a store is not included in locatable results and is never placed at `[0, 0]`.
- With no matches, all sections render a consistent empty state and the map does not call `fitBounds()` with an empty set.
- An invalid request returns `400` with a theme-safe HTML response. It does not silently widen the result.
- A map result count above the configured limit renders a refine-search state. It does not silently omit matching stores.
- Fetch error and loading states use its public lifecycle events and remain inside the page's accessible status region.
- Before Search this area is submitted, `StoreLocator` may hide and reorder sidebar items for the current client viewport. This is presentation state only: the server summary and facets continue to describe the last submitted spatial scope. The interface labels these counts separately and exposes the pending Search this area action.

## 16. Security and output rules

- Every meta-box mutation requires capability, nonce, and valid request context checks.
- Every request parameter has a type, length, count, and range limit.
- Facet taxonomy names come only from configuration, not directly from the request.
- Term values are sanitized and validated against their configured taxonomy.
- Mapbox options and coordinate arrays are JSON encoded and HTML-attribute escaped.
- Store titles, addresses, contacts, URLs, popup content, and facet labels are escaped for their output context.
- The public Mapbox token must be origin restricted. It is not treated as a server secret.
- The search result does not expose private or draft stores.

## 17. Performance characteristics

The first implementation can load and evaluate all published locatable stores in process because the target data set is approximately 250 stores.

The cost is:

- one store candidate read;
- in-process text and spatial filtering;
- one paginated or map result query over eligible IDs;
- one ID query and grouped count per taxonomy facet through Føhn `Facets`.

Position requests are not stored by Føhn's static page cache in the first version. `near`, `radius`, and `bbox` are deliberately absent from `PageCacheConfig::cacheQueryArgs`, so the existing unknown-query rule bypasses both full-page and section caching. Normalization still rounds coordinates to five decimal places and radius to one decimal place so copied URLs are stable. Unpositioned listing and facet URLs can use the existing cache policy when their query arguments are explicitly configured.

A store set that cannot be loaded and filtered within the response budget is the trigger for a separate remote search design. No repository port is added before that second implementation exists.

## 18. Delivery plan

### Phase 1 — package and model

- Add `packages/store-locator` and Composer discovery metadata.
- Add `Store`, `StoreCoordinates`, post type registration, and post meta schemas.
- Add the native meta box and save rules.
- Add model and editor unit tests.

### Phase 2 — request and search

- Add `StoreSearchRequest`, strict query parsing, and error handling.
- Add text matching, bounding-box filtering, Haversine radius filtering, and distance ordering.
- Add `StoreSearchResult` with list and map projections.
- Add empty-ID and coordinate regression tests.

### Phase 3 — facets

- Add configured taxonomy parsing and query clauses.
- Reuse Føhn `Facets` with the spatial eligible-ID scope.
- Verify each facet removes only its own constraint.
- Add position-aware, multi-facet, empty, and selected-state tests.

### Phase 4 — templates and sections

- Add the Twig namespace hook and override precedence.
- Add filter, summary, result, list, map, item, popup, empty, and pagination templates.
- Add the four page-local Føhn sections.
- Add escaping and no-JavaScript template tests.

### Phase 5 — `next` browser integration

- Update the demo integration to `@studiometa/ui@next`, `@studiometa/js-toolkit@next`, and `@studiometa/ui-mapbox@next`.
- Add geocoder, clear-position, search-area, list/map switch, loading, and error wiring through declarative components.
- Add browser tests for Fetch replacement, history, selection, map/list switching, and position changes.
- Do not add a locator-specific npm package.

### Phase 6 — operations, documentation, and release

- Verify five-decimal coordinate normalization, one-decimal radius normalization, and static page-cache bypass for every positioned full-page and section request.
- Add the package to root Composer repositories, scripts, CI, release checks, and split workflow.
- Add demo fixtures and smoke assertions.
- Document installation, native field editing, configuration, template overrides, query parameters, and limits.
- Update the release checklist so the package carries the same version and exact inter-package constraint as every other Føhn package.

## 19. Tests

Coverage must include:

- cold and cached discovery of the package model and hooks;
- post type, rewrite, REST, support, and post meta registration;
- coordinate bounds, zero, missing halves, and Mapbox `[lng, lat]` order;
- native meta-box nonce, capability, autosave, revision, sanitization, and invalid-value behavior;
- query parsing limits and every invalid parameter combination;
- text search over title, description, and address fields;
- radius inclusion at known distances and exact boundary behavior;
- bounding boxes that do and do not cross the antimeridian;
- nearest-first ordering;
- the empty `post__in` short circuit;
- selected taxonomy combinations;
- position-aware disjunctive facet counts;
- list pagination and complete normal URLs;
- map result limits and refine-search behavior;
- Twig escaping and valid JSON attributes;
- child-theme, parent-theme, and package template precedence;
- missing token, no matches, no JavaScript, and Mapbox failure fallbacks;
- four-section Fetch responses and history URLs;
- geocoder result submission;
- explicit Search this area submission without move-triggered request loops;
- list and marker selection after a result replacement;
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
│       └── StoreCoordinates.php
├── templates/
│   ├── Empty.twig
│   ├── Facet.twig
│   ├── Filters.twig
│   ├── Locator.twig
│   ├── Pagination.twig
│   ├── Results.twig
│   ├── StoreItem.twig
│   ├── StoreList.twig
│   ├── StorePopup.twig
│   └── Summary.twig
└── tests/
```

The demo adds its own archive controller, page shell, four `templates/sections/store-*.twig` wrappers, configured taxonomies, CSS, fixtures, and browser tests.

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

## 22. Acceptance criteria

The first release is complete when:

- an editor can create and update a store with native WordPress fields;
- `/store/` renders a no-JavaScript searchable and filterable listing;
- the same URL can render an interactive clustered map;
- list and map use one normalized request and one search result;
- a geocoder result applies a radius scope and refreshes all sections;
- Search this area applies a bounding-box scope only after explicit visitor action;
- every facet count reflects text, position, and all other selected facet groups;
- Fetch updates filters, summary, results, and pagination together while keeping a normal history URL;
- malformed position and facet input cannot widen a query;
- missing JavaScript, token, coordinates, or network access leaves a useful server-rendered experience;
- theme template overrides work without copying the package implementation;
- the integration runs on the `next` UI and js-toolkit release line without a locator-specific npm package;
- unit, browser, smoke, lint, static analysis, package split, and release checks pass.
