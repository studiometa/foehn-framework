### Added

- The new `studiometa/foehn-places` package adds a place locator: a `place` post type and a hierarchical `place_type` taxonomy, with labels and archive slug from `PlacesConfig` (override it in `app/places.config.php`), the `latitude`, `longitude`, `street`, `postal_code`, `city` and `country` meta keys, and `phone`, `email` and `website` when `contactFields` is on. A project extends `Studiometa\Foehn\Places\Models\Place` and maps its subclass with `#[AsTimberModel('place')]`. Add `PlacesHooks::class` to `FoehnConfig::$hooks` to turn on the place meta box and the search, then flush the rewrite rules
- The place meta box fills the coordinates and the address from a Mapbox address search, in the content language. The Mapbox token and style are set on **Places → Place locator settings**, or in `PlacesConfig`, which wins and shows the field read-only
- `PlaceSearch::current()` gives the search of the place archive: text, radius around `near` with distance ordering, map area (`bbox`) and taxonomy facets counted within the text and position scope. A request that breaks the contract answers `400`. Each single place page gets a JSON-LD node of the type `PlacesConfig::$schemaType`
- A setting whose option a `pre_option_{$option}` filter sets shows the value read-only on its settings page

### Fixed

- A section request answers the error status that its template controller, or a `template_redirect` hook, set, as the full page does. It answered `200` before
- A `#[AsTimberModel]` class wins over the class of an `#[AsPostType]` with the same name, whatever the order of discovery. Before, the post type class always won, because post types apply after Timber models
