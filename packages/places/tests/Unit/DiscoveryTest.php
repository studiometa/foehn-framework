<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\DiscoveryProblems;
use Studiometa\Foehn\Discovery\HookDiscovery;
use Studiometa\Foehn\Discovery\JsonLdDiscovery;
use Studiometa\Foehn\Discovery\PostMetaDiscovery;
use Studiometa\Foehn\Discovery\PostTypeDiscovery;
use Studiometa\Foehn\Discovery\SettingsPageDiscovery;
use Studiometa\Foehn\Discovery\TaxonomyDiscovery;
use Studiometa\Foehn\Discovery\TimberModelDiscovery;
use Studiometa\Foehn\Places\Admin\PlacesSettings;
use Studiometa\Foehn\Places\Config\PlacesConfig;
use Studiometa\Foehn\Places\Models\Place;
use Studiometa\Foehn\Places\Models\PlaceType;
use Studiometa\Foehn\Places\Tests\Fixtures\ProjectPlace;
use Studiometa\Foehn\PostTypes\PostTypeRegistry;
use Studiometa\Foehn\Settings\Settings;

/**
 * Discover a class into a discovery, through the cache when asked.
 *
 * @template T of \Tempest\Discovery\Discovery
 * @param T $discovery
 * @param T $fresh
 * @return T
 */
function placesDiscovered(object $discovery, object $fresh, string $class, bool $cached, bool $vendor = true): object
{
    $location = $vendor ? testVendorLocation('Studiometa\\Foehn\\Places\\') : testDiscoveryLocation();
    discoverFixture($discovery, $class, $location);

    return $cached ? restoreThroughCacheFile($discovery, $fresh, $location) : $discovery;
}

beforeEach(function () {
    wp_stub_reset();
    PostTypeRegistry::clear();
    Settings::clear();
    $container = bootTestContainer();
    $container->singleton(
        PlacesConfig::class,
        static fn() => new PlacesConfig(
            archiveSlug: 'stores',
            singular: 'Store',
            plural: 'Stores',
            typeSingular: 'Store type',
            typePlural: 'Store types',
        ),
    );
});

afterEach(function () {
    PostTypeRegistry::clear();
    tearDownTestContainer();
});

describe('foehn-places discovery', function () {
    it('registers the place post type with labels, archive slug, REST and supports from config', function (
        bool $cached,
    ) {
        placesDiscovered(
            new PostTypeDiscovery(new DiscoveryProblems()),
            new PostTypeDiscovery(new DiscoveryProblems()),
            Place::class,
            $cached,
        )->apply();

        $args = wp_stub_get_calls('register_post_type')[0]['args'];

        expect($args['postType'])
            ->toBe('place')
            ->and($args['args']['labels']['name'])
            ->toBe('Stores')
            ->and($args['args']['labels']['singular_name'])
            ->toBe('Store')
            ->and($args['args']['has_archive'])
            ->toBe('stores')
            ->and($args['args']['rewrite'])
            ->toBe(['slug' => 'stores'])
            ->and($args['args']['public'])
            ->toBeTrue()
            ->and($args['args']['show_in_rest'])
            ->toBeTrue()
            ->and($args['args']['supports'])
            ->toBe(['title', 'editor', 'thumbnail', 'revisions', 'custom-fields']);
    })->with(['cold' => [false], 'cached' => [true]]);

    it('registers the hierarchical place_type taxonomy with labels from config', function (bool $cached) {
        placesDiscovered(
            new TaxonomyDiscovery(new DiscoveryProblems()),
            new TaxonomyDiscovery(new DiscoveryProblems()),
            PlaceType::class,
            $cached,
        )->apply();

        $args = wp_stub_get_calls('register_taxonomy')[0]['args'];

        expect($args['taxonomy'])
            ->toBe('place_type')
            ->and($args['objectType'])
            ->toBe(['place'])
            ->and($args['args']['hierarchical'])
            ->toBeTrue()
            ->and($args['args']['public'])
            ->toBeTrue()
            ->and($args['args']['labels']['name'])
            ->toBe('Store types');
    })->with(['cold' => [false], 'cached' => [true]]);

    it('registers the core meta keys of place for REST', function (bool $cached) {
        placesDiscovered(
            new PostMetaDiscovery(new DiscoveryProblems()),
            new PostMetaDiscovery(new DiscoveryProblems()),
            Place::class,
            $cached,
        )->apply();

        $calls = array_column(wp_stub_get_calls('register_meta'), 'args');

        expect(array_column($calls, 'metaKey'))->toBe([
            'latitude',
            'longitude',
            'street',
            'postal_code',
            'city',
            'country',
        ]);
        expect(array_column(array_column($calls, 'args'), 'object_subtype'))->each->toBe('place');
        expect(array_column(array_column($calls, 'args'), 'show_in_rest'))->each->toBeTrue();
    })->with(['cold' => [false], 'cached' => [true]]);

    it('gives Timber and Place::query() the project subclass', function (bool $cached) {
        $models = placesDiscovered(
            new TimberModelDiscovery(new DiscoveryProblems()),
            new TimberModelDiscovery(new DiscoveryProblems()),
            ProjectPlace::class,
            $cached,
            vendor: false,
        );
        $postTypes = placesDiscovered(
            new PostTypeDiscovery(new DiscoveryProblems()),
            new PostTypeDiscovery(new DiscoveryProblems()),
            Place::class,
            $cached,
        );

        // The WordPress order: Timber models on after_setup_theme, post types on init.
        $models->apply();
        $postTypes->apply();

        expect(apply_filters('timber/post/classmap', []))->toBe(['place' => ProjectPlace::class]);
        expect(Place::query()->getParameters()['post_type'] ?? null)->toBe('place');
        expect(ProjectPlace::query()->getParameters()['post_type'] ?? null)->toBe('place');
    })->with(['cold' => [false], 'cached' => [true]]);

    it('edits a project field of the subclass in the Føhn meta box of place', function (bool $cached) {
        placesDiscovered(
            new PostMetaDiscovery(new DiscoveryProblems()),
            new PostMetaDiscovery(new DiscoveryProblems()),
            ProjectPlace::class,
            $cached,
            vendor: false,
        )->apply();

        $hooks = array_column(array_column(wp_stub_get_calls('add_action'), 'args'), 'hook');

        expect($hooks)->toContain('add_meta_boxes_place')->toContain('save_post_place');
        expect(wp_stub_get_calls('register_meta')[0]['args']['args']['object_subtype'])->toBe('place');
    })->with(['cold' => [false], 'cached' => [true]]);

    it('registers the settings page under the place menu', function (bool $cached) {
        placesDiscovered(
            new SettingsPageDiscovery(new DiscoveryProblems()),
            new SettingsPageDiscovery(new DiscoveryProblems()),
            PlacesSettings::class,
            $cached,
        )->apply();

        foreach (wp_stub_get_calls('add_action') as $call) {
            if ($call['args']['hook'] === 'admin_menu') {
                $call['args']['callback']();
            }
        }

        $page = wp_stub_get_calls('add_submenu_page')[0]['args'];

        expect($page['parent_slug'] ?? $page['parentSlug'] ?? null)->toBe('edit.php?post_type=place');
        expect(array_keys(array_column(
            array_column(wp_stub_get_calls('register_setting'), 'args'),
            'optionName',
            'optionName',
        )))->toBe([PlacesSettings::TOKEN, PlacesSettings::STYLE]);
    })->with(['cold' => [false], 'cached' => [true]]);

    it('discovers the hooks once the project lists them, and the JSON-LD provider', function () {
        $hooks = new HookDiscovery(\Tempest\Container\GenericContainer::instance());
        discoverFixture(
            $hooks,
            \Studiometa\Foehn\Places\Hooks\PlacesHooks::class,
            testVendorLocation('Studiometa\\Foehn\\Places\\'),
        );

        // A package hook class is opt-in, as every hook class Føhn ships:
        // DiscoveryRunner hands the classes of FoehnConfig::$hooks back against the app location.
        expect(iterator_to_array($hooks->getItems()))->toBeEmpty();

        discoverFixture($hooks, \Studiometa\Foehn\Places\Hooks\PlacesHooks::class, testDiscoveryLocation());
        $jsonLd = new JsonLdDiscovery(new DiscoveryProblems());
        discoverFixture(
            $jsonLd,
            \Studiometa\Foehn\Places\Seo\PlaceJsonLd::class,
            testVendorLocation('Studiometa\\Foehn\\Places\\'),
        );

        expect(iterator_to_array($hooks->getItems()))->not->toBeEmpty();
        expect(iterator_to_array($jsonLd->getItems()))->toHaveCount(1);
    });
});
