<?php

declare(strict_types=1);

use Studiometa\Foehn\PageCache\ScheduleStore;

beforeEach(function () {
    wp_stub_reset();
    $this->store = new ScheduleStore();
});

describe('ScheduleStore', function () {
    it('records a URL and its date in one non-autoloaded option', function () {
        $this->store->record('https://example.com/', 2000, now: 1000);

        expect($this->store->all())->toBe(['https://example.com/' => 2000]);
        expect(wp_stub_get_calls('update_option')[0]['args'])->toMatchArray([
            'option' => ScheduleStore::OPTION,
            'autoload' => false,
        ]);
    });

    it('keeps the earliest future date of a URL', function () {
        $this->store->record('https://example.com/', 3000, now: 1000);
        $this->store->record('https://example.com/', 2000, now: 1000);
        $this->store->record('https://example.com/', 4000, now: 1000);

        expect($this->store->all())->toBe(['https://example.com/' => 2000]);
    });

    it('writes nothing when the stored date already covers the new one', function () {
        $this->store->record('https://example.com/', 2000, now: 1000);
        $this->store->record('https://example.com/', 2500, now: 1000);

        expect(wp_stub_get_calls('update_option'))->toHaveCount(1);
    });

    it('replaces a date that has passed', function () {
        // The page was purged, or is about to be, and has been rendered again since: the
        // next change is the one to keep.
        $this->store->record('https://example.com/', 1500, now: 1000);
        $this->store->record('https://example.com/', 5000, now: 2000);

        expect($this->store->all())->toBe(['https://example.com/' => 5000]);
    });

    it('lists the URLs whose date has passed', function () {
        $this->store->record('https://example.com/b/', 1000, now: 0);
        $this->store->record('https://example.com/a/', 1500, now: 0);
        $this->store->record('https://example.com/c/', 3000, now: 0);

        expect($this->store->due(2000))->toBe(['https://example.com/a/', 'https://example.com/b/']);
    });

    it('forgets only the URLs that are still due', function () {
        $this->store->record('https://example.com/a/', 1000, now: 0);
        $this->store->record('https://example.com/b/', 1000, now: 0);
        $this->store->record('https://example.com/c/', 3000, now: 0);
        $due = $this->store->due(2000);

        // Rendered again between the read and the purge, with a later date.
        $this->store->record('https://example.com/b/', 4000, now: 2000);
        $this->store->forget($due, 2000);

        expect($this->store->all())->toBe(['https://example.com/b/' => 4000, 'https://example.com/c/' => 3000]);
    });

    it('deletes the option once nothing is left', function () {
        $this->store->record('https://example.com/', 1000, now: 0);
        $this->store->forget(['https://example.com/'], 2000);

        expect(get_option(ScheduleStore::OPTION))->toBeFalse();
    });

    it('ignores a malformed option', function () {
        update_option(ScheduleStore::OPTION, [
            'https://example.com/' => 'soon',
            3 => 1000,
            'https://example.com/a/' => '1200',
        ]);

        expect($this->store->all())->toBe(['https://example.com/a/' => 1200]);

        update_option(ScheduleStore::OPTION, 'garbage');

        expect($this->store->all())->toBe([]);
    });
});
