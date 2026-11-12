<?php

declare(strict_types=1);

use Studiometa\Foehn\Trace\TraceStore;

describe('TraceStore', function () {
    beforeEach(function () {
        $this->directory = errorLogDirectory();
        $this->store = new TraceStore($this->directory);
    });

    afterEach(fn() => removeErrorLogDirectory($this->directory));

    it('gives each request a short id', function () {
        expect(TraceStore::newId())->toMatch(TraceStore::ID_PATTERN);
        expect(TraceStore::newId())->not->toBe(TraceStore::newId());
    });

    it('finds a trace by its id, and gives the last one', function () {
        $this->store->save(['id' => 'aaaaaa', 'url' => '/first/']);
        usleep(2000);
        $this->store->save(['id' => 'bbbbbb', 'url' => '/second/']);

        expect($this->store->find('aaaaaa'))->toBe(['id' => 'aaaaaa', 'url' => '/first/']);
        expect($this->store->find('cccccc'))->toBeNull();
        expect($this->store->last())->toBe(['id' => 'bbbbbb', 'url' => '/second/']);
    });

    it('refuses an id that is not one, so a glob pattern cannot reach another file', function () {
        $this->store->save(['id' => 'aaaaaa']);
        $this->store->save(['id' => '../../etc']);
        $this->store->save(['url' => '/no-id/']);

        expect($this->store->find('*'))->toBeNull();
        expect($this->store->find('a*'))->toBeNull();
        expect(glob($this->directory . '/*.json'))->toHaveCount(1);
    });

    it('keeps the last traces only', function () {
        mkdir($this->directory);

        for ($index = 0; $index < (TraceStore::MAX_TRACES + 3); $index++) {
            file_put_contents(
                sprintf('%s/20260101000000%03d-%06x.json', $this->directory, $index, $index),
                json_encode(['id' => sprintf('%06x', $index)]),
            );
        }

        $this->store->save(['id' => 'ffffff']);

        expect(glob($this->directory . '/*.json'))->toHaveCount(TraceStore::MAX_TRACES);
        expect($this->store->find('000000'))->toBeNull();
        expect($this->store->find('000004'))->not->toBeNull();
        expect($this->store->last())->toBe(['id' => 'ffffff']);
    });

    it('is empty before the first trace, and after clear()', function () {
        expect($this->store->last())->toBeNull();
        expect($this->store->clear())->toBe(0);

        $this->store->save(['id' => 'aaaaaa']);

        expect($this->store->clear())->toBe(1);
        expect($this->store->last())->toBeNull();
    });
});
