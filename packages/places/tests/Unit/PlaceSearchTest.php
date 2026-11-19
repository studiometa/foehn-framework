<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Places\Config\PlacesConfig;
use Studiometa\Foehn\Places\Search\PlaceSearch;
use Studiometa\Foehn\Places\Search\PlaceSearchRequest;
use Studiometa\Foehn\Places\Search\PlaceSearchResult;
use Studiometa\Foehn\Places\Tests\Fixtures\TestPlace;
use Studiometa\Foehn\Query\Facets;

/**
 * @param array<int, array{title: string, lat: mixed, lng: mixed, city?: string, content?: string}> $places
 */
function seedPlaces(array $places): void
{
    $GLOBALS['wp_stub_query_posts'] = static function (array $vars) use ($places): array {
        $GLOBALS['places_stub_candidate_vars'] = $vars;

        return array_keys($places);
    };

    foreach ($places as $id => $place) {
        $GLOBALS['wp_stub_post_fields'][$id] = [
            'post_title' => $place['title'],
            'post_content' => $place['content'] ?? '',
        ];
        $GLOBALS['wp_stub_post_meta'][$id] = [
            'latitude' => $place['lat'],
            'longitude' => $place['lng'],
            'city' => $place['city'] ?? '',
        ];
        $post = new WP_Post();
        $post->ID = $id;
        $post->post_type = 'place';
        $GLOBALS['wp_stub_posts'][$id] = $post;
    }
}

function placeSearch(?PlacesConfig $config = null): PlaceSearch
{
    return new PlaceSearch($config ?? new PlacesConfig(), new FoehnConfig(), new Facets());
}

beforeEach(function () {
    wp_stub_reset();
    $GLOBALS['wp_stub_taxonomies'] = ['place_type', 'place_service'];
    $GLOBALS['places_stub_place_taxonomies'] = ['place_type', 'place_service'];

    seedPlaces([
        1 => ['title' => 'Lyon Part-Dieu', 'lat' => 45.7606, 'lng' => 4.8597, 'city' => 'Lyon'],
        2 => [
            'title' => 'Villeurbanne',
            'lat' => 45.7719,
            'lng' => 4.8902,
            'content' => '<p>Near the <b>Lyon</b> campus</p>',
        ],
        3 => ['title' => 'Paris Bercy', 'lat' => 48.8396, 'lng' => 2.3799, 'city' => 'Paris'],
        4 => ['title' => 'Nowhere', 'lat' => '', 'lng' => 4.0],
        5 => ['title' => 'Auckland', 'lat' => -36.8485, 'lng' => 174.7633],
    ]);

    $agency = new WP_Term();
    $agency->term_id = 1;
    $agency->slug = 'agency';
    $agency->taxonomy = 'place_type';
    $GLOBALS['wp_stub_terms']['place_type'] = [$agency];
});

describe('PlaceSearch eligible places', function () {
    it('reads published places only, with filters on', function () {
        placeSearch()->eligibleIds(new PlaceSearchRequest());

        expect($GLOBALS['places_stub_candidate_vars'])->toMatchArray([
            'post_type' => 'place',
            'post_status' => 'publish',
            'suppress_filters' => false,
        ]);
    });

    it('never includes a place without coordinates', function () {
        expect(placeSearch()->eligibleIds(new PlaceSearchRequest()))->toBe([1, 2, 3, 5]);
    });

    it('matches text in the title, the description and the address, ignoring case', function () {
        expect(placeSearch()->eligibleIds(new PlaceSearchRequest(q: 'LYON')))->toBe([1, 2]);
        expect(placeSearch()->eligibleIds(new PlaceSearchRequest(q: 'paris')))->toBe([3]);
        expect(placeSearch()->eligibleIds(new PlaceSearchRequest(q: 'campus')))->toBe([2]);
    });

    it('matches a capitalized query, as typed or parsed from the request', function () {
        expect(placeSearch()->eligibleIds(new PlaceSearchRequest(q: 'Paris')))->toBe([3]);
        expect(placeSearch()->eligibleIds(new PlaceSearchRequest(q: 'PARIS Bercy')))->toBe([3]);

        $search = placeSearch();
        $search->parse('q=Paris');
        $query = new WP_Query();
        $search->narrow($query);

        expect($search->request()?->q)->toBe('Paris')->and($query->query_vars['post__in'])->toBe([3]);
    });

    it('keeps places within the radius, nearest first, with their distances', function () {
        $search = placeSearch();
        $request = PlaceSearchRequest::fromQueryString('near=45.7719,4.8902&radius=10', new PlacesConfig());

        expect($search->eligibleIds($request))->toBe([2, 1]);
        expect($search->result()->distances ?? null)->toBeArray();
    });

    it('includes a place exactly on the radius', function () {
        $origin = PlaceSearchRequest::fromQueryString('near=45.7719,4.8902', new PlacesConfig());
        $distance = $origin->near?->distanceTo(\Studiometa\Foehn\Places\ValueObjects\PlaceCoordinates::from(
            45.7606,
            4.8597,
        ));

        expect($distance)->toBeLessThan(10_000);
        expect(placeSearch(new PlacesConfig(radiusOptions: [10, 25], defaultRadius: 10))->eligibleIds($origin))
            ->toContain(1);

        seedPlaces([7 => [
            'title' => 'Edge',
            'lat' => 45.7719 + ((10_000 / 6_371_008.8) * (180 / M_PI)),
            'lng' => 4.8902,
        ]]);
        $request = new PlaceSearchRequest(near: $origin->near, radius: 10);

        expect(placeSearch()->eligibleIds($request))->toBe([7]);
    });

    it('keeps places inside a bounding box, across the antimeridian too', function () {
        $box = static fn(string $bbox): PlaceSearchRequest => PlaceSearchRequest::fromQueryString(
            'bbox=' . $bbox,
            new PlacesConfig(),
        );

        expect(placeSearch()->eligibleIds($box('4.7,45.6,5,45.9')))->toBe([1, 2]);
        expect(placeSearch()->eligibleIds($box('170,-40,190,-30')))->toBe([5]);
        expect(placeSearch()->eligibleIds($box('-180,-90,180,90')))->toBe([1, 2, 3, 5]);
    });

    it('never includes a place of another language', function () {
        $GLOBALS['pll_stub_languages'] = ['fr' => 'Français', 'en' => 'English'];
        $GLOBALS['pll_stub_current'] = 'en';
        $GLOBALS['pll_stub_post_translations'] = [
            1 => ['fr' => 1, 'en' => 2],
            2 => ['fr' => 1, 'en' => 2],
            3 => ['fr' => 3],
            5 => ['en' => 5],
        ];

        expect(placeSearch()->eligibleIds(new PlaceSearchRequest()))->toBe([2, 5]);
        expect($GLOBALS['places_stub_candidate_vars']['lang'])->toBe('en');
    });
});

describe('PlaceSearch facet taxonomies', function () {
    it('skips a configured taxonomy that is not a taxonomy of place', function () {
        $search = new PlaceSearch(
            new PlacesConfig(facetTaxonomies: ['place_type', 'category', 'missing']),
            new FoehnConfig(),
            new Facets(),
        );

        expect($search->facetTaxonomies())->toBe(['place_type']);
    });

    it('names each skipped taxonomy in a notice in debug mode', function () {
        $GLOBALS['wp_stub_taxonomies'][] = 'category';
        $notices = [];
        set_error_handler(static function (int $level, string $message) use (&$notices): bool {
            $notices[] = $message;

            return true;
        }, E_USER_NOTICE);

        try {
            $taxonomies = new PlaceSearch(
                new PlacesConfig(facetTaxonomies: ['place_type', 'category']),
                new FoehnConfig(debug: true),
                new Facets(),
            )->facetTaxonomies();
        } finally {
            restore_error_handler();
        }

        expect($taxonomies)->toBe(['place_type']);
        expect($notices)->toBe([
            '[foehn-places] The facet taxonomy "category" is not a taxonomy of "place": it is skipped.',
        ]);
    });
});

describe('PlaceSearch main query', function () {
    it('narrows the main query to the eligible places, ordered by title', function () {
        $search = placeSearch();
        $search->parse('q=lyon&place_type=agency');
        $query = new WP_Query();
        $search->narrow($query);

        expect($query->query_vars)->toMatchArray([
            'post__in' => [1, 2],
            'posts_per_page' => 24,
            'orderby' => 'title',
            'order' => 'ASC',
            'place_type' => 'agency',
        ]);
    });

    it('orders by distance with near, and uses the map limit for the map view', function () {
        $search = placeSearch(new PlacesConfig(mapResultLimit: 100));
        $search->parse('near=45.7719,4.8902&radius=10&view=map');
        $query = new WP_Query();
        $search->narrow($query);

        expect($query->query_vars)->toMatchArray([
            'post__in' => [2, 1],
            'posts_per_page' => 100,
            'orderby' => 'post__in',
        ]);
    });

    it('turns no eligible place into post__in [0], never an empty list', function () {
        $search = placeSearch();
        $search->parse('q=nothing-matches');
        $query = new WP_Query();
        $search->narrow($query);

        expect($query->query_vars['post__in'])->toBe([0]);
    });

    it('matches nothing for an invalid request', function () {
        $search = placeSearch(new PlacesConfig(nearSearch: false));
        $search->parse('near=45,4');
        $query = new WP_Query();
        $search->narrow($query);

        expect($search->isInvalid())->toBeTrue();
        expect($query->query_vars['post__in'])->toBe([0]);
    });

    it('records an unknown term as an invalid request', function () {
        $search = placeSearch();
        $search->parse('place_type=unknown');

        expect($search->isInvalid())->toBeTrue();
    });

    it('counts each facet with the position scope and without its own group only', function () {
        $term = static function (int $id, string $slug, string $taxonomy): WP_Term {
            $term = new WP_Term();
            $term->term_id = $id;
            $term->slug = $slug;
            $term->taxonomy = $taxonomy;

            return $term;
        };
        $GLOBALS['wp_stub_terms']['place_type'] = [
            $term(1, 'agency', 'place_type'),
            $term(2, 'showroom', 'place_type'),
        ];
        $GLOBALS['wp_stub_terms']['place_service'] = [$term(3, 'repair', 'place_service')];

        $search = placeSearch(new PlacesConfig(facetTaxonomies: ['place_type', 'place_service']));
        $search->parse('near=45.7719,4.8902&radius=10&place_type=agency&place_service=repair');
        $query = new WP_Query();
        $search->narrow($query);

        $counted = [];
        $GLOBALS['wp_stub_query_posts'] = static function (array $vars) use (&$counted): array {
            $counted[] = $vars;

            return [1, 2];
        };
        $GLOBALS['wpdb'] = new wpdb();
        $GLOBALS['wpdb']->results = [];
        $facets = new Facets();
        $facets->for('place_type', $query);
        $facets->for('place_service', $query);

        expect($counted[0]['post__in'])
            ->toBe([2, 1])
            ->and($counted[0])
            ->not
            ->toHaveKey('place_type')
            ->and($counted[0]['place_service'])
            ->toBe('repair');
        expect($counted[1]['post__in'])
            ->toBe([2, 1])
            ->and($counted[1])
            ->not
            ->toHaveKey('place_service')
            ->and($counted[1]['place_type'])
            ->toBe('agency');
    });

    it('keeps the native page of a filtered and positioned archive', function () {
        $search = placeSearch(new PlacesConfig(listPageSize: 1));
        $search->parse('near=45.7719,4.8902&radius=10&place_type=agency');
        $query = new WP_Query();
        $query->set('paged', 2);
        $search->narrow($query);

        expect($query->query_vars)->toMatchArray(['paged' => 2, 'posts_per_page' => 1, 'post__in' => [2, 1]]);
    });
});

describe('PlaceSearch result', function () {
    beforeEach(function () {
        $GLOBALS['wpdb'] = new wpdb();
        $GLOBALS['wpdb']->results = [];
    });

    afterEach(function () {
        unset($GLOBALS['wp_query'], $GLOBALS['wpdb']);
    });

    it('is an empty, invalid result for an invalid request, without reading the main query', function () {
        $search = placeSearch(new PlacesConfig(nearSearch: false));
        $search->parse('near=45,4');
        $result = $search->result();

        expect($result->invalid)
            ->toBeTrue()
            ->and($result->total)
            ->toBe(0)
            ->and($result->places)
            ->toBe([])
            ->and($result->features)
            ->toBe(['near' => false, 'area' => true]);
    });

    it('reads the total, the page and the facets of the main query once', function () {
        $search = placeSearch();
        $search->parse('q=lyon&place_type=agency');
        $GLOBALS['wp_query'] = new WP_Query();
        $search->narrow($GLOBALS['wp_query']);
        $GLOBALS['wp_query']->set('paged', 2);
        $GLOBALS['wp_query']->found_posts = 30;
        $GLOBALS['wp_query']->max_num_pages = 2;
        $result = $search->result();

        expect($result->invalid)
            ->toBeFalse()
            ->and($result->total)
            ->toBe(30)
            ->and($result->page)
            ->toBe(2)
            ->and($result->pages)
            ->toBe(2)
            ->and($result->mapLimitExceeded)
            ->toBeFalse()
            ->and(array_keys($result->facets))
            ->toBe(['place_type'])
            ->and($result->selected())
            ->toBe(['place_type' => ['agency']])
            ->and($search->result())
            ->toBe($result);
    });

    it('says when the map view has more places than the map limit, and frames nothing', function () {
        $search = placeSearch(new PlacesConfig(mapResultLimit: 10));
        $search->parse('view=map');
        $GLOBALS['wp_query'] = new WP_Query();
        $GLOBALS['wp_query']->found_posts = 11;
        $result = $search->result();

        expect($result->mapLimitExceeded)->toBeTrue()->and($result->bounds)->toBeNull();
    });
});

describe('PlaceSearchResult', function () {
    beforeEach(function () {
        $this->places = [
            TestPlace::with(1, ['latitude' => 45.0, 'longitude' => 4.0]),
            TestPlace::with(2, ['latitude' => '', 'longitude' => '']),
        ];
        $this->places[0]->post_title = 'Lyon';
        $GLOBALS['wp_stub_permalinks'][1] = 'https://example.com/places/lyon/';
    });

    it('shows the places in the list view only', function () {
        $features = ['near' => true, 'area' => true];
        $list = new PlaceSearchResult(new PlaceSearchRequest(), false, $features, places: $this->places);
        $map = new PlaceSearchResult(new PlaceSearchRequest(view: 'map'), false, $features, places: $this->places);

        expect($list->list())->toBe($this->places)->and($list->map())->toBe([])->and($map->list())->toBe([]);
    });

    it('has a marker for each map place with coordinates', function () {
        $result = new PlaceSearchResult(
            new PlaceSearchRequest(view: 'map'),
            false,
            ['near' => true, 'area' => true],
            places: $this->places,
        );

        expect($result->map())->toBe($this->places);
        expect($result->markers())->toBe([
            ['id' => 1, 'lngLat' => [4.0, 45.0], 'title' => 'Lyon', 'link' => 'https://example.com/places/lyon/'],
        ]);
    });

    it('has no map places and no markers above the map limit', function () {
        $result = new PlaceSearchResult(
            new PlaceSearchRequest(view: 'map'),
            false,
            ['near' => true, 'area' => true],
            places: $this->places,
            mapLimitExceeded: true,
        );

        expect($result->map())->toBe([])->and($result->markers())->toBe([]);
    });

    it('gives the distance of a place or of its id', function () {
        $result = new PlaceSearchResult(
            new PlaceSearchRequest(),
            false,
            ['near' => true, 'area' => true],
            distances: [1 => 1200.5],
        );

        expect($result->distance($this->places[0]))
            ->toBe(1200.5)
            ->and($result->distance(1))
            ->toBe(1200.5)
            ->and($result->distance(2))
            ->toBeNull();
    });
});

describe('PlaceSearch bounds', function () {
    it('frames the places and the near origin, and nothing for a bbox', function () {
        $places = [
            TestPlace::with(1, ['latitude' => 45.0, 'longitude' => 4.0]),
            TestPlace::with(2, ['latitude' => 46.0, 'longitude' => 5.0]),
        ];

        expect(PlaceSearch::bounds($places, new PlaceSearchRequest()))->toBe([[4.0, 45.0], [5.0, 46.0]]);
        expect(PlaceSearch::bounds($places, PlaceSearchRequest::fromQueryString(
            'near=44,3',
            new PlacesConfig(),
        )))->toBe([[3.0, 44.0], [5.0, 46.0]]);
        expect(PlaceSearch::bounds($places, PlaceSearchRequest::fromQueryString(
            'bbox=3,44,6,47',
            new PlacesConfig(),
        )))->toBeNull();
        expect(PlaceSearch::bounds([], new PlaceSearchRequest()))->toBeNull();
    });
});
