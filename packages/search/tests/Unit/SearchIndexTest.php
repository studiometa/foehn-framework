<?php

declare(strict_types=1);

beforeEach(function () {
    requiresLoupe();
    $this->index = testSearchIndex($this->directory);
});

afterEach(function () {
    removeDirectory($this->directory ?? null);
});

it('finds a document with a typo in the query', function () {
    $this->index->saveMany('en', [
        ['id' => 1, 'post_type' => 'post', 'title' => 'Sourdough bread', 'content' => 'Flour, water and salt.'],
        ['id' => 2, 'post_type' => 'post', 'title' => 'Croissants', 'content' => 'Butter and patience.'],
    ]);

    expect($this->index->ids('en', 'sourdouhg'))->toBe([1]);
});

it('ranks a match in the title above a match in the content', function () {
    $this->index->saveMany('en', [
        ['id' => 1, 'post_type' => 'post', 'title' => 'Croissants', 'content' => 'Better with sourdough starter.'],
        ['id' => 2, 'post_type' => 'post', 'title' => 'Sourdough', 'content' => 'A bread.'],
    ]);

    expect($this->index->ids('en', 'sourdough'))->toBe([2, 1]);
});

it('gives every matching id, past the 1,000 of one Loupe page', function () {
    $documents = [];

    for ($id = 1; $id <= 1_050; $id++) {
        $documents[] = ['id' => $id, 'post_type' => 'post', 'title' => 'Bread ' . $id];
    }

    $this->index->saveMany('en', $documents);

    expect($this->index->ids('en', 'bread'))->toHaveCount(1_050);
});

it('matches nothing for an empty query', function () {
    $this->index->save('en', ['id' => 1, 'post_type' => 'post', 'title' => 'Bread']);

    expect($this->index->ids('en', '  '))->toBe([]);
});

it('keeps each language in its own index', function () {
    $this->index->save('en', ['id' => 1, 'post_type' => 'post', 'title' => 'Bread']);
    $this->index->save('fr', ['id' => 2, 'post_type' => 'post', 'title' => 'Pain']);

    expect($this->index->ids('en', 'pain'))->toBe([]);
    expect($this->index->ids('fr', 'pain'))->toBe([2]);
    expect(is_dir($this->directory . '/fr'))->toBeTrue();
});

it('suggests the best matches with their title and post type', function () {
    $this->index->saveMany('en', [
        ['id' => 1, 'post_type' => 'post', 'title' => 'Bread'],
        ['id' => 2, 'post_type' => 'page', 'title' => 'Breakfast bread'],
        ['id' => 3, 'post_type' => 'post', 'title' => 'Bread rolls'],
    ]);

    $suggestions = $this->index->suggest('en', 'bread', 2);

    expect($suggestions)->toHaveCount(2);
    expect($suggestions[0])->toHaveKeys(['id', 'title', 'post_type']);
});

it('deletes a document from the indexes it is given', function () {
    $this->index->save('en', ['id' => 1, 'post_type' => 'post', 'title' => 'Bread']);
    $this->index->delete(1, ['en', 'fr']);

    expect($this->index->count('en'))->toBe(0);
});
