<?php

declare(strict_types=1);

use Studiometa\Foehn\PageCache\ScheduleStore;

beforeEach(function () {
    wp_stub_reset();
    $GLOBALS['wpdb'] = scheduleStoreDatabase();
    $this->store = new ScheduleStore();
});

describe('ScheduleStore', function () {
    it('records a URL and its date in a non-autoloaded option of its own', function () {
        $this->store->record('https://example.com/', 2000);
        $this->store->record('https://example.com/a/', 3000);

        expect($this->store->all())->toBe(['https://example.com/' => 2000, 'https://example.com/a/' => 3000]);
        expect(get_option(ScheduleStore::option('https://example.com/')))
            ->toBe('0000000000000002000 https://example.com/');
        expect($GLOBALS['wpdb']->queries[0])->toContain("'off')");
        expect(ScheduleStore::option('https://example.com/'))
            ->not
            ->toBe(ScheduleStore::option('https://example.com/a/'));
    });

    it('writes only the option of the URL it records', function () {
        // Two renders at the same time write two different options, so neither erases
        // the record of the other, as a shared array written back whole did.
        $this->store->record('https://example.com/', 2000);
        $this->store->record('https://example.com/a/', 3000);

        expect(array_keys($GLOBALS['wp_stub_options']))->toBe([
            ScheduleStore::option('https://example.com/'),
            ScheduleStore::option('https://example.com/a/'),
        ]);
    });

    it('keeps the earliest date of a URL', function () {
        $this->store->record('https://example.com/', 3000);
        $this->store->record('https://example.com/', 2000);
        $this->store->record('https://example.com/', 4000);

        expect($this->store->all())->toBe(['https://example.com/' => 2000]);
    });

    it('never replaces an earlier date with a later one', function (int $first, int $second) {
        $this->store->record('https://example.com/', $first);
        $this->store->record('https://example.com/', $second);

        expect($this->store->all())->toBe(['https://example.com/' => min($first, $second)]);
    })->with([
        'earlier first' => [2000, 3000],
        'later first' => [3000, 2000],
        'a date with more digits after' => [999, 1000],
        'a date with fewer digits after' => [1000, 999],
        'the same date' => [2000, 2000],
    ]);

    it('keeps the earlier date when two renders of a URL saw no record and the later one writes last', function () {
        // The race of a read followed by update_option(): two renders read no record, the
        // render with the earlier date writes, then the render with the later date writes.
        // The cache of both still says the option does not exist. No read decides the
        // write: one upsert per render keeps the smaller value in the database.
        $option = ScheduleStore::option('https://example.com/');
        wp_cache_set('notoptions', [$option => true], 'options');

        $this->store->record('https://example.com/', 2000);
        wp_cache_set('notoptions', [$option => true], 'options');
        $this->store->record('https://example.com/', 3000);

        expect($GLOBALS['wpdb']->queries)
            ->toHaveCount(2)
            ->each->toEndWith('ON DUPLICATE KEY UPDATE option_value = LEAST(option_value, VALUES(option_value))');
        expect($this->store->all())->toBe(['https://example.com/' => 2000]);
    });

    it('pads the date so that the database orders the values of a URL by date', function () {
        // The dates are zero-padded, so the database compares them as strings in date order.
        expect(strcmp(
            ScheduleStore::value('https://example.com/', 999),
            ScheduleStore::value('https://example.com/', 1000),
        ))
            ->toBeLessThan(0);
        expect(ScheduleStore::value('https://example.com/', PHP_INT_MAX))->toStartWith(PHP_INT_MAX . ' ');
    });

    it('clears what the object cache keeps of the option it writes', function () {
        $option = ScheduleStore::option('https://example.com/');
        wp_cache_set($option, '0000000000000009000 https://example.com/', 'options');
        wp_cache_set('notoptions', [$option => true, 'other' => true], 'options');

        $this->store->record('https://example.com/', 2000);

        expect(wp_cache_get($option, 'options'))->toBeFalse();
        expect(wp_cache_get('notoptions', 'options'))->toBe(['other' => true]);
    });

    it('keeps a date that has passed until the job forgets it', function () {
        // /news/ is stored with a block that shows at 1500. After 1500 and before the job
        // runs, a variant of /news/ renders with a later change. The due date stays, or the
        // stored /news/, which does not show the block, would stay until 5000.
        $this->store->record('https://example.com/news/', 1500);
        $this->store->record('https://example.com/news/', 5000);

        expect($this->store->due(2000))->toBe(['https://example.com/news/']);

        $this->store->forget(['https://example.com/news/']);
        $this->store->record('https://example.com/news/', 5000);

        expect($this->store->all())->toBe(['https://example.com/news/' => 5000]);
    });

    it('lists the URLs whose date has passed', function () {
        $this->store->record('https://example.com/b/', 1000);
        $this->store->record('https://example.com/a/', 1500);
        $this->store->record('https://example.com/c/', 3000);

        expect($this->store->due(2000))->toBe(['https://example.com/a/', 'https://example.com/b/']);
    });

    it('forgets the URLs it is given, and only those', function () {
        $this->store->record('https://example.com/a/', 1000);
        $this->store->record('https://example.com/b/', 3000);
        $this->store->forget(['https://example.com/a/']);

        expect($this->store->all())->toBe(['https://example.com/b/' => 3000]);
        expect(get_option(ScheduleStore::option('https://example.com/a/')))->toBeFalse();
    });

    it('ignores a malformed option', function () {
        update_option(ScheduleStore::PREFIX . 'a', 'soon https://example.com/');
        update_option(ScheduleStore::PREFIX . 'b', '0000000000000001000');
        update_option(ScheduleStore::PREFIX . 'c', ['url' => 'https://example.com/', 'at' => 1000]);
        update_option(ScheduleStore::PREFIX . 'd', '0000000000000001200 https://example.com/a/');

        expect($this->store->all())->toBe(['https://example.com/a/' => 1200]);
    });
});
