<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsJob;

describe('AsJob', function () {
    it('can be instantiated with defaults', function () {
        $job = new AsJob();

        expect($job->group)->toBe('foehn');
        expect($job->hook)->toBeNull();
    });

    it('accepts a custom group', function () {
        $job = new AsJob(group: 'my-plugin');

        expect($job->group)->toBe('my-plugin');
    });

    it('accepts a custom hook name', function () {
        $job = new AsJob(hook: 'my_plugin/process');

        expect($job->hook)->toBe('my_plugin/process');
    });

    it('is readonly', function () {
        expect(AsJob::class)->toBeReadonly();
    });

    it('can be used as an attribute', function () {
        $reflection = new ReflectionClass(AsJob::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        expect($attributes)->toHaveCount(1);
    });

    it('targets classes only', function () {
        $reflection = new ReflectionClass(AsJob::class);
        $attribute = $reflection->getAttributes(Attribute::class)[0]->newInstance();

        expect($attribute->flags & Attribute::TARGET_CLASS)->toBeTruthy();
    });
});
