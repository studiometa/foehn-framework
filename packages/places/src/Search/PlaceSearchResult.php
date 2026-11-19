<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Places\Search;

use Studiometa\Foehn\Places\Models\Place;
use Studiometa\Foehn\Query\FacetOption;

/**
 * One search, read once per request: the list and the map are two projections of it.
 *
 * The list holds the places of the current page. The map holds every match up to
 * `PlacesConfig::$mapResultLimit`, and says when the total is above it, so a template
 * asks to refine the search rather than show an incomplete map as complete.
 */
final readonly class PlaceSearchResult
{
    /**
     * @param array{near: bool, area: bool} $features The position features that are on
     * @param list<Place> $places The places of the main query: a page of the list, or the map places
     * @param array<int, float> $distances Distance from `near`, in metres, by place id
     * @param array<string, list<FacetOption>> $facets Facet options, by taxonomy
     * @param array{0: array{0: float, 1: float}, 1: array{0: float, 1: float}}|null $bounds
     *   The area to frame, as [[west, south], [east, north]]
     */
    public function __construct(
        public PlaceSearchRequest $request,
        public bool $invalid,
        public array $features,
        public int $total = 0,
        public int $page = 1,
        public int $pages = 0,
        public array $places = [],
        public bool $mapLimitExceeded = false,
        public array $distances = [],
        public array $facets = [],
        public ?array $bounds = null,
    ) {}

    /**
     * The places of the list view: the current page.
     *
     * @return list<Place>
     */
    public function list(): array
    {
        return $this->request->view === 'list' ? $this->places : [];
    }

    /**
     * The places of the map view, empty when the total is above the map limit.
     *
     * @return list<Place>
     */
    public function map(): array
    {
        return $this->request->view === 'map' && !$this->mapLimitExceeded ? $this->places : [];
    }

    /**
     * The map features: id, Mapbox `[lng, lat]`, title and link of each map place.
     *
     * @return list<array{id: int, lngLat: array{0: float, 1: float}, title: string, link: string}>
     */
    public function markers(): array
    {
        $markers = [];

        foreach ($this->map() as $place) {
            $coordinates = $place->coordinates();

            if ($coordinates !== null) {
                $markers[] = [
                    'id' => $place->ID,
                    'lngLat' => $coordinates->toLngLat(),
                    'title' => $place->title(),
                    'link' => $place->link(),
                ];
            }
        }

        return $markers;
    }

    /**
     * The distance of a place from `near`, in metres, or null.
     */
    public function distance(Place|int $place): ?float
    {
        return $this->distances[$place instanceof Place ? $place->ID : $place] ?? null;
    }

    /**
     * The selected term slugs, by taxonomy.
     *
     * @return array<string, list<string>>
     */
    public function selected(): array
    {
        return $this->request->terms;
    }
}
