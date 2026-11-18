# Place locator

`studiometa/foehn-places` adds a place locator: a `place` post type with native fields, an address search in the editor, a spatial search on the archive, and facets. A place is any located record: a store, a dealer, an agency, a partner university.

WordPress is the source of truth. There is no remote search index and no custom REST endpoint. The search narrows the main query of the `place` archive, so native pagination, Timber pagination and [`Facets`](/guide/query-filters) work as on any archive.

## Installation

```bash
composer require studiometa/foehn-places
```

The package requires the exact same version of `studiometa/foehn`. Upgrade the two packages together.

The hooks of the package are opt-in, as every hook class of Føhn. Add `PlacesHooks` to the hooks of the project:

```php
// app/foehn.config.php
use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Places\Hooks\PlacesHooks;

return new FoehnConfig(hooks: [PlacesHooks::class]);
```

Without it, the post type, the taxonomy, the meta keys, the settings page and the JSON-LD node are registered, but the place meta box, the contact keys and the search are not.

Flush the rewrite rules after the installation and after a change of `archiveSlug`: `wp foehn rewrite:flush`.

## Configuration

The package ships a default `PlacesConfig`. Override it in `app/places.config.php`:

```php
use Studiometa\Foehn\Places\Config\PlacesConfig;

return new PlacesConfig(
    archiveSlug: 'stores',
    singular: __('Store', 'theme'),
    plural: __('Stores', 'theme'),
    schemaType: 'Store',
    accessToken: getenv('MAPBOX_ACCESS_TOKEN') ?: null,
    facetTaxonomies: ['place_type', 'place_service'],
);
```

| Option                       | Default             | Meaning                                                            |
| ---------------------------- | ------------------- | ------------------------------------------------------------------ |
| `archiveSlug`                | `place`             | Slug of the archive and of the single places                       |
| `singular`, `plural`         | `Place`, `Places`   | Labels of the post type                                            |
| `typeSingular`, `typePlural` | `Place type`, …     | Labels of the `place_type` taxonomy                                |
| `contactFields`              | `true`              | The phone, email and website fields                                |
| `schemaType`                 | `Place`             | The schema.org type of the JSON-LD node                            |
| `accessToken`, `style`       | `null`              | Mapbox token and style. Win over the settings page when not `null` |
| `geocoderOptions`            | `[]`                | Options of the Mapbox geocoder. A `language` here wins             |
| `facetTaxonomies`            | `['place_type']`    | Taxonomies offered as facets. Each must be attached to `place`     |
| `listPageSize`               | `24`                | Places on a page of the list                                       |
| `mapResultLimit`             | `250`               | The most places a map shows                                        |
| `nearSearch`                 | `true`              | Search around an address (`near`, `radius`)                        |
| `areaSearch`                 | `true`              | Search the visible map area (`bbox`)                               |
| `radiusOptions`              | `[10, 25, 50, 100]` | Radii offered around an address, in kilometres                     |
| `defaultRadius`              | `25`                | Radius of a `near` request with no radius. One of `radiusOptions`  |

A facet taxonomy that does not exist or is not attached to `place` is skipped. With `FoehnConfig::$debug` on, a notice says so.

## The model

`Place` extends `Studiometa\Foehn\Models\Post`. It is not final: a project extends it and maps its subclass to `place`. The project mapping wins, so `Place::query()`, `Timber::get_posts()` and the search give the project class.

```php
#[AsTimberModel('place')]
#[AsPostMeta(key: 'partner_since', type: 'integer', control: 'number', label: 'Partner since')]
final class Place extends \Studiometa\Foehn\Places\Models\Place
{
    public function partnerSince(): ?int
    {
        return $this->meta('partner_since');
    }
}
```

A project field with a `control` is edited in the "Fields" meta box of [`#[AsPostMeta]`](/api/as-post-meta).

| Method                                            | Returns                                                           |
| ------------------------------------------------- | ----------------------------------------------------------------- |
| `coordinates()`                                   | `PlaceCoordinates`, or `null` when missing, incomplete or invalid |
| `isLocatable()`                                   | Whether the place has valid coordinates                           |
| `addressLines()`                                  | Street, postal code and city, country                             |
| `street()`, `postalCode()`, `city()`, `country()` | The address fields, or `null`                                     |
| `phone()`, `phoneUri()`, `email()`, `website()`   | The contact fields, `null` when `contactFields` is off            |
| `types()`                                         | The `place_type` terms                                            |
| `Place::locatable()`                              | Every published place with coordinates                            |

`PlaceCoordinates::toLngLat()` gives the Mapbox order, `[longitude, latitude]`. A place is never placed at `[0, 0]` because of missing values. Zero itself is a valid coordinate.

The registered keys are `latitude`, `longitude`, `street`, `postal_code`, `city` and `country`, and `phone`, `email` and `website` when `contactFields` is on. All are in the REST API. Turning `contactFields` off keeps the stored values.

## Editing

The place meta box has the address search, the location, the address and, when `contactFields` is on, the contact. A save:

- needs the nonce and the right to edit the post, and skips autosaves and revisions;
- keeps a field absent from the request, so a save without the meta box clears nothing;
- saves the coordinates as a pair: a value out of range, or a missing half, keeps the stored coordinates and shows an error in the meta box;
- keeps a numeric zero, and sanitizes text, email, URL and phone values.

The address search is a standalone `MapboxGeocoder` of `@studiometa/ui-mapbox`. A result fills the latitude and the longitude (rounded to six decimal places), the street, the postal code, the city and the country. A field that the result does not give keeps its value. Every field stays editable, and nothing is saved before the post is. The geocoder uses the content language of a multilingual site, else the admin language.

The script is prebuilt in `packages/places/dist/`, from `packages/places/assets/`, and printed in the meta box only. CI fails when the committed build is out of date. Rebuild it with `npm run -w @studiometa/foehn-places-admin build`.

## Settings

**Places → Place locator settings** holds the Mapbox access token and the map style. The token is public: it is printed in the page markup. Restrict it to the URLs of the site in the Mapbox account.

A non-null `accessToken` or `style` in `PlacesConfig` wins over the option. The settings page then shows the value read-only. Without a token, the address search is not rendered, and a notice links to the settings page.

## Search

The archive reads one GET contract:

| Parameter        | Values                       | Meaning                                             |
| ---------------- | ---------------------------- | --------------------------------------------------- |
| `q`              | text, at most 100 characters | Title, description and address, without case        |
| `view`           | `list`, `map`                | Result projection. Default `list`                   |
| `near`           | `latitude,longitude`         | Origin of a radius search, rounded to five decimals |
| `radius`         | one of `radiusOptions`       | Kilometres around `near`. Dropped without `near`    |
| `bbox`           | `west,south,east,north`      | A map area                                          |
| a facet taxonomy | `name[]=slug` or `name=a,b`  | Selected terms                                      |

An empty value is absent. `near` and `bbox` together, a repeated parameter, an unknown `view`, a radius outside the options, a coordinate out of range, a malformed value, an unknown term, and a `near`, `radius` or `bbox` whose feature is off answer `400`, for the page and for its sections. An unknown term never turns the archive into a `404`.

`near` and `bbox` can come from raw map values: a number keeps the full precision JavaScript prints (`0.30000000000000004`, `1e-7`), and the server rounds it to five decimals. `bbox` longitudes can come from raw map bounds: a span of 360° or more removes the longitude constraint, and an area across the antimeridian works.

`PlaceSearchRequest::toQueryArgs()` gives the request as query arguments, for a link that keeps it (pagination, the view switch). Each taxonomy is one comma-separated value (`place_type=a,b`), so `add_query_arg()` and `http_build_query()` write the form that the parser and the page cache read.

With `near`, places within the radius come nearest first (Haversine distance). Without it, places come by title. On a multilingual site, a place of another language is never in the result or the facet counts.

```php
use Studiometa\Foehn\Places\Search\PlaceSearch;

$result = PlaceSearch::current();

$result->invalid;      // a 400 request
$result->total;        // matching places
$result->list();       // the places of the current page, in the list view
$result->map();        // the map places, empty above mapResultLimit
$result->mapLimitExceeded;
$result->markers();    // id, [lng, lat], title and link of each map place
$result->distance($place); // metres from near, or null
$result->facets;       // FacetOption lists, by taxonomy
$result->bounds;       // [[west, south], [east, north]] to frame, or null
```

Each facet is counted with the text and position scope and the other facet groups, and without its own group.

## Archive page

The package renders the fragments of the archive. The theme owns the page: a template controller for `archive-place`, a page template, and four section wrappers.

```php
use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Contracts\TemplateControllerInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Studiometa\Foehn\Places\Search\PlaceSearch;
use Studiometa\Foehn\Places\View\PlacesView;
use Studiometa\Foehn\Views\TemplateContext;

#[AsTemplateController('archive-place', priority: 5)]
final readonly class PlaceArchiveController implements TemplateControllerInterface
{
    public function __construct(
        private ViewEngineInterface $view,
        private PlacesView $places,
    ) {}

    public function handle(TemplateContext $context): string
    {
        return $this->view->render(
            'pages/archive-place',
            $context->with('result', PlaceSearch::current())->with('places', $this->places),
        );
    }
}
```

`PlaceSearch::current()` runs once. Every template receives the same `result`, and `places` (`PlacesView`): the configuration, the resolved Mapbox token and style, the section names, and the URLs that keep the request.

```twig
{# templates/pages/archive-place.twig #}
{% extends 'layouts/base.twig' %}

{% block content %}
  {{ foehn_section('place-summary', { result, places }) }}

  {% include '@places/Geocoder.twig' with { result, places } only %}
  {{ foehn_section('place-filters', { result, places }) }}

  {% if result.request.view == 'map' and not result.invalid %}
    {% embed '@places/Map.twig' with { result, places } only %}
      {% block items %}
        {{ foehn_section('place-results', { result, places }) }}
      {% endblock %}
    {% endembed %}
  {% else %}
    {{ foehn_section('place-results', { result, places }) }}
  {% endif %}

  {{ foehn_section('place-pagination', { result, places }) }}
{% endblock %}
```

Each section wrapper includes its package template:

```twig
{# templates/sections/place-filters.twig #}
{% include '@places/Filters.twig' with { result, places } only %}
```

| Section            | Package template  |
| ------------------ | ----------------- |
| `place-filters`    | `Filters.twig`    |
| `place-summary`    | `Summary.twig`    |
| `place-results`    | `Results.twig`    |
| `place-pagination` | `Pagination.twig` |

The geocoder and the map shell are never inside a section: a Fetch update morphs the four sections, never the Mapbox input or the map canvas.

## Templates

| Template          | Renders                                                                                       |
| ----------------- | --------------------------------------------------------------------------------------------- |
| `Filters.twig`    | The GET form: text, facets, the position controls of the features that are on, Clear position |
| `Facet.twig`      | One facet group: a checkbox per term, with its count                                          |
| `Geocoder.twig`   | The address search. Nothing when `nearSearch` is off or there is no token                     |
| `Summary.twig`    | The total, the position scope, the view switch and the status region                          |
| `ViewSwitch.twig` | The list and map links, with the current request                                              |
| `Results.twig`    | `PlaceList.twig` or `MapItems.twig`, the refine-search state, and `data-bounds`               |
| `PlaceList.twig`  | The places of the current page                                                                |
| `MapItems.twig`   | The map places, each a `MapboxClusterItem` with `id="place-{ID}"`                             |
| `PlaceItem.twig`  | One place: title, distance, types, address, contact                                           |
| `PlacePopup.twig` | The popup of a selected marker                                                                |
| `Map.twig`        | `StoreLocator`, `MapboxMap`, `MapboxCluster`, the navigation control, Search this area        |
| `Pagination.twig` | The pages of the list. Nothing in the map view                                                |
| `Empty.twig`      | No match, or an invalid request, with a link to the unfiltered archive                        |

The templates escape every value. The theme owns the layout, the map height and the CSS.

### Overrides

`PlacesHooks` registers two Twig namespaces on `timber/locations`:

| Namespace     | Looks in                                                                           |
| ------------- | ---------------------------------------------------------------------------------- |
| `@places`     | `templates/places/` of the child theme, then of the parent theme, then the package |
| `@places-pkg` | The package only                                                                   |

A file in `templates/places/` replaces the package template of the same name. To add to a template without copying it, include the package one through `@places-pkg`. Every package template reaches the others through `@places`, so an override applies everywhere:

```twig
{# templates/places/PlaceItem.twig: the package item, then a project field #}
{% include '@places-pkg/PlaceItem.twig' %}
{% if place.openingHours() %}
  <p class="places-item__hours">{{ place.openingHours()|e }}</p>
{% endif %}
```

## Browser integration

The package ships no front-end script. The templates emit declarative markup for `@studiometa/ui` and `@studiometa/ui-mapbox`, on the `next` release line:

```bash
npm install @studiometa/js-toolkit@next @studiometa/ui@next @studiometa/ui-mapbox@next mapbox-gl @mapbox/mapbox-gl-geocoder
```

```js
import "@studiometa/ui/autoload";
import "@studiometa/ui-mapbox/autoload";
import "mapbox-gl/dist/mapbox-gl.css";
import "@mapbox/mapbox-gl-geocoder/dist/mapbox-gl-geocoder.css";
```

- The filter form is a GET form with `Action Fetch`. A change submits it after 300 ms. `Fetch` requests the four sections in one request, morphs them (`mode="morph"`, `selector="[data-foehn-section]"`), and writes the archive URL with the form fields to the history, never `?foehn_sections=`. Back and forward update the sections only.
- An empty position control is disabled, so the form never submits `near=` or `bbox=`.
- A geocoder result writes `near` into the form, turns `bbox` off and submits. The radius control appears with `near`; without one, the server applies `defaultRadius`.
- The map frames `result.bounds` on the first load (`bounds` and `fitBoundsOptions` of the map options) and after each update (the `data-bounds` of the results). A `bbox` request has no bounds, so the map keeps the area the visitor chose.
- Search this area shows only after a move made by the visitor (`map-moveend` with an `originalEvent`), never after a `fitBounds()`. It submits the raw map bounds; the server normalizes them.
- The form sets `aria-busy` and writes a loading text, then an error text, into the `place-status` region of the summary. A failed request (a `400` included) keeps the last sections.
- Each map item is the sidebar row and the map feature: `StoreLocator` selects it from a click in the list or on a marker. `id="place-{ID}"` keeps the node of a place that stays after a morph.

In an `Action` effect with a target, such as `MapboxMap(#place-map) -> …`, `$el` is the element of the target. The element of the action is `action.$el`.

### Without JavaScript, token or network

- Without JavaScript, the form reloads the archive with the search: text, facets, pagination and the view switch work. The geocoder and Search this area need JavaScript.
- Without a token, the geocoder is not rendered and the map view says the map is not available, then lists the places.
- Without coordinates, a place is in no result.
- Without Mapbox network access, the server-rendered list and links stay.

## Page cache

Position requests are not page cached: `near`, `radius` and `bbox` are not cache query arguments, so every positioned page and section request is a bypass. Key the other arguments, so an unpositioned search is a cached page and a cached section response:

```php
// app/page-cache.config.php
return new PageCacheConfig(
    // …
    cacheQueryArgs: [
        'q' => '^[A-Za-z0-9-]{1,100}$',
        'view' => ['list', 'map'],
        'place_type' => '^[a-z0-9-]+(?:,[a-z0-9-]+)*$',
    ],
);
```

Add one entry per facet taxonomy. Pagination is a path and needs no entry. An empty `q`, as the form submits it, is absent from the key. A text with a space or an accent is outside the characters the cache writes to a file name, so it is a bypass.

## Limits

- The search loads the places of the current language in PHP. It targets about 250 places. Above that, or for typo-tolerant search, move the search to `studiometa/foehn-search`.
- The map shows at most `mapResultLimit` places, one DOM node each. Above it, the map asks to refine the search.
- The list is paginated with `listPageSize`, without upper limit.
- Facets are taxonomies. An address field is not a facet: a project that filters by country uses a taxonomy.

## JSON-LD

Each single place page gets a node of the type `schemaType`, with `name`, `url`, `description`, `image`, a `PostalAddress`, `GeoCoordinates` when the place has coordinates, and `telephone`, `email` and `sameAs` when contact fields are on and filled. See [JSON-LD](/guide/json-ld).
