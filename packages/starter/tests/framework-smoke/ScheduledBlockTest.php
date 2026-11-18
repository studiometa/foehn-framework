<?php

declare(strict_types=1);

use Studiometa\Foehn\Cron\HeartbeatEvent;
use Studiometa\Foehn\PageCache\ScheduleStore;
use Studiometa\Foehn\Smoke\Support\Site;

/**
 * A block shown only from a date, on a cached page.
 *
 * The unit suite proves the render filter and the schedule store on stubs. Only a real
 * site shows the three parts together: core passes the attribute to the `render_block`
 * filter of a core block, the page cache records the URL of the page, and the heartbeat
 * cron event deletes the stored page once the date has passed, so the next request shows the block.
 */

/** Seconds from now at which the scheduled block shows. */
const SCHEDULED_BLOCK_DELAY = 8;

beforeAll(function () {
    if (!Site::isRunning()) {
        return;
    }

    Site::enableCache();
});

afterAll(function () {
    if (!Site::isRunning()) {
        return;
    }

    if (isset($GLOBALS['foehn_smoke_scheduled_page'])) {
        Site::wp('wp post delete ' . (int) $GLOBALS['foehn_smoke_scheduled_page'] . ' --force');
    }

    Site::disableCache();
    Site::clearCache();
});

beforeEach(function () {
    if (!Site::isRunning()) {
        $this->markTestSkipped('ddev is not running — run `ddev start` and try again.');
    }

    Site::clearCache();
});

it('hides a scheduled block before its date, and shows it after the job purged the cached page', function () {
    $start = time();
    $created = (string) Site::wp('wp eval-file tests/framework-smoke/fixtures/scheduled-block.php '
    . SCHEDULED_BLOCK_DELAY);
    expect($created)->toMatch('/^\d+ \/\S*$/', "the fixture page was not created: {$created}");
    [$id, $path] = explode(' ', $created, 2);
    $GLOBALS['foehn_smoke_scheduled_page'] = (int) $id;

    // Before the date: the block is not rendered, and the page is stored without it.
    $before = smokeWarm($path);
    expectCache($before, 'HIT');
    expect($before->body)->toContain('smoke-always-block')->not->toContain('smoke-scheduled-block');

    // The render recorded the page and the date its output changes.
    expect((string) Site::wp(
        'wp option list --search=' . ScheduleStore::PREFIX . '* --fields=option_value --format=json',
    ))
        ->toContain(str_replace('/', '\\/', $path));

    // After the date, the stored page is stale until the job runs.
    sleep(max(0, $start + SCHEDULED_BLOCK_DELAY + 1 - time()));
    $stale = smokeGet($path);
    expectCache($stale, 'HIT');
    expect($stale->body)->not->toContain('smoke-scheduled-block');

    // The real cron runs the heartbeat event, and the purge with it: no Action Scheduler.
    Site::wp('wp cron event run ' . HeartbeatEvent::HOOK);

    $after = smokeGet($path);
    expectCache($after, 'MISS');
    expect($after->body)->toContain('smoke-always-block')->toContain('smoke-scheduled-block');
});
