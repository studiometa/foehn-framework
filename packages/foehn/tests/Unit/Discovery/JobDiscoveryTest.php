<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\DiscoveryProblems;
use Studiometa\Foehn\Discovery\JobDiscovery;
use Studiometa\Foehn\Jobs\JobRegistry;
use Tests\Fixtures\InvalidJobHandlerFixture;
use Tests\Fixtures\JobHandlerBuiltinParamFixture;
use Tests\Fixtures\JobHandlerCustomHookFixture;
use Tests\Fixtures\JobHandlerFixture;
use Tests\Fixtures\JobHandlerNoInvokeFixture;
use Tests\Fixtures\NoAttributeFixture;

beforeEach(function () {
    $this->location = testDiscoveryLocation();
    $this->registry = new JobRegistry();
    $this->discovery = new JobDiscovery($this->registry, $this->problems = new DiscoveryProblems());
});

describe('JobDiscovery', function () {
    it('discovers job handler attributes on classes', function () {
        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(JobHandlerFixture::class));

        $items = iterator_to_array($this->discovery->getItems());

        expect($items)->toHaveCount(1);
        expect($items[0]['handlerClass'])->toBe(JobHandlerFixture::class);
        expect($items[0]['dtoClass'])->toBe(\Tests\Fixtures\JobDtoFixture::class);
        expect($items[0]['attribute']->hook)->toBeNull();
        expect($items[0]['attribute']->group)->toBe('foehn');
    });

    it('uses custom hook name when provided', function () {
        $this->discovery->discover(
            $this->location,
            new \Tempest\Reflection\ClassReflector(JobHandlerCustomHookFixture::class),
        );

        $items = iterator_to_array($this->discovery->getItems());

        expect($items)->toHaveCount(1);
        expect($items[0]['attribute']->hook)->toBe('my_plugin/process_import');
        expect($items[0]['attribute']->group)->toBe('my-plugin');
    });

    it('ignores classes without job attributes', function () {
        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(NoAttributeFixture::class));

        expect($this->discovery->getItems())->toHaveCount(0);
    });

    it('reports handlers without a DTO parameter, and skips them', function () {
        $this->discovery->discover(
            $this->location,
            new \Tempest\Reflection\ClassReflector(InvalidJobHandlerFixture::class),
        );

        expect($this->discovery->getItems())->toHaveCount(0);
        expect($this->problems->all()[0]['reason'])
            ->toContain('has an __invoke() method with 0 parameters instead of one');
    });

    it('reports handlers without __invoke method, and skips them', function () {
        $this->discovery->discover(
            $this->location,
            new \Tempest\Reflection\ClassReflector(JobHandlerNoInvokeFixture::class),
        );

        expect($this->discovery->getItems())->toHaveCount(0);
        expect($this->problems->all()[0]['reason'])->toContain('has #[AsJob] but has no public __invoke() method');
    });

    it('reports handlers with builtin parameter type, and skips them', function () {
        $this->discovery->discover(
            $this->location,
            new \Tempest\Reflection\ClassReflector(JobHandlerBuiltinParamFixture::class),
        );

        expect($this->discovery->getItems())->toHaveCount(0);
        expect($this->problems->all()[0]['reason'])
            ->toContain('has an __invoke() parameter that is not typed with a class');
        expect($this->problems->all()[0]['fix'])
            ->toBe(
                'Give '
                . JobHandlerBuiltinParamFixture::class
                . ' a public __invoke() method whose one parameter is typed with the job class.',
            );
    });

    it('reports hasItems correctly', function () {
        expect($this->discovery->getItems())->toHaveCount(0);

        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(JobHandlerFixture::class));

        expect($this->discovery->getItems())->not->toHaveCount(0);
    });

    it('registers handlers in the registry on apply', function () {
        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(JobHandlerFixture::class));

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
        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(JobHandlerFixture::class));

        expect($this->discovery->getItems()->getForLocation($this->location))->not->toBeEmpty();

        $restored = restoreThroughCacheFile(
            $this->discovery,
            new JobDiscovery(new JobRegistry(), new DiscoveryProblems()),
            $this->location,
        );

        expect(iterator_to_array($restored->getItems()))->toEqual(iterator_to_array($this->discovery->getItems()));
    });
});
