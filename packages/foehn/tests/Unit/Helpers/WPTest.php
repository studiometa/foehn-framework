<?php

declare(strict_types=1);

use Studiometa\Foehn\Helpers\WP;

beforeEach(function (): void {
    // Reset globals
    unset($GLOBALS['wpdb'], $GLOBALS['wp_query'], $GLOBALS['post'], $GLOBALS['wp_stub_current_user']);
});

describe('WP', function (): void {
    describe('db()', function (): void {
        it('returns the global wpdb instance', function (): void {
            $wpdb = new wpdb();
            $wpdb->prefix = 'test_';
            $GLOBALS['wpdb'] = $wpdb;

            expect(WP::db())->toBe($wpdb);
            expect(WP::db()->prefix)->toBe('test_');
        });
    });

    describe('query()', function (): void {
        it('returns the global wp_query instance', function (): void {
            $wpQuery = new WP_Query();
            $wpQuery->post_count = 5;
            $GLOBALS['wp_query'] = $wpQuery;

            expect(WP::query())->toBe($wpQuery);
            expect(WP::query()->post_count)->toBe(5);
        });
    });

    describe('post()', function (): void {
        it('returns the global post when set', function (): void {
            $post = new WP_Post();
            $post->ID = 42;
            $post->post_type = 'page';
            $GLOBALS['post'] = $post;

            expect(WP::post())->toBe($post);
            expect(WP::post()->ID)->toBe(42);
        });

        it('returns null when no post is set', function (): void {
            expect(WP::post())->toBeNull();
        });
    });

    describe('user()', function (): void {
        it('returns the current user when logged in', function (): void {
            $user = new WP_User();
            $user->ID = 1;
            $user->display_name = 'Admin';
            $GLOBALS['wp_stub_current_user'] = $user;

            expect(WP::user())->toBe($user);
            expect(WP::user()->display_name)->toBe('Admin');
        });

        it('returns null when no user is logged in', function (): void {
            $user = new WP_User();
            $user->ID = 0;
            $GLOBALS['wp_stub_current_user'] = $user;

            expect(WP::user())->toBeNull();
        });
    });
});
