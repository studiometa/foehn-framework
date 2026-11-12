<?php

declare(strict_types=1);

use Demo\Rest\ProjectsApi;
use Studiometa\Foehn\Attributes\AsRestRoute;

describe('ProjectsApi', function () {
    beforeEach(fn() => wp_stub_reset());

    it('opens the list to everyone and checks the drafts route itself', function () {
        $index = new ReflectionMethod(ProjectsApi::class, 'index')->getAttributes(AsRestRoute::class)[0]->newInstance();
        $drafts = new ReflectionMethod(
            ProjectsApi::class,
            'drafts',
        )->getAttributes(AsRestRoute::class)[0]->newInstance();

        expect([$index->namespace, $index->route, $index->method, $index->permission])->toBe([
            'demo/v1',
            '/projects',
            'GET',
            'public',
        ]);
        expect([$drafts->route, $drafts->permission])->toBe(['/projects/drafts', 'canSeeDrafts']);
        expect(method_exists(ProjectsApi::class, 'canSeeDrafts'))->toBeTrue();
    });

    it('shows drafts only to a user who can edit posts', function () {
        $api = new ProjectsApi();

        expect($api->canSeeDrafts(new WP_REST_Request()))->toBeFalse();

        $GLOBALS['wp_stub_user_can']['edit_posts'] = true;

        expect($api->canSeeDrafts(new WP_REST_Request()))->toBeTrue();
    });
});
