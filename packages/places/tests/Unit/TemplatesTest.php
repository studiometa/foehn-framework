<?php

declare(strict_types=1);

use Studiometa\Foehn\Places\Config\PlacesConfig;
use Studiometa\Foehn\Places\Hooks\PlacesHooks;
use Studiometa\Foehn\Places\Search\PlaceSearchRequest;
use Studiometa\Foehn\Places\Search\PlaceSearchResult;
use Studiometa\Foehn\Places\Tests\Fixtures\TestPlace;
use Studiometa\Foehn\Places\View\PlacesView;
use Studiometa\Foehn\Query\FacetOption;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * A Twig environment with the package namespaces and the Timber and Føhn functions the
 * templates call. Autoescape is off, as in Timber: the templates escape every value.
 *
 * @param array<int|string, list<string>> $locations
 */
function placesTwig(array $locations = []): Environment
{
    $locations = PlacesHooks::locations($locations, '/nonexistent/child', '/nonexistent/parent');
    $loader = new FilesystemLoader();

    foreach ($locations as $namespace => $paths) {
        foreach ($paths as $path) {
            $loader->addPath($path, (string) $namespace);
        }
    }

    $twig = new Environment($loader, ['autoescape' => false, 'strict_variables' => true]);
    $twig->addFunction(new TwigFunction('__', static fn(string $text): string => $text));

    foreach (['esc_url', 'esc_html', 'esc_attr'] as $filter) {
        $twig->addFilter(new TwigFilter($filter, $filter));
    }

    return $twig;
}

/**
 * @param array<string, mixed> $overrides
 */
function placesResult(array $overrides = [], ?PlacesConfig $config = null): PlaceSearchResult
{
    $config ??= new PlacesConfig();

    return new PlaceSearchResult(...[
        'request' => new PlaceSearchRequest(),
        'invalid' => false,
        'features' => ['near' => $config->nearSearch, 'area' => $config->areaSearch],
        ...$overrides,
    ]);
}

/**
 * @param array<string, mixed> $context
 */
function renderPlaces(
    string $template,
    PlaceSearchResult $result,
    ?PlacesConfig $config = null,
    array $context = [],
): string {
    return placesTwig()->render('@places/' . $template, [
        'result' => $result,
        'places' => new PlacesView($config ?? new PlacesConfig()),
        ...$context,
    ]);
}

function lyon(): TestPlace
{
    return TestPlace::with(7, [
        'title' => 'Lyon <Part-Dieu>',
        'latitude' => '45.7606',
        'longitude' => '4.8597',
        'street' => '1 rue "Garibaldi"',
        'city' => 'Lyon',
        'phone' => '+33 4 00 00 00 00',
        'types' => ['Agency & co'],
    ]);
}

function agencyOption(int $count = 3, bool $active = false): FacetOption
{
    $term = new WP_Term();
    $term->term_id = 1;
    $term->slug = 'agency';
    $term->name = 'Agency <b>';
    $term->taxonomy = 'place_type';

    return new FacetOption($term, $count, $active);
}

beforeEach(function () {
    wp_stub_reset();
    $GLOBALS['wp_stub_archive_links']['place'] = 'https://example.com/place/';
    bootTestContainer()->singleton(PlacesConfig::class, static fn() => new PlacesConfig());
});

afterEach(fn() => tearDownTestContainer());

describe('Template namespaces', function () {
    it('looks in the child theme, the parent theme, then the package', function () {
        $root = sys_get_temp_dir() . '/foehn-places-' . uniqid();
        mkdir($root . '/child/templates/places', 0o777, true);
        mkdir($root . '/parent/templates/places', 0o777, true);

        $locations = PlacesHooks::locations([0 => ['/theme/templates']], $root . '/child', $root . '/parent');

        expect($locations[0])
            ->toBe(['/theme/templates'])
            ->and($locations['places'])
            ->toBe([$root . '/child/templates/places', $root . '/parent/templates/places', PlacesHooks::TEMPLATES])
            ->and($locations['places-pkg'])
            ->toBe([PlacesHooks::TEMPLATES]);

        removeTestDirectory($root);
    });

    it('leaves out a theme without overrides and counts one theme once', function () {
        expect(PlacesHooks::locations([], '/nonexistent', '/nonexistent')['places'])->toBe([PlacesHooks::TEMPLATES]);
    });

    it('lets a theme override replace a package template and reuse the package one', function () {
        $root = sys_get_temp_dir() . '/foehn-places-' . uniqid();
        mkdir($root . '/templates/places', 0o777, true);
        file_put_contents(
            $root . '/templates/places/Empty.twig',
            '<div class="theme-empty">{% include "@places-pkg/Empty.twig" %}</div>',
        );

        $locations = PlacesHooks::locations([], $root, $root);
        $loader = new FilesystemLoader();

        foreach ($locations as $namespace => $paths) {
            foreach ($paths as $path) {
                $loader->addPath($path, (string) $namespace);
            }
        }

        $twig = placesTwig();
        $twig->setLoader($loader);
        $html = $twig->render('@places/Results.twig', [
            'result' => placesResult(),
            'places' => new PlacesView(new PlacesConfig()),
        ]);

        expect($html)->toContain('<div class="theme-empty"><div class="places-empty">');

        removeTestDirectory($root);
    });

    it('ships every template of the spec', function () {
        $templates = array_map('basename', glob(PlacesHooks::TEMPLATES . '/*.twig') ?: []);

        expect($templates)->toEqualCanonicalizing([
            'Empty.twig',
            'Facet.twig',
            'Filters.twig',
            'Geocoder.twig',
            'Map.twig',
            'MapItems.twig',
            'Pagination.twig',
            'PlaceItem.twig',
            'PlaceList.twig',
            'PlacePopup.twig',
            'Results.twig',
            'Summary.twig',
            'ViewSwitch.twig',
        ]);
    });
});

describe('Filters', function () {
    it('is a GET form to the archive that works without JavaScript', function () {
        $html = renderPlaces('Filters.twig', placesResult(['facets' => ['place_type' => [agencyOption()]]]));

        expect($html)
            ->toContain('id="place-search"')
            ->toContain('action="https://example.com/place/"')
            ->toContain('method="get"')
            ->toContain('name="q"')
            ->toMatch('/name="place_type\[\]"\s+value="agency"/')
            ->toContain('<button type="submit">')
            ->toContain('data-option-selector="[data-foehn-section]"')
            ->toContain('data-option-mode="morph"')
            ->toContain('data-option-params="{&quot;foehn_sections&quot;:&quot;' . PlacesView::SECTIONS . '&quot;}"')
            ->not->toContain('data-option-src');
    });

    it('keeps empty position controls disabled, so an unpositioned form submits none', function () {
        $html = renderPlaces('Filters.twig', placesResult());

        expect($html)
            ->toMatch('/name="near"\s+value=""\s+disabled/')
            ->toMatch('/name="bbox"\s+value=""\s+disabled/')
            ->not->toContain('name="radius"')
            ->not->toContain('name="view"')
            ->not->toContain('Clear position');
    });

    it('renders the radius and a clear link around a position', function () {
        $request = PlaceSearchRequest::fromQueryString('near=45.764,4.8357&radius=50&view=map', new PlacesConfig());
        $html = renderPlaces('Filters.twig', placesResult(['request' => $request]));

        expect($html)
            ->toMatch('/name="near"\s+value="45.764,4.8357"\s*>/')
            ->toContain('<option value="50" selected>')
            ->toContain('name="view" value="map"')
            ->toContain('href="https://example.com/place/?view=map"')
            ->toContain('Clear position')
            ->not->toContain('data-option-src');
    });

    it('renders no position control when both position features are off', function () {
        $config = new PlacesConfig(nearSearch: false, areaSearch: false);
        $html = renderPlaces('Filters.twig', placesResult(config: $config), $config);

        expect($html)->not->toContain('name="near"')->not->toContain('name="bbox"')->toContain('name="q"');
    });

    it('escapes the text query and the facet labels', function () {
        $request = PlaceSearchRequest::fromQueryString('q=' . rawurlencode('"><script>x</script>'), new PlacesConfig());
        $html = renderPlaces('Filters.twig', placesResult([
            'request' => $request,
            'facets' => ['place_type' => [agencyOption(0, true)]],
        ]));

        expect($html)
            ->not
            ->toContain('<script>')
            ->toContain('value="&quot;&gt;&lt;script&gt;x&lt;/script&gt;"')
            ->toContain('Agency &lt;b&gt;')
            ->toMatch('/value="agency"\s+checked\s*>/');
    });

    it('disables a facet option that gives no place, unless it is selected', function () {
        $html = renderPlaces('Filters.twig', placesResult(['facets' => ['place_type' => [agencyOption(0)]]]));

        expect($html)->toMatch('/value="agency"\s+disabled\s*>/');
    });
});

describe('Geocoder', function () {
    it('renders the token, the options and the Fetch target', function () {
        $config = new PlacesConfig(accessToken: 'pk.test"', geocoderOptions: ['country' => 'fr']);
        $html = renderPlaces('Geocoder.twig', placesResult(config: $config), $config);

        expect($html)
            ->toContain('data-component="Action MapboxGeocoder"')
            ->toContain('data-option-access-token="pk.test&quot;"')
            ->toContain(
                'data-option-options="{&quot;language&quot;:&quot;en&quot;,&quot;country&quot;:&quot;fr&quot;}"',
            )
            ->toContain('data-on:map-result="Fetch(#place-search) -> () => {');
    });

    it('renders nothing without a token or with nearSearch off', function () {
        expect(trim(renderPlaces('Geocoder.twig', placesResult())))->toBe('');

        $config = new PlacesConfig(accessToken: 'pk.test', nearSearch: false);
        expect(trim(renderPlaces('Geocoder.twig', placesResult(config: $config), $config)))->toBe('');
    });
});

describe('Results', function () {
    it('lists the places of the page with escaped values and the distance', function () {
        $request = PlaceSearchRequest::fromQueryString('near=45.764,4.8357', new PlacesConfig());
        $html = renderPlaces('Results.twig', placesResult([
            'request' => $request,
            'total' => 1,
            'places' => [lyon()],
            'distances' => [7 => 2400.0],
            'bounds' => [[4.8357, 45.7606], [4.8597, 45.764]],
        ]));

        expect($html)
            ->toContain('<li id="place-7" class="places-list__item">')
            ->toContain('Lyon &lt;Part-Dieu&gt;')
            ->toContain('1 rue &quot;Garibaldi&quot;')
            ->toContain('Agency &amp; co')
            ->toContain('2.4 km')
            ->toContain('href="tel:+33400000000"')
            ->toContain('data-bounds="[[4.8357,45.7606],[4.8597,45.764]]"')
            ->not->toContain('MapboxClusterItem');
    });

    it('renders map items in the Mapbox order with a popup', function () {
        $request = PlaceSearchRequest::fromQueryString('view=map', new PlacesConfig());
        $html = renderPlaces('Results.twig', placesResult(['request' => $request, 'total' => 1, 'places' => [lyon()]]));

        expect($html)
            ->toContain('id="place-7"')
            ->toContain('data-component="MapboxClusterItem"')
            ->toContain('data-option-id="7"')
            ->toContain('data-option-lng-lat="[4.8597,45.7606]"')
            ->toContain('<template data-ref="popup">')
            ->not->toContain('data-bounds');
    });

    it('asks to refine the search above the map limit', function () {
        $request = PlaceSearchRequest::fromQueryString('view=map', new PlacesConfig());
        $html = renderPlaces('Results.twig', placesResult([
            'request' => $request,
            'total' => 300,
            'places' => [lyon()],
            'mapLimitExceeded' => true,
        ]));

        expect($html)->toContain('300 places match')->not->toContain('MapboxClusterItem');
    });

    it('renders an empty state with a way back', function () {
        $request = PlaceSearchRequest::fromQueryString('q=zzz', new PlacesConfig());

        expect(renderPlaces('Results.twig', placesResult(['request' => $request])))
            ->toContain('No place matches this search.')
            ->toContain('Clear the search');
        expect(renderPlaces('Results.twig', placesResult(['invalid' => true])))
            ->toContain('This search cannot be read')
            ->toContain('href="https://example.com/place/"');
    });
});

describe('Summary and pagination', function () {
    it('says the total, the scope and links the two views with the same state', function () {
        $request = PlaceSearchRequest::fromQueryString(
            'near=45.764,4.8357&place_type=agency',
            new PlacesConfig(),
            [
                'place_type',
            ],
        );
        $html = renderPlaces('Summary.twig', placesResult(['request' => $request, 'total' => 4]));

        expect($html)
            ->toContain('4 places')
            ->toContain('within 25 km of the chosen address')
            ->toContain('href="https://example.com/place/?near=45.764%2C4.8357&amp;radius=25&amp;place_type=agency"')
            ->toContain(
                'href="https://example.com/place/?near=45.764%2C4.8357&amp;radius=25&amp;place_type=agency&amp;view=map"',
            )
            ->toContain('id="place-status"');
    });

    it('paginates the list with links that keep the request', function () {
        $request = PlaceSearchRequest::fromQueryString('q=lyon', new PlacesConfig());
        $html = renderPlaces('Pagination.twig', placesResult(['request' => $request, 'page' => 1, 'pages' => 3]));

        expect($html)
            ->toContain('<span class="places-pagination__page" aria-current="page">1</span>')
            ->toContain('q=lyon')
            ->toContain('href="https://example.com/place/?paged=2&amp;q=lyon"')
            ->toContain('data-option-params="{&quot;foehn_sections&quot;:&quot;' . PlacesView::SECTIONS . '&quot;}"')
            ->not->toContain('data-option-src');
    });

    it('renders no pagination in the map view', function () {
        $request = PlaceSearchRequest::fromQueryString('view=map', new PlacesConfig());

        expect(trim(renderPlaces('Pagination.twig', placesResult(['request' => $request, 'pages' => 3]))))->toBe('');
    });
});

describe('Map', function () {
    it('renders the map shell with Search this area, outside the items block', function () {
        $config = new PlacesConfig(accessToken: 'pk.test');
        $html = renderPlaces(
            'Map.twig',
            placesResult([
                'bounds' => [[4.0, 45.0], [5.0, 46.0]],
            ], $config),
            $config,
        );

        expect($html)
            ->toContain('data-component="StoreLocator"')
            ->toContain('id="place-map"')
            ->toContain('data-component="MapboxMap Action"')
            ->toContain('data-component="MapboxCluster"')
            ->toContain('data-component="MapboxNavigationControl"')
            ->toContain('data-ref="container"')
            ->toContain('&quot;bounds&quot;:[[4,45],[5,46]]')
            ->toContain('&quot;maxZoom&quot;:12')
            ->toContain('id="place-search-area"')
            ->toContain('event.detail.event.originalEvent');
    });

    it('renders no Search this area button with areaSearch off', function () {
        $config = new PlacesConfig(accessToken: 'pk.test', areaSearch: false);
        $html = renderPlaces('Map.twig', placesResult(config: $config), $config);

        expect($html)->not->toContain('place-search-area')->not->toContain('map-moveend');
    });

    it('renders an unavailable state without a token', function () {
        $html = renderPlaces('Map.twig', placesResult());

        expect($html)->toContain('The map is not available')->not->toContain('MapboxMap');
    });
});

describe('PlacesView', function () {
    it('formats distances', function () {
        $view = new PlacesView(new PlacesConfig());

        expect($view->distance(846.0))
            ->toBe('850 m')
            ->and($view->distance(2400.0))
            ->toBe('2.4 km')
            ->and($view->distance(36_400.0))
            ->toBe('36 km');
    });

    it('builds URLs without the default view and without empty values', function () {
        $view = new PlacesView(new PlacesConfig());
        $request = PlaceSearchRequest::fromQueryString('q=a b&view=map', new PlacesConfig());

        expect($view->url($request, ['view' => 'list']))
            ->toBe('https://example.com/place/?q=a%20b')
            ->and($view->url($request, ['q' => null]))
            ->toBe('https://example.com/place/?view=map');
    });

    it('lets a configured geocoder language win', function () {
        $view = new PlacesView(new PlacesConfig(geocoderOptions: ['language' => 'de']));

        expect($view->geocoderOptions())->toBe(['language' => 'de']);
    });
});

/**
 * The HTML of the demo venue page that the browser tests in
 * packages/demo/tests/js/places mount. It is the demo page template, its section
 * templates and the package templates, rendered here so the browser tests run the shipped markup. A template
 * change that is not rendered again fails this test: run it with
 * UPDATE_BROWSER_FIXTURES=1 to write the files again.
 */
describe('Browser fixtures', function () {
    $fixtures = dirname(__DIR__, 3) . '/demo/tests/js/places/fixtures';

    /**
     * The demo page, with a layout that renders the content block only, and sections
     * that render their template in the wrapper SectionRenderer writes.
     */
    $renderPage = static function (PlaceSearchResult $result, PlacesConfig $config): string {
        $theme = dirname(__DIR__, 3) . '/demo/theme';
        $loader = new FilesystemLoader();
        // The package templates without the demo's PlaceItem override, whose
        // `openingHours` reads post meta through Timber.
        $locations = PlacesHooks::locations([0 => [$theme . '/templates']], '/nonexistent', '/nonexistent');

        foreach ($locations as $namespace => $paths) {
            foreach ($paths as $path) {
                $loader->addPath($path, $namespace === 0 ? FilesystemLoader::MAIN_NAMESPACE : (string) $namespace);
            }
        }

        $twig = placesTwig();
        $twig->setLoader(new ChainLoader([
            new ArrayLoader(['layouts/base.twig' => '{% block content %}{% endblock %}']),
            $loader,
        ]));
        $twig->addFunction(new TwigFunction(
            'foehn_section',
            static fn(array $context, string $name, array $sectionContext = []): string => sprintf(
                '<div id="foehn-section-%s" data-foehn-section="%s">%s</div>',
                $name,
                $name,
                $twig->render('sections/' . $name . '.twig', [...$context, ...$sectionContext]),
            ),
            ['needs_context' => true, 'is_safe' => ['html']],
        ));

        return $twig->render('pages/archive-place.twig', [
            'result' => $result,
            'places' => new PlacesView($config),
        ]);
    };

    $place = static fn(int $id, string $city, float $lat, float $lng): TestPlace => TestPlace::with($id, [
        'title' => $city,
        'link' => '/venues/' . strtolower($city) . '/',
        'latitude' => (string) $lat,
        'longitude' => (string) $lng,
        'city' => $city,
    ]);

    $option = static function (string $slug, string $name): FacetOption {
        $term = new WP_Term();
        $term->term_id = crc32($slug);
        $term->slug = $slug;
        $term->name = $name;
        $term->taxonomy = 'place_type';

        return new FacetOption($term, 1, false);
    };

    it('matches the rendered demo venue page in each state', function () use ($fixtures, $renderPage, $place, $option) {
        $GLOBALS['wp_stub_archive_links']['place'] = '/venues/';
        $config = new PlacesConfig(accessToken: 'pk.test');
        $paris = $place(1, 'Paris', 48.8566, 2.3522);
        $lyon = $place(2, 'Lyon', 45.764, 4.8357);
        $arles = $place(3, 'Arles', 43.6767, 4.6278);
        $facets = ['place_type' => [$option('gallery', 'Gallery'), $option('bookshop', 'Bookshop')]];
        $request = static fn(string $query): PlaceSearchRequest => PlaceSearchRequest::fromQueryString(
            $query,
            $config,
            ['place_type'],
        );
        $states = [
            'list-page-1' => ['request' => $request(''), 'places' => [$paris, $lyon], 'total' => 3, 'pages' => 2],
            'list-page-2' => ['request' => $request(''), 'places' => [$arles], 'total' => 3, 'page' => 2, 'pages' => 2],
            'list-lyon' => ['request' => $request('q=lyon'), 'places' => [$lyon], 'total' => 1, 'pages' => 1],
            'map' => [
                'request' => $request('view=map'),
                'places' => [$paris, $lyon],
                'total' => 2,
                'bounds' => [[2.3522, 45.764], [4.8357, 48.8566]],
            ],
            'map-south' => [
                'request' => $request('view=map&q=south'),
                'places' => [$lyon, $arles],
                'total' => 2,
                'bounds' => [[4.6278, 43.6767], [4.8357, 45.764]],
            ],
        ];

        foreach ($states as $name => $state) {
            $html = $renderPage(placesResult([...$state, 'facets' => $facets], $config), $config);
            $path = $fixtures . '/' . $name . '.html';

            if (getenv('UPDATE_BROWSER_FIXTURES') === '1') {
                file_put_contents($path, $html);
            }

            expect(is_file($path) ? file_get_contents($path) : null)->toBe($html, $name . '.html is out of date');
        }
    });
});
