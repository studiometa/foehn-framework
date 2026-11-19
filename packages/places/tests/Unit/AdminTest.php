<?php

declare(strict_types=1);

use Studiometa\Foehn\Places\Admin\PlaceMetaBox;
use Studiometa\Foehn\Places\Admin\PlacesSettings;
use Studiometa\Foehn\Places\Config\PlacesConfig;
use Studiometa\Foehn\Places\Hooks\PlacesHooks;

/**
 * @param array<string, mixed> $fields
 * @return array<string, mixed>
 */
function placeForm(array $fields): array
{
    return [PlaceMetaBox::NONCE => wp_create_nonce(PlaceMetaBox::ID), PlaceMetaBox::FIELD => $fields];
}

function renderedMetaBox(PlaceMetaBox $box): string
{
    $post = new WP_Post();
    $post->ID = 7;
    ob_start();
    $box->render($post);

    return (string) ob_get_clean();
}

beforeEach(function () {
    wp_stub_reset();
    $GLOBALS['wp_stub_user_can'] = ['edit_post' => true];
    unset($GLOBALS['places_stub_autosave'], $GLOBALS['places_stub_revision']);
    $this->box = new PlaceMetaBox(new PlacesConfig());
});

describe('PlaceMetaBox', function () {
    it('saves the coordinates and the address', function () {
        $this->box->save(7, placeForm([
            'latitude' => '45.764',
            'longitude' => '0',
            'street' => ' 1 rue <b>A</b> ',
            'city' => 'Lyon',
        ]));

        expect($GLOBALS['wp_stub_post_meta'][7])->toMatchArray([
            'latitude' => 45.764,
            'longitude' => 0.0,
            'street' => '1 rue A',
            'city' => 'Lyon',
        ]);
    });

    it('keeps the stored coordinates and reports values out of range or a missing half', function (array $fields) {
        $GLOBALS['wp_stub_post_meta'][7] = ['latitude' => 45.0, 'longitude' => 4.0];
        $this->box->save(7, placeForm($fields));

        expect($GLOBALS['wp_stub_post_meta'][7])->toMatchArray(['latitude' => 45.0, 'longitude' => 4.0]);
        expect(renderedMetaBox($this->box))->toContain('The coordinates were not saved');
    })->with([
        'latitude above 90' => [['latitude' => '91', 'longitude' => '4']],
        'longitude below -180' => [['latitude' => '45', 'longitude' => '-181']],
        'a missing half' => [['latitude' => '45', 'longitude' => '']],
        'not a number' => [['latitude' => 'north', 'longitude' => '4']],
    ]);

    it('deletes both coordinates when both are empty', function () {
        $GLOBALS['wp_stub_post_meta'][7] = ['latitude' => 45.0, 'longitude' => 4.0];
        $this->box->save(7, placeForm(['latitude' => '', 'longitude' => '']));

        expect($GLOBALS['wp_stub_post_meta'][7] ?? [])->not->toHaveKeys(['latitude', 'longitude']);
    });

    it('keeps the fields absent from the request', function () {
        $GLOBALS['wp_stub_post_meta'][7] = ['city' => 'Lyon', 'latitude' => 45.0];
        $this->box->save(7, placeForm(['street' => 'Rue']));

        expect($GLOBALS['wp_stub_post_meta'][7])->toMatchArray([
            'city' => 'Lyon',
            'latitude' => 45.0,
            'street' => 'Rue',
        ]);
    });

    it('sanitizes the contact values', function () {
        $this->box->save(7, placeForm([
            'email' => 'not an email',
            'website' => 'https://example.com',
            'phone' => '<i>01</i>',
        ]));

        expect($GLOBALS['wp_stub_post_meta'][7] ?? [])
            ->toMatchArray(['website' => 'https://example.com', 'phone' => '01'])
            ->not->toHaveKey('email');
    });

    it('saves nothing without the nonce, the capability, or for an autosave or a revision', function (
        callable $arrange,
    ) {
        $form = $arrange();
        $this->box->save(7, $form ?? placeForm(['city' => 'Lyon']));

        expect($GLOBALS['wp_stub_post_meta'][7] ?? [])->toBe([]);
    })->with([
        'no nonce' => [fn() => [PlaceMetaBox::FIELD => ['city' => 'Lyon']]],
        'no capability' => [function () {
            $GLOBALS['wp_stub_user_can'] = [];
        }],
        'an autosave' => [function () {
            $GLOBALS['places_stub_autosave'] = 6;
        }],
        'a revision' => [function () {
            $GLOBALS['places_stub_revision'] = 6;
        }],
    ]);

    it('draws contact fields only when they are on', function () {
        expect(renderedMetaBox($this->box))->toContain('foehn_places[phone]')->toContain('foehn_places[latitude]');
        expect(renderedMetaBox(new PlaceMetaBox(new PlacesConfig(contactFields: false))))
            ->not
            ->toContain('foehn_places[phone]')
            ->toContain('foehn_places[latitude]');
    });

    it('draws the address search with a token, and a settings link without one', function () {
        expect(renderedMetaBox($this->box))
            ->toContain('page=place-locator-settings')
            ->not->toContain('data-component="MapboxGeocoder"');

        $html = renderedMetaBox(new PlaceMetaBox(new PlacesConfig(accessToken: 'pk.test', geocoderOptions: [
            'country' => 'fr',
        ])));

        expect($html)
            ->toContain('data-component="MapboxGeocoder"')
            ->toContain('data-option-access-token="pk.test"')
            ->toContain('&quot;language&quot;:&quot;en&quot;')
            ->toContain('&quot;country&quot;:&quot;fr&quot;')
            ->toContain('<script type="module">');
    });

    it('gives the geocoder the content language of a multilingual site', function () {
        $GLOBALS['pll_stub_languages'] = ['fr' => 'Français', 'en' => 'English'];
        $GLOBALS['pll_stub_current'] = 'fr';

        expect(renderedMetaBox(new PlaceMetaBox(new PlacesConfig(accessToken: 'pk.test'))))
            ->toContain('&quot;language&quot;:&quot;fr&quot;');
    });
});

describe('PlacesSettings', function () {
    it('is a subpage of the place menu', function () {
        $attribute = new ReflectionClass(PlacesSettings::class)->getAttributes()[0]->newInstance();

        expect($attribute->parent)
            ->toBe('edit.php?post_type=place')
            ->and($attribute->capability)
            ->toBe('manage_options');
    });

    it('reads the option, and lets a config value win', function () {
        $GLOBALS['wp_stub_options'][PlacesSettings::TOKEN] = 'pk.option';

        expect(PlacesSettings::accessToken(new PlacesConfig()))->toBe('pk.option');
        expect(PlacesSettings::accessToken(new PlacesConfig(accessToken: 'pk.config')))->toBe('pk.config');
        expect(PlacesSettings::style(new PlacesConfig()))->toBe(PlacesConfig::DEFAULT_STYLE);
        expect(PlacesSettings::style(new PlacesConfig(style: 'mapbox://styles/me/x')))->toBe('mapbox://styles/me/x');
    });
});

describe('PlacesHooks', function () {
    beforeEach(function () {
        $this->hooks = static fn(PlacesConfig $config): PlacesHooks => new PlacesHooks(
            $config,
            new \Studiometa\Foehn\Places\Search\PlaceSearch(
                $config,
                new \Studiometa\Foehn\Config\FoehnConfig(),
                new \Studiometa\Foehn\Query\Facets(),
            ),
            new PlaceMetaBox($config),
        );
    });

    it('registers the contact keys when they are on, and not when they are off', function () {
        ($this->hooks)(new PlacesConfig())->registerContactMeta();
        $keys = array_column(array_column(wp_stub_get_calls('register_meta'), 'args'), 'metaKey');

        expect($keys)->toBe(['phone', 'email', 'website']);

        wp_stub_reset();
        ($this->hooks)(new PlacesConfig(contactFields: false))->registerContactMeta();

        expect(wp_stub_get_calls('register_meta'))->toBe([]);
    });

    it('answers the option with the config value, and keeps the stored option on save', function () {
        $hooks = ($this->hooks)(new PlacesConfig(accessToken: 'pk.config'));

        expect($hooks->tokenOption(false))
            ->toBe('pk.config')
            ->and($hooks->keepToken('pk.config', 'pk.stored'))
            ->toBe('pk.stored')
            ->and($hooks->styleOption(false))
            ->toBeFalse()
            ->and($hooks->keepStyle('mapbox://new', 'mapbox://old'))
            ->toBe('mapbox://new');
    });

    it('drops the term query vars of an invalid request, so it is not a 404', function () {
        $GLOBALS['wp_stub_taxonomies'] = ['place_type'];
        $_SERVER['QUERY_STRING'] = 'place_type=unknown';

        $vars = ($this->hooks)(new PlacesConfig())->parseRequest(['post_type' => 'place', 'place_type' => 'unknown']);

        expect($vars)->toBe(['post_type' => 'place']);
        unset($_SERVER['QUERY_STRING']);
    });

    it('keeps the term query vars of a valid request, and leaves other requests alone', function () {
        $GLOBALS['wp_stub_taxonomies'] = ['place_type'];
        $agency = new WP_Term();
        $agency->slug = 'agency';
        $GLOBALS['wp_stub_terms']['place_type'] = [$agency];
        $_SERVER['QUERY_STRING'] = 'place_type=Agency';
        $hooks = ($this->hooks)(new PlacesConfig());

        try {
            expect($hooks->parseRequest(['post_type' => 'place', 'place_type' => 'Agency']))->toBe([
                'post_type' => 'place',
                'place_type' => 'agency',
            ]);
            expect($hooks->parseRequest(['post_type' => 'post', 'place_type' => 'x']))->toBe([
                'post_type' => 'post',
                'place_type' => 'x',
            ]);
        } finally {
            unset($_SERVER['QUERY_STRING']);
        }
    });

    it('narrows the main query of the place archive only, never in the admin', function () {
        $archive = static fn(bool $main = true, string $type = 'place'): WP_Query => new class($main, $type) extends
            WP_Query {
            public function __construct(
                bool $main,
                private string $type,
            ) {
                parent::__construct();
                $this->set_main_query($main);
            }

            public function is_post_type_archive(mixed $postTypes = ''): bool
            {
                return $postTypes === $this->type;
            }
        };
        $hooks = ($this->hooks)(new PlacesConfig());

        $GLOBALS['wp_stub_is_admin'] = true;
        $hooks->narrow($admin = $archive());
        $GLOBALS['wp_stub_is_admin'] = false;
        $hooks->narrow($secondary = $archive(false));
        $hooks->narrow($other = $archive(type: 'post'));

        expect($admin->query_vars)->toBe([])->and($secondary->query_vars)->toBe([])->and($other->query_vars)->toBe([]);

        $hooks->narrow($main = $archive());

        expect($main->query_vars)->toHaveKey('post__in');
    });

    it('answers 400 and no cache to an invalid search of the place archive only', function () {
        $config = new PlacesConfig(nearSearch: false);
        $search = new \Studiometa\Foehn\Places\Search\PlaceSearch(
            $config,
            new \Studiometa\Foehn\Config\FoehnConfig(),
            new \Studiometa\Foehn\Query\Facets(),
        );
        $hooks = new PlacesHooks($config, $search, new PlaceMetaBox($config));
        $GLOBALS['wp_stub_template'] = 'archive';

        $search->parse('');
        $hooks->sendStatus();

        expect($GLOBALS['wp_stub_status_headers'])->toBe([]);

        $search->parse('near=45,4');
        $GLOBALS['wp_stub_template'] = 'single';
        $hooks->sendStatus();

        expect($GLOBALS['wp_stub_status_headers'])->toBe([]);

        $GLOBALS['wp_stub_template'] = 'archive';
        $hooks->sendStatus();

        expect($GLOBALS['wp_stub_status_headers'])
            ->toBe([['code' => 400, 'description' => '']])
            ->and($GLOBALS['places_stub_nocache'] ?? false)
            ->toBeTrue();
        http_response_code(200);
        unset($GLOBALS['places_stub_nocache']);
    });

    it('leaves a single place and other requests alone', function () {
        expect(PlacesHooks::isArchiveRequest(['post_type' => 'place', 'place' => 'lyon']))->toBeFalse();
        expect(PlacesHooks::isArchiveRequest(['post_type' => 'post']))->toBeFalse();
        expect(PlacesHooks::isArchiveRequest(['post_type' => 'place', 'paged' => 2]))->toBeTrue();
    });
});
