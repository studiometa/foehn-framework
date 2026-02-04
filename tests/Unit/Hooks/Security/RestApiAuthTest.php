<?php

declare(strict_types=1);

use Studiometa\WPTempest\Hooks\Security\RestApiAuth;

describe('RestApiAuth', function () {
    it('removes user endpoints when not logged in', function () {
        $GLOBALS['wp_stub_logged_in'] = false;

        $hooks = new RestApiAuth();

        $endpoints = [
            '/wp/v2/posts' => ['methods' => 'GET'],
            '/wp/v2/users' => ['methods' => 'GET'],
            '/wp/v2/users/(?P<id>[\d]+)' => ['methods' => 'GET'],
            '/wp/v2/users/me' => ['methods' => 'GET'],
            '/wp/v2/pages' => ['methods' => 'GET'],
        ];

        $result = $hooks->restrictUserEndpoints($endpoints);

        expect($result)->toHaveKey('/wp/v2/posts');
        expect($result)->toHaveKey('/wp/v2/pages');
        expect($result)->not->toHaveKey('/wp/v2/users');
        expect($result)->not->toHaveKey('/wp/v2/users/(?P<id>[\d]+)');
        expect($result)->not->toHaveKey('/wp/v2/users/me');
    });

    it('preserves all endpoints when logged in', function () {
        $GLOBALS['wp_stub_logged_in'] = true;

        $hooks = new RestApiAuth();

        $endpoints = [
            '/wp/v2/posts' => ['methods' => 'GET'],
            '/wp/v2/users' => ['methods' => 'GET'],
            '/wp/v2/users/me' => ['methods' => 'GET'],
        ];

        $result = $hooks->restrictUserEndpoints($endpoints);

        expect($result)->toBe($endpoints);
    });

    it('has correct filter attribute', function () {
        $method = new ReflectionMethod(RestApiAuth::class, 'restrictUserEndpoints');
        $attributes = $method->getAttributes(\Studiometa\WPTempest\Attributes\AsFilter::class);

        expect($attributes)->toHaveCount(1);
        expect($attributes[0]->newInstance()->hook)->toBe('rest_endpoints');
    });

    afterEach(function () {
        $GLOBALS['wp_stub_logged_in'] = false;
    });
});
