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

Position requests are not page cached: `near`, `radius` and `bbox` are not cache query arguments.

## JSON-LD

Each single place page gets a node of the type `schemaType`, with `name`, `url`, `description`, `image`, a `PostalAddress`, `GeoCoordinates` when the place has coordinates, and `telephone`, `email` and `sameAs` when contact fields are on and filled. See [JSON-LD](/guide/json-ld).
