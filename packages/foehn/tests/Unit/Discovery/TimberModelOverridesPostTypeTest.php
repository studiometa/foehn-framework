<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsPostType;
use Studiometa\Foehn\Attributes\AsTimberModel;
use Studiometa\Foehn\Discovery\DiscoveryProblems;
use Studiometa\Foehn\Discovery\PostTypeDiscovery;
use Studiometa\Foehn\Discovery\TimberModelDiscovery;
use Studiometa\Foehn\Models\Post;
use Studiometa\Foehn\PostTypes\PostTypeRegistry;

/**
 * A package registers a post type with its own model, and a project maps a subclass
 * to the same post type. The project class must win, whatever the order the two
 * discoveries apply in, and whether the items come from a scan or from the cache.
 */
#[AsPostType(name: 'overridable_place', singular: 'Place', plural: 'Places')]
class OverridablePackagePlace extends Post {}

#[AsTimberModel(name: 'overridable_place')]
final class OverridableProjectPlace extends OverridablePackagePlace {}

/**
 * @return array{0: PostTypeDiscovery, 1: TimberModelDiscovery}
 */
function overridableDiscoveries(bool $cached): array
{
    $vendor = testVendorLocation();
    $app = testDiscoveryLocation();
    $postTypes = new PostTypeDiscovery(new DiscoveryProblems());
    $models = new TimberModelDiscovery(new DiscoveryProblems());
    discoverFixture($postTypes, OverridablePackagePlace::class, $vendor);
    discoverFixture($models, OverridableProjectPlace::class, $app);

    if (!$cached) {
        return [$postTypes, $models];
    }

    return [
        restoreThroughCacheFile($postTypes, new PostTypeDiscovery(new DiscoveryProblems()), $vendor),
        restoreThroughCacheFile($models, new TimberModelDiscovery(new DiscoveryProblems()), $app),
    ];
}

beforeEach(function () {
    wp_stub_reset();
    PostTypeRegistry::clear();
});

afterEach(fn() => PostTypeRegistry::clear());

describe('a Timber model over a post type of the same name', function () {
    it('wins in the Timber class map', function (bool $cached, bool $modelsFirst) {
        [$postTypes, $models] = overridableDiscoveries($cached);

        if ($modelsFirst) {
            $models->apply();
            $postTypes->apply();
        } else {
            $postTypes->apply();
            $models->apply();
        }

        expect(apply_filters('timber/post/classmap', []))->toBe([
            'overridable_place' => OverridableProjectPlace::class,
        ]);
    })->with([
        'cold, models applied first (the WordPress order)' => [false, true],
        'cold, post types applied first' => [false, false],
        'cached, models applied first (the WordPress order)' => [true, true],
        'cached, post types applied first' => [true, false],
    ]);

    it('queries the same post type from either class', function (bool $cached) {
        [$postTypes, $models] = overridableDiscoveries($cached);
        $models->apply();
        $postTypes->apply();

        expect(PostTypeRegistry::get(OverridableProjectPlace::class))
            ->toBe('overridable_place')
            ->and(PostTypeRegistry::get(OverridablePackagePlace::class))
            ->toBe('overridable_place');
    })->with(['cold' => [false], 'cached' => [true]]);
});
