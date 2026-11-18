<?php

declare(strict_types=1);

use Studiometa\Foehn\Search\Config\SearchConfig;
use Studiometa\Foehn\Search\Languages;
use Studiometa\Foehn\Search\Rest\SuggestionsEndpoint;

beforeEach(function () {
    requiresLoupe();
    wp_stub_reset();
    $GLOBALS['pll_stub_languages'] = ['en' => 'English', 'fr' => 'Français'];
    $GLOBALS['pll_stub_current'] = 'en';
    $this->index = testSearchIndex($this->directory);
    $this->index->saveMany('en', [
        ['id' => 1, 'post_type' => 'post', 'title' => 'Bread'],
        ['id' => 2, 'post_type' => 'post', 'title' => 'Bread rolls'],
    ]);
    $this->index->save('fr', ['id' => 3, 'post_type' => 'page', 'title' => 'Pain']);
    searchPost(3, ['post_name' => 'pain']);
    $this->endpoint = new SuggestionsEndpoint($this->index, new Languages(), new SearchConfig(suggestions: 1));
});

afterEach(function () {
    removeDirectory($this->directory ?? null);
});

it('suggests the best matches, as many as the config says, with a short Cache-Control', function () {
    $request = new WP_REST_Request('GET', '/foehn-search/v1/suggestions');
    $request->set_param('q', 'bread');

    $response = $this->endpoint->suggestions($request);

    expect($response->get_data())->toHaveCount(1);
    expect($response->get_data()[0])->toHaveKeys(['id', 'title', 'type', 'url']);
    expect($response->get_headers())->toBe(['Cache-Control' => 'public, max-age=300']);
});

it('suggests in the language the request names', function () {
    $request = new WP_REST_Request('GET', '/foehn-search/v1/suggestions');
    $request->set_param('q', 'pain');
    $request->set_param('lang', 'fr');

    expect($this->endpoint->suggestions($request)->get_data())->toBe([
        ['id' => 3, 'title' => 'Pain', 'type' => 'page', 'url' => home_url('/pain/')],
    ]);
});

it('suggests in the current language when the request names an unknown one', function () {
    $request = new WP_REST_Request('GET', '/foehn-search/v1/suggestions');
    $request->set_param('q', 'pain');
    $request->set_param('lang', 'de');

    expect($this->endpoint->suggestions($request)->get_data())->toBe([]);
});
