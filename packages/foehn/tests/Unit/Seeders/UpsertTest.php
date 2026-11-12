<?php

declare(strict_types=1);

use Studiometa\Foehn\Seeders\Upsert;

beforeEach(fn() => wp_stub_reset());

describe('Upsert::post', function () {
    it('finds the post by type and slug in any status', function () {
        Upsert::post('event', 'spring-fair');

        expect(wp_stub_get_calls('get_posts')[0]['args']['args'])->toBe([
            'post_type' => 'event',
            'post_status' => 'any',
            'name' => 'spring-fair',
            'posts_per_page' => 1,
            'fields' => 'ids',
        ]);
    });

    it('creates a published post when none has the slug', function () {
        $id = Upsert::post('event', 'spring-fair', ['post_title' => 'Spring fair']);

        expect($id)->toBe(100);
        expect(wp_stub_get_calls('wp_insert_post')[0]['args'])->toBe([
            'postarr' => [
                'post_type' => 'event',
                'post_name' => 'spring-fair',
                'post_status' => 'publish',
                'post_title' => 'Spring fair',
            ],
            'wp_error' => true,
        ]);
    });

    it('updates the post that has the slug', function () {
        $GLOBALS['wp_stub_get_posts'] = [42];

        $id = Upsert::post('event', 'spring-fair', ['post_title' => 'Spring fair']);

        expect($id)->toBe(42);
        expect(wp_stub_get_calls('wp_insert_post')[0]['args']['postarr']['ID'])->toBe(42);
    });

    it('lets the data set the status', function () {
        Upsert::post('event', 'spring-fair', ['post_status' => 'draft']);

        expect(wp_stub_get_calls('wp_insert_post')[0]['args']['postarr']['post_status'])->toBe('draft');
    });

    it('writes the meta on the post', function () {
        $GLOBALS['wp_stub_get_posts'] = [42];

        Upsert::post('event', 'spring-fair', meta: ['starts_at' => '2026-04-01', 'capacity' => 200]);

        expect($GLOBALS['wp_stub_post_meta'][42])->toBe(['starts_at' => '2026-04-01', 'capacity' => 200]);
    });

    it('throws when WordPress refuses the post, and writes no meta', function () {
        $GLOBALS['wp_stub_insert_post'] = new WP_Error('invalid_type', 'Invalid post type.');

        expect(fn() => Upsert::post('event', 'spring-fair', meta: ['capacity' => 200]))->toThrow(
            RuntimeException::class,
            'Could not write event/spring-fair: Invalid post type.',
        );
        expect(wp_stub_get_calls('update_post_meta'))->toBe([]);
    });
});
