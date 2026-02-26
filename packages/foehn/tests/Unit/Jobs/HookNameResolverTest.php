<?php

declare(strict_types=1);

use Studiometa\Foehn\Jobs\HookNameResolver;

describe('HookNameResolver', function () {
    describe('forCron', function () {
        it('converts class name to hook name', function () {
            $hook = HookNameResolver::forCron('App\\Jobs\\CleanupLogs');

            expect($hook)->toBe('foehn/app/jobs/cleanup_logs');
        });

        it('uses custom hook when provided', function () {
            $hook = HookNameResolver::forCron('App\\Jobs\\CleanupLogs', 'my_plugin/cleanup');

            expect($hook)->toBe('my_plugin/cleanup');
        });

        it('handles deeply nested namespaces', function () {
            $hook = HookNameResolver::forCron('App\\Domain\\Billing\\Jobs\\SendInvoice');

            expect($hook)->toBe('foehn/app/domain/billing/jobs/send_invoice');
        });

        it('handles leading backslash', function () {
            $hook = HookNameResolver::forCron('\\App\\Jobs\\CleanupLogs');

            expect($hook)->toBe('foehn/app/jobs/cleanup_logs');
        });
    });

    describe('forJob', function () {
        it('converts DTO class name to hook name', function () {
            $hook = HookNameResolver::forJob('App\\Jobs\\ProcessImport');

            expect($hook)->toBe('foehn/app/jobs/process_import');
        });

        it('uses custom hook when provided', function () {
            $hook = HookNameResolver::forJob('App\\Jobs\\ProcessImport', 'my_plugin/process');

            expect($hook)->toBe('my_plugin/process');
        });

        it('converts CamelCase correctly', function () {
            $hook = HookNameResolver::forJob('App\\Jobs\\SendWelcomeEmail');

            expect($hook)->toBe('foehn/app/jobs/send_welcome_email');
        });
    });
});
