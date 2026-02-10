<?php

declare(strict_types=1);

use Studiometa\Foehn\Helpers\Env;

beforeEach(function (): void {
    // Clear env variables
    putenv('APP_ENV');
    putenv('WP_ENV');
});

afterEach(function (): void {
    // Clean up
    putenv('APP_ENV');
    putenv('WP_ENV');
});

describe('Env', function (): void {
    describe('get()', function (): void {
        it('returns APP_ENV when set', function (): void {
            putenv('APP_ENV=staging');

            expect(Env::get())->toBe('staging');
        });

        it('returns WP_ENV when APP_ENV is not set', function (): void {
            putenv('WP_ENV=development');

            expect(Env::get())->toBe('development');
        });

        it('prefers APP_ENV over WP_ENV', function (): void {
            putenv('APP_ENV=production');
            putenv('WP_ENV=development');

            expect(Env::get())->toBe('production');
        });

        it('falls back to production when no env is set', function (): void {
            expect(Env::get())->toBe('production');
        });
    });

    describe('is()', function (): void {
        it('returns true when environment matches', function (): void {
            putenv('APP_ENV=staging');

            expect(Env::is('staging'))->toBeTrue();
        });

        it('returns false when environment does not match', function (): void {
            putenv('APP_ENV=staging');

            expect(Env::is('production'))->toBeFalse();
        });
    });

    describe('isProduction()', function (): void {
        it('returns true when in production', function (): void {
            putenv('APP_ENV=production');

            expect(Env::isProduction())->toBeTrue();
        });

        it('returns true by default (fallback)', function (): void {
            expect(Env::isProduction())->toBeTrue();
        });

        it('returns false when not in production', function (): void {
            putenv('APP_ENV=development');

            expect(Env::isProduction())->toBeFalse();
        });
    });

    describe('isDevelopment()', function (): void {
        it('returns true when in development', function (): void {
            putenv('APP_ENV=development');

            expect(Env::isDevelopment())->toBeTrue();
        });

        it('returns false when not in development', function (): void {
            putenv('APP_ENV=production');

            expect(Env::isDevelopment())->toBeFalse();
        });
    });

    describe('isStaging()', function (): void {
        it('returns true when in staging', function (): void {
            putenv('APP_ENV=staging');

            expect(Env::isStaging())->toBeTrue();
        });

        it('returns false when not in staging', function (): void {
            putenv('APP_ENV=production');

            expect(Env::isStaging())->toBeFalse();
        });
    });

    describe('isLocal()', function (): void {
        it('returns true when in local', function (): void {
            putenv('APP_ENV=local');

            expect(Env::isLocal())->toBeTrue();
        });

        it('returns true when in development', function (): void {
            putenv('APP_ENV=development');

            expect(Env::isLocal())->toBeTrue();
        });

        it('returns false when not in local or development', function (): void {
            putenv('APP_ENV=production');

            expect(Env::isLocal())->toBeFalse();
        });
    });

    describe('isDebug()', function (): void {
        it('returns true when WP_DEBUG is true', function (): void {
            if (!defined('WP_DEBUG')) {
                define('WP_DEBUG', true);
            }

            expect(Env::isDebug())->toBe(WP_DEBUG);
        });
    });
});
