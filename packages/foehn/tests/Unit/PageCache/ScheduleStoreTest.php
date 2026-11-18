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
        expect(wp_stub_get_calls('update_option')[0]['args'])->toMatchArray([
            'option' => ScheduleStore::option('https://example.com/'),
            'value' => ['url' => 'https://example.com/', 'at' => 2000],
            'autoload' => false,
        ]);
        expect(ScheduleStore::option('https://example.com/'))
            ->not
            ->toBe(ScheduleStore::option('https://example.com/a/'));
    });

    it('writes only the option of the URL it records', function () {
        // Two renders at the same time write two different options, so neither erases
        // the record of the other, as a shared array written back whole did.
        $this->store->record('https://example.com/', 2000);
        $this->store->record('https://example.com/a/', 3000);

        expect(array_column(array_column(wp_stub_get_calls('update_option'), 'args'), 'option'))->toBe([
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

    it('writes nothing when the stored date already covers the new one', function () {
        $this->store->record('https://example.com/', 2000);
        $this->store->record('https://example.com/', 2500);

        expect(wp_stub_get_calls('update_option'))->toHaveCount(1);
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
        update_option(ScheduleStore::PREFIX . 'a', ['url' => 'https://example.com/', 'at' => 'soon']);
        update_option(ScheduleStore::PREFIX . 'b', ['at' => 1000]);
        update_option(ScheduleStore::PREFIX . 'c', 'garbage');
        update_option(ScheduleStore::PREFIX . 'd', ['url' => 'https://example.com/a/', 'at' => '1200']);

        expect($this->store->all())->toBe(['https://example.com/a/' => 1200]);
    });

    it('reads nothing without a database', function () {
        unset($GLOBALS['wpdb']);

        expect($this->store->all())->toBe([]);
    });
});
