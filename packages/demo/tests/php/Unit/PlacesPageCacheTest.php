<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\PageCacheConfig;
use Studiometa\Foehn\PageCache\Bypass;
use Studiometa\Foehn\Places\Config\PlacesConfig;
use Studiometa\Foehn\Places\Search\PlaceSearchRequest;
use Studiometa\Foehn\Places\View\PlacesView;

/**
 * The page cache of the venue locator, read from the demo's own config files.
 *
 * Unpositioned requests, pages and sections, are keyed. Every positioned request is a
 * bypass, because `near`, `radius` and `bbox` are not cache query arguments.
 */
if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

function venueCacheKey(string $query): ?string
{
    /** @var PageCacheConfig $config */
    $config = require dirname(__DIR__, 3) . '/theme/app/page-cache.config.php';
    $key = new Bypass($config)->key([
        'REQUEST_METHOD' => 'GET',
        'HTTP_HOST' => 'example.com',
        'REQUEST_URI' => '/venues/' . ($query === '' ? '' : '?' . $query),
        'QUERY_STRING' => $query,
    ]);

    return $key === null ? null : (string) $key->variant;
}

describe('Venue locator page cache', function () {
    it('keys unpositioned pages and section requests', function (string $query) {
        expect(venueCacheKey($query))->not->toBeNull();
    })->with([
        'archive' => [''],
        'text' => ['q=lyon'],
        'empty text, as the form submits it' => ['q=&place_type[]=gallery'],
        'map view' => ['view=map'],
        'facets' => ['place_type=gallery,bookshop&venue_region=provence'],
        'repeated facet' => ['place_type[]=gallery&place_type[]=museum'],
        'sections' => ['q=lyon&foehn_sections=place-filters,place-summary,place-results,place-pagination'],
    ]);

    it('bypasses every positioned page and section request', function (string $query) {
        expect(venueCacheKey($query))->toBeNull();
    })->with([
        'near' => ['near=45.764,4.8357'],
        'near and radius' => ['near=45.764,4.8357&radius=50'],
        'bbox' => ['bbox=4.7,45.6,5,45.9&view=map'],
        'near section' => [
            'near=45.764,4.8357&foehn_sections=place-filters,place-summary,place-results,place-pagination',
        ],
        'bbox section' => ['bbox=4.7,45.6,5,45.9&view=map&foehn_sections=place-results'],
    ]);

    it('writes the URL of a position with five decimals', function () {
        $GLOBALS['wp_stub_archive_links']['place'] = 'https://example.com/venues/';
        /** @var PlacesConfig $config */
        $config = require dirname(__DIR__, 3) . '/theme/app/places.config.php';
        $request = PlaceSearchRequest::fromQueryString('near=45.76404123,4.835659999&radius=10', $config);

        expect(new PlacesView($config)->url($request))
            ->toBe('https://example.com/venues/?near=45.76404%2C4.83566&radius=10');
    });
});
