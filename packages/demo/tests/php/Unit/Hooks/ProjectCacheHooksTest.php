<?php

declare(strict_types=1);

use Demo\Hooks\ProjectCacheHooks;
use Studiometa\Foehn\Attributes\AsAction;
use Studiometa\Foehn\Cache\TransientCache;

describe('ProjectCacheHooks', function () {
    beforeEach(fn() => wp_stub_reset());

    it('runs when a project is saved', function () {
        $attrs = new ReflectionMethod(ProjectCacheHooks::class, 'flush')->getAttributes(AsAction::class);

        expect(array_map(static fn($attr) => $attr->newInstance()->hook, $attrs))->toBe(['save_post_project']);
    });

    it('flushes the projects tag', function () {
        $cache = new TransientCache();
        $cache->tags(['projects'])->put('portfolio-stats', ['series' => 6]);

        new ProjectCacheHooks($cache)->flush();

        expect($cache->has('portfolio-stats'))->toBeFalse();
    });
});
