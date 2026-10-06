<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\BlockCategoryDiscovery;
use Tests\Fixtures\BlockCategoryFixture;
use Tests\Fixtures\BlockCategoryOverlapFixture;

/**
 * The categories the editor gets once the filter has run over the given list.
 *
 * @param list<array{slug: string, title: string, icon: ?string}> $existing
 * @return list<array{slug: string, title: string, icon: ?string}>
 */
function filteredBlockCategories(array $existing = []): array
{
    return apply_filters('block_categories_all', $existing, null);
}

beforeEach(fn() => wp_stub_reset());

describe('BlockCategoryDiscovery::apply', function () {
    it('adds the categories before the existing ones', function () {
        $discovery = new BlockCategoryDiscovery();

        discoverFixture($discovery, BlockCategoryFixture::class);
        $discovery->apply();

        $categories = filteredBlockCategories([['slug' => 'text', 'title' => 'Text', 'icon' => null]]);

        expect(array_column($categories, 'slug'))->toBe(['acme-layout', 'acme-content', 'text']);
    });

    it('passes the slug, the title and the icon through', function () {
        $discovery = new BlockCategoryDiscovery();

        discoverFixture($discovery, BlockCategoryFixture::class);
        $discovery->apply();

        $categories = filteredBlockCategories();

        expect($categories[0])->toBe(['slug' => 'acme-layout', 'title' => 'Layout', 'icon' => 'layout']);
        expect($categories[1])->toBe(['slug' => 'acme-content', 'title' => 'Content', 'icon' => null]);
    });

    it('skips a slug that is already registered', function () {
        $discovery = new BlockCategoryDiscovery();

        discoverFixture($discovery, BlockCategoryOverlapFixture::class);
        $discovery->apply();

        $categories = filteredBlockCategories([['slug' => 'theme', 'title' => 'Theme', 'icon' => null]]);

        expect(array_column($categories, 'slug'))->toBe(['acme-layout', 'acme-media', 'theme']);
        expect($categories[2]['title'])->toBe('Theme');
    });

    it('keeps the class whose name sorts first when two classes declare one slug', function () {
        $discovery = new BlockCategoryDiscovery();

        // Discovered in reverse name order: the result must not depend on scan order.
        discoverFixture($discovery, BlockCategoryOverlapFixture::class);
        discoverFixture($discovery, BlockCategoryFixture::class);
        $discovery->apply();

        $categories = filteredBlockCategories();

        expect(array_column($categories, 'slug'))->toBe(['acme-layout', 'acme-content', 'theme', 'acme-media']);
        expect($categories[0]['title'])->toBe('Layout');
    });

    it('adds no filter when nothing was discovered', function () {
        new BlockCategoryDiscovery()->apply();

        expect(wp_stub_get_calls('add_filter'))->toBeEmpty();
    });
});
