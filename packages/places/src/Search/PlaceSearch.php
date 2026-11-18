<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Places\Search;

use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Helpers\WP;
use Studiometa\Foehn\Places\Config\PlacesConfig;
use Studiometa\Foehn\Places\Models\Place;
use Studiometa\Foehn\Places\ValueObjects\PlaceCoordinates;
use Studiometa\Foehn\Query\Facets;
use Tempest\Container\Singleton;
use Timber\Timber;
use WP_Post;
use WP_Query;
use WP_Term;

use function Studiometa\Foehn\multilingual;
use function Tempest\Container\get;

/**
 * The place search: the request of this page, the eligible places, and the result.
 *
 * WordPress stays the source of truth. The search reads the published places of the
 * current language once, filters them in PHP by text and position, and hands the
 * eligible ids to the main query as `post__in`. WordPress applies the facet taxonomy
 * query vars itself, so native pagination and Føhn Facets work on the narrowed query.
 */
#[Singleton]
final class PlaceSearch
{
    private ?PlaceSearchRequest $request = null;

    private bool $invalid = false;

    /** @var array<int, float> */
    private array $distances = [];

    private ?PlaceSearchResult $result = null;

    public function __construct(
        private readonly PlacesConfig $config,
        private readonly FoehnConfig $foehn,
        private readonly Facets $facets,
    ) {}

    /**
     * The result of the search of this request, built once from the main query.
     */
    public static function current(): PlaceSearchResult
    {
        return get(self::class)->result();
    }

    /**
     * Parse the request of the archive. An invalid request is recorded, not thrown.
     */
    public function parse(string $queryString): ?PlaceSearchRequest
    {
        $this->result = null;
        $this->distances = [];

        try {
            $this->request = PlaceSearchRequest::fromQueryString(
                $queryString,
                $this->config,
                $this->facetTaxonomies(),
                $this->termsExist(...),
            );
            $this->invalid = false;
        } catch (InvalidPlaceSearch) {
            $this->request = null;
            $this->invalid = true;
        }

        return $this->request;
    }

    public function isInvalid(): bool
    {
        return $this->invalid;
    }

    public function request(): ?PlaceSearchRequest
    {
        return $this->request;
    }

    /**
     * The configured facet taxonomies that exist and are attached to `place`.
     *
     * Any other name is skipped, with a notice in debug mode.
     *
     * @return list<string>
     */
    public function facetTaxonomies(): array
    {
        $taxonomies = [];

        foreach ($this->config->facetTaxonomies as $taxonomy) {
            if (taxonomy_exists($taxonomy) && is_object_in_taxonomy(Place::POST_TYPE, $taxonomy)) {
                $taxonomies[] = $taxonomy;

                continue;
            }

            if ($this->foehn->isDebug()) {
                trigger_error(
                    sprintf(
                        '[foehn-places] The facet taxonomy "%s" is not a taxonomy of "place": it is skipped.',
                        $taxonomy,
                    ),
                    E_USER_NOTICE,
                );
            }
        }

        return $taxonomies;
    }

    /**
     * The eligible place ids, nearest first with `near`, and their distances.
     *
     * An empty list means no place: the caller must turn it into `post__in = [0]`,
     * because WordPress reads an empty `post__in` as no restriction.
     *
     * @return list<int>
     */
    public function eligibleIds(PlaceSearchRequest $request): array
    {
        $ids = [];
        $this->distances = [];

        foreach ($this->candidates() as $candidate) {
            if ($request->q !== null && !str_contains($candidate['text'], mb_strtolower($request->q))) {
                continue;
            }

            $coordinates = $candidate['coordinates'];

            if ($request->bbox !== null && !$request->bbox->contains($coordinates)) {
                continue;
            }

            if ($request->near !== null) {
                $distance = $request->near->distanceTo($coordinates);

                if ($distance > ((int) $request->radius * 1000)) {
                    continue;
                }

                $this->distances[$candidate['id']] = $distance;
            }

            $ids[] = $candidate['id'];
        }

        if ($request->near !== null) {
            usort($ids, fn(int $a, int $b): int => $this->distances[$a] <=> $this->distances[$b]);
        }

        return $ids;
    }

    /**
     * Narrow the main query of the place archive.
     */
    public function narrow(WP_Query $query): void
    {
        if ($this->invalid || $this->request === null) {
            $query->set('post__in', [0]);

            return;
        }

        $request = $this->request;
        $ids = $this->eligibleIds($request);

        $query->set('post__in', $ids === [] ? [0] : $ids);
        $query->set(
            'posts_per_page',
            $request->view === 'map' ? $this->config->mapResultLimit : $this->config->listPageSize,
        );
        $query->set('orderby', $request->near !== null ? 'post__in' : 'title');
        $query->set('order', 'ASC');

        foreach ($request->terms as $taxonomy => $slugs) {
            $query->set($taxonomy, implode(',', $slugs));
        }
    }

    /**
     * The result, read from the main query once.
     */
    public function result(): PlaceSearchResult
    {
        if ($this->result !== null) {
            return $this->result;
        }

        $features = ['near' => $this->config->nearSearch, 'area' => $this->config->areaSearch];

        $request = $this->request;

        if ($this->invalid || $request === null) {
            return $this->result = new PlaceSearchResult(new PlaceSearchRequest(), $this->invalid, $features);
        }

        $query = WP::query();
        $places = [];

        foreach ($query->posts as $post) {
            $place = Timber::get_post($post);

            if ($place instanceof Place) {
                $places[] = $place;
            }
        }

        $total = $query->found_posts;
        $facets = [];

        foreach ($this->facetTaxonomies() as $taxonomy) {
            $facets[$taxonomy] = $this->facets->for($taxonomy, $query);
        }

        $mapLimitExceeded = $request->view === 'map' && $total > $this->config->mapResultLimit;

        return $this->result = new PlaceSearchResult(
            request: $request,
            invalid: false,
            features: $features,
            total: $total,
            page: max(1, (int) $query->get('paged', 1)),
            pages: $query->max_num_pages,
            places: $places,
            mapLimitExceeded: $mapLimitExceeded,
            distances: $this->distances,
            facets: $facets,
            bounds: $mapLimitExceeded ? null : self::bounds($places, $request),
        );
    }

    /**
     * The area that frames places and the `near` origin, as [[west, south], [east, north]].
     *
     * None for a `bbox` request, which keeps the area the visitor chose, and none
     * without a place.
     *
     * @param list<Place> $places
     * @return array{0: array{0: float, 1: float}, 1: array{0: float, 1: float}}|null
     */
    public static function bounds(array $places, PlaceSearchRequest $request): ?array
    {
        if ($request->bbox !== null) {
            return null;
        }

        $points = array_values(array_filter(array_map(
            static fn(Place $place): ?PlaceCoordinates => $place->coordinates(),
            $places,
        )));

        if ($points === []) {
            return null;
        }

        if ($request->near !== null) {
            $points[] = $request->near;
        }

        $longitudes = array_map(static fn(PlaceCoordinates $point): float => $point->longitude, $points);
        $latitudes = array_map(static fn(PlaceCoordinates $point): float => $point->latitude, $points);

        return [
            [min($longitudes), min($latitudes)],
            [max($longitudes), max($latitudes)],
        ];
    }

    /**
     * The published places of the current language that have coordinates.
     *
     * A WP_Query with filters on, so a multilingual plugin applies its language
     * clause, and the current language is passed and checked again: the result does
     * not depend on the settings of the plugin.
     *
     * @return list<array{id: int, text: string, coordinates: PlaceCoordinates}>
     */
    private function candidates(): array
    {
        $multilingual = multilingual();
        $language = $multilingual?->currentLanguage();
        $args = [
            'post_type' => Place::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'suppress_filters' => false,
            'orderby' => 'none',
        ];

        if ($language !== null) {
            $args['lang'] = $language;
        }

        $candidates = [];

        foreach (new WP_Query($args)->posts as $id) {
            $id = (int) $id;

            if ($multilingual !== null && $language !== null) {
                $post = get_post($id);

                if (!$post instanceof WP_Post || $multilingual->language($post) !== $language) {
                    continue;
                }
            }

            $coordinates = PlaceCoordinates::from(
                get_post_meta($id, 'latitude', true),
                get_post_meta($id, 'longitude', true),
            );

            if ($coordinates === null) {
                continue;
            }

            $text = [get_post_field('post_title', $id), wp_strip_all_tags(get_post_field('post_content', $id))];

            foreach (Place::ADDRESS_FIELDS as $field) {
                $value = get_post_meta($id, $field, true);
                $text[] = is_scalar($value) ? (string) $value : '';
            }

            $candidates[] = [
                'id' => $id,
                'text' => mb_strtolower(implode("\n", $text)),
                'coordinates' => $coordinates,
            ];
        }

        return $candidates;
    }

    /**
     * Whether every slug is a term of the taxonomy, in the current language.
     *
     * @param list<string> $slugs
     */
    private function termsExist(string $taxonomy, array $slugs): bool
    {
        $args = [
            'taxonomy' => $taxonomy,
            'slug' => $slugs,
            'hide_empty' => false,
        ];
        $language = multilingual()?->currentLanguage();

        if ($language !== null) {
            $args['lang'] = $language;
        }

        $found = get_terms($args);

        if (!is_array($found)) {
            return false;
        }

        $found = array_map(static fn(mixed $term): string => $term instanceof WP_Term ? $term->slug : '', $found);

        return array_diff($slugs, $found) === [];
    }
}
