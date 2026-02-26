<?php

declare(strict_types=1);

use Studiometa\Foehn\Jobs\JobRegistry;
use Tests\Fixtures\JobDtoFixture;
use Tests\Fixtures\JobHandlerFixture;

describe('JobRegistry', function () {
    it('registers and retrieves a handler', function () {
        $registry = new JobRegistry();
        $registry->register(
            JobDtoFixture::class,
            JobHandlerFixture::class,
            'foehn/tests/fixtures/job_dto_fixture',
            'foehn',
        );

        $registration = $registry->getForDto(JobDtoFixture::class);

        expect($registration)->not->toBeNull();
        expect($registration['hook'])->toBe('foehn/tests/fixtures/job_dto_fixture');
        expect($registration['handlerClass'])->toBe(JobHandlerFixture::class);
        expect($registration['group'])->toBe('foehn');
    });

    it('returns null for unregistered DTO', function () {
        $registry = new JobRegistry();

        expect($registry->getForDto('NonExistent\\Class'))->toBeNull();
    });

    it('checks if a handler is registered', function () {
        $registry = new JobRegistry();

        expect($registry->has(JobDtoFixture::class))->toBeFalse();

        $registry->register(
            JobDtoFixture::class,
            JobHandlerFixture::class,
            'foehn/tests/fixtures/job_dto_fixture',
            'foehn',
        );

        expect($registry->has(JobDtoFixture::class))->toBeTrue();
    });

    it('lists all registered handlers', function () {
        $registry = new JobRegistry();

        expect($registry->all())->toBeEmpty();

        $registry->register(
            JobDtoFixture::class,
            JobHandlerFixture::class,
            'foehn/tests/fixtures/job_dto_fixture',
            'foehn',
        );

        expect($registry->all())->toHaveCount(1);
    });
});
