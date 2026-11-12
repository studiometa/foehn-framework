<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\DatabaseExportConfig;
use Studiometa\Foehn\DatabaseExport\Anonymizer;

describe('DatabaseExportConfig', function () {
    it('declares nothing by default, so every unknown table is structure only', function () {
        $config = new DatabaseExportConfig();

        expect($config->window)->toBe('-2 months');
        expect($config->safeTables)->toBe([]);
        expect($config->structureOnlyTables)->toBe([]);
        expect($config->columns)->toBe([]);
        expect($config->allowedEmailDomains)->toBe([]);
    });

    it('is readonly', function () {
        expect(DatabaseExportConfig::class)->toBeReadonlyClass();
    });

    it('names its anonymizers, so a config holds no closure', function () {
        $config = new DatabaseExportConfig(columns: ['registrations' => ['email' => Anonymizer::Email]]);

        expect($config->columns['registrations']['email'])->toBe(Anonymizer::from('email'));
    });

    it('starts the window at the configured relative date, in UTC', function () {
        $now = new DateTimeImmutable('2026-05-15 10:00:00', new DateTimeZone('UTC'));

        expect(new DatabaseExportConfig()->from(null, $now))->toBe('2026-03-15 10:00:00');
        expect(new DatabaseExportConfig(window: '-1 week')->from(null, $now))->toBe('2026-05-08 10:00:00');
    });

    it('lets --from replace the window with a relative or an absolute date', function () {
        $now = new DateTimeImmutable('2026-05-15 10:00:00', new DateTimeZone('UTC'));
        $config = new DatabaseExportConfig();

        expect($config->from('-1 year', $now))->toBe('2025-05-15 10:00:00');
        expect($config->from('2026-01-01', $now))->toBe('2026-01-01 00:00:00');
    });

    it('refuses a date it cannot read', function () {
        new DatabaseExportConfig()->from('not a date');
    })->throws(InvalidArgumentException::class);

    it('allows an email by its exact domain, ignoring case', function () {
        $config = new DatabaseExportConfig(allowedEmailDomains: ['Example.com']);

        expect($config->allowsEmail('dev@example.com'))->toBeTrue();
        expect($config->allowsEmail('DEV@EXAMPLE.COM'))->toBeTrue();
        expect($config->allowsEmail('dev@mail.example.com'))->toBeFalse();
        expect($config->allowsEmail('dev@example.com.example.net'))->toBeFalse();
        expect($config->allowsEmail('example.com'))->toBeFalse();
        expect($config->allowsEmail(null))->toBeFalse();
    });

    it('allows no email when no domain is listed', function () {
        expect(new DatabaseExportConfig()->allowsEmail('dev@example.com'))->toBeFalse();
    });
});
