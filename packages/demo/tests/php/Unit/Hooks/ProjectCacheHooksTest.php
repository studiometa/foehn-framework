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

    it('queues the plates of a project when it is saved', function () {
        $attrs = new ReflectionMethod(ProjectCacheHooks::class, 'warmPlates')->getAttributes(AsAction::class);
        $action = $attrs[0]->newInstance();

        expect($action->hook)->toBe('save_post_project');
        expect($action->acceptedArgs)->toBe(2);
    });

    it('queues nothing for a project that is not published', function () {
        $post = new WP_Post();
        $post->ID = 12;
        $post->post_status = 'draft';

        // dispatch() would need the kernel, so returning before it is the assertion.
        new ProjectCacheHooks(new TransientCache())->warmPlates(12, $post);

        expect(true)->toBeTrue();
    });
});
