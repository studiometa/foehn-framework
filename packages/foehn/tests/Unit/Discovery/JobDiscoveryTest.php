<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\DiscoveryLocation;
use Studiometa\Foehn\Discovery\JobDiscovery;
use Studiometa\Foehn\Jobs\JobRegistry;
use Tests\Fixtures\InvalidJobHandlerFixture;
use Tests\Fixtures\JobHandlerBuiltinParamFixture;
use Tests\Fixtures\JobHandlerCustomHookFixture;
use Tests\Fixtures\JobHandlerFixture;
use Tests\Fixtures\JobHandlerNoInvokeFixture;
use Tests\Fixtures\NoAttributeFixture;

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    $this->registry = new JobRegistry();
    $this->discovery = new JobDiscovery($this->registry);
});

describe('JobDiscovery', function () {
    it('discovers job handler attributes on classes', function () {
        $this->discovery->discover($this->location, new ReflectionClass(JobHandlerFixture::class));

        $items = $this->discovery->getItems()->all();

        expect($items)->toHaveCount(1);
        expect($items[0]['handlerClass'])->toBe(JobHandlerFixture::class);
        expect($items[0]['dtoClass'])->toBe(\Tests\Fixtures\JobDtoFixture::class);
        expect($items[0]['hook'])->toBe('foehn/tests/fixtures/job_dto_fixture');
        expect($items[0]['group'])->toBe('foehn');
    });

    it('uses custom hook name when provided', function () {
        $this->discovery->discover($this->location, new ReflectionClass(JobHandlerCustomHookFixture::class));

        $items = $this->discovery->getItems()->all();

        expect($items)->toHaveCount(1);
        expect($items[0]['hook'])->toBe('my_plugin/process_import');
        expect($items[0]['group'])->toBe('my-plugin');
    });

    it('ignores classes without job attributes', function () {
        $this->discovery->discover($this->location, new ReflectionClass(NoAttributeFixture::class));

        expect($this->discovery->getItems()->isEmpty())->toBeTrue();
    });

    it('ignores handlers without a DTO parameter', function () {
        $this->discovery->discover($this->location, new ReflectionClass(InvalidJobHandlerFixture::class));

        expect($this->discovery->getItems()->isEmpty())->toBeTrue();
    });

    it('ignores handlers without __invoke method', function () {
        $this->discovery->discover($this->location, new ReflectionClass(JobHandlerNoInvokeFixture::class));

        expect($this->discovery->getItems()->isEmpty())->toBeTrue();
    });

    it('ignores handlers with builtin parameter type', function () {
        $this->discovery->discover($this->location, new ReflectionClass(JobHandlerBuiltinParamFixture::class));

        expect($this->discovery->getItems()->isEmpty())->toBeTrue();
    });

    it('reports hasItems correctly', function () {
        expect($this->discovery->hasItems())->toBeFalse();

        $this->discovery->discover($this->location, new ReflectionClass(JobHandlerFixture::class));

        expect($this->discovery->hasItems())->toBeTrue();
    });

    it('registers handlers in the registry on apply', function () {
        $this->discovery->discover($this->location, new ReflectionClass(JobHandlerFixture::class));

        wp_stub_reset();
        $this->discovery->apply();

        // Check the registry was populated
        expect($this->registry->has(\Tests\Fixtures\JobDtoFixture::class))->toBeTrue();

        $registration = $this->registry->getForDto(\Tests\Fixtures\JobDtoFixture::class);
        expect($registration['hook'])->toBe('foehn/tests/fixtures/job_dto_fixture');
        expect($registration['handlerClass'])->toBe(JobHandlerFixture::class);

        // Check WordPress action was registered
        $calls = wp_stub_get_calls('add_action');
        $jobActions = array_values(array_filter(
            $calls,
            fn($call) => $call['args']['hook'] === 'foehn/tests/fixtures/job_dto_fixture',
        ));

        expect($jobActions)->toHaveCount(1);
    });

    it('does nothing on apply when no items discovered', function () {
        wp_stub_reset();
        $this->discovery->apply();

        expect(wp_stub_get_calls('add_action'))->toBeEmpty();
        expect($this->registry->all())->toBeEmpty();
    });

    it('supports caching', function () {
        $this->discovery->discover($this->location, new ReflectionClass(JobHandlerFixture::class));

        $cacheData = $this->discovery->getCacheableData();

        expect($cacheData)->not->toBeEmpty();

        // Restore from cache
        $restoredRegistry = new JobRegistry();
        $restored = new JobDiscovery($restoredRegistry);
        $restored->restoreFromCache($cacheData);

        expect($restored->getItems()->all())->toEqual($this->discovery->getItems()->all());
        expect($restored->wasRestoredFromCache())->toBeTrue();
    });
});
