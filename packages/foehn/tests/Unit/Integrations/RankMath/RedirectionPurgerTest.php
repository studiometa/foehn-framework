<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\PageCacheConfig;
use Studiometa\Foehn\Integrations\RankMath\RedirectionPurger;
use Studiometa\Foehn\PageCache\CacheKey;
use Studiometa\Foehn\PageCache\Invalidator;
use Studiometa\Foehn\PageCache\Store;

/**
 * One serialized `sources` column, as Rank Math writes it.
 *
 * @param list<array{pattern: string, comparison: string, ignore?: string}> $sources
 */
function rankMathSources(array $sources): string
{
    return serialize(array_map(static fn(array $source): array => ['ignore' => '', ...$source], $sources));
}

beforeEach(function () {
    wp_stub_reset();

    $this->root = pageCacheRoot();
    $config = new PageCacheConfig(enabled: true, path: $this->root);
    $this->store = new Store($config);
    $this->purger = new RedirectionPurger(new Invalidator($config, $this->store, pageCacheJobs()));

    $GLOBALS['wpdb'] = new wpdb();

    foreach (['/old-page', '/old-page/child', '/other'] as $path) {
        $this->store->put(CacheKey::create('example.com', $path), pageCacheBody());
    }

    $this->stored = fn(string $path): bool => $this->store->has(CacheKey::create('example.com', $path));
    $this->save = fn() => $this->purger->watch(
        "UPDATE `wp_rank_math_redirections` SET `status` = 'active', `updated` = '2026-11-16 10:00:01' WHERE `id` IN (3)",
    );
});

afterEach(function () {
    removeTestDirectory($this->root);
});

describe('RedirectionPurger', function () {
    it('watches the queries of the request and purges on shutdown', function () {
        $this->purger->register();

        $filters = array_column(wp_stub_get_calls('add_filter'), 'args');
        $actions = array_column(wp_stub_get_calls('add_action'), 'args');

        expect(array_column($filters, 'hook'))->toBe(['query']);
        expect(array_column($actions, 'hook'))->toBe(['shutdown']);
    });

    it('hands every query back unchanged', function () {
        $query = "INSERT INTO wp_rank_math_redirections (sources, updated) VALUES ('a', 'b')";

        expect($this->purger->watch($query))->toBe($query);
    });

    it('purges the stored page of an exact source, and nothing else', function () {
        $GLOBALS['wpdb']->column = [rankMathSources([['pattern' => 'old-page', 'comparison' => 'exact']])];

        ($this->save)();
        $this->purger->purge();

        expect(($this->stored)('/old-page'))->toBeFalse();
        expect(($this->stored)('/old-page/child'))->toBeTrue();
        expect(($this->stored)('/other'))->toBeTrue();
    });

    it('reads the active redirections written since the request started', function () {
        $GLOBALS['wp_stub_current_time'] = '2026-11-16 09:59:58';
        $this->purger->register();

        ($this->save)();
        $this->purger->purge();

        expect($GLOBALS['wpdb']->queries)->toBe([
            "SELECT sources FROM wp_rank_math_redirections WHERE status = 'active' AND updated >= 2026-11-16 09:59:58",
        ]);
    });

    it('empties the cache for a source that is a pattern', function (string $comparison, string $ignore) {
        $GLOBALS['wpdb']->column = [
            rankMathSources([
                ['pattern' => 'other', 'comparison' => 'exact'],
                ['pattern' => 'old-', 'comparison' => $comparison, 'ignore' => $ignore],
            ]),
        ];

        ($this->save)();
        $this->purger->purge();

        expect($this->store->stats()['files'])->toBe(0);
    })->with([
        'contains' => ['contains', ''],
        'starts with' => ['start', ''],
        'ends with' => ['end', ''],
        'regex' => ['regex', ''],
        'exact, ignoring case' => ['exact', 'case'],
    ]);

    it('skips a fragment source, which no request carries', function () {
        $GLOBALS['wpdb']->column = [rankMathSources([['pattern' => '#top', 'comparison' => 'exact']])];

        ($this->save)();
        $this->purger->purge();

        expect($this->store->stats()['files'])->toBe(3);
    });

    it('reads nothing when the request wrote no redirection', function (string $query) {
        $this->purger->watch($query);
        $this->purger->purge();

        expect($GLOBALS['wpdb']->queries)->toBe([]);
    })->with([
        'a read' => ["SELECT * FROM wp_rank_math_redirections WHERE status = 'active'"],
        'the lookup cache' => ["INSERT INTO wp_rank_math_redirections_cache (from_url, updated) VALUES ('a', 'b')"],
        'the hit counter' => [
            "UPDATE wp_rank_math_redirections SET hits = 4, last_accessed = '2026-11-16 10:00:00' WHERE id = 3",
        ],
        'another table' => ["UPDATE wp_posts SET post_title = 'updated' WHERE ID = 1"],
    ]);

    it('purges once, however many writes the request made', function () {
        ($this->save)();
        ($this->save)();
        $this->purger->purge();
        $this->purger->purge();

        expect($GLOBALS['wpdb']->queries)->toHaveCount(1);
    });
});
