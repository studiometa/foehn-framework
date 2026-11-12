<?php

declare(strict_types=1);

use Studiometa\Foehn\Trace\RequestTrace;
use Tests\Fixtures\TemplateControllerFixture;

beforeEach(function () {
    $this->location = testDiscoveryLocation();
    wp_stub_reset();
    bootTestContainer();
    $this->discovery = testTemplateControllerDiscovery();
});

afterEach(fn() => tearDownTestContainer());

describe('TemplateControllerDiscovery apply', function () {
    it('registers template_include filter', function () {
        $this->discovery->discover(
            $this->location,
            new \Tempest\Reflection\ClassReflector(TemplateControllerFixture::class),
        );
        $this->discovery->apply();

        $filters = wp_stub_get_calls('add_filter');

        expect($filters)->toHaveCount(1);
        expect($filters[0]['args']['hook'])->toBe('template_include');
        expect($filters[0]['args']['priority'])->toBe(5);
    });

    it('passes through when no controller matches', function () {
        $this->discovery->discover(
            $this->location,
            new \Tempest\Reflection\ClassReflector(TemplateControllerFixture::class),
        );
        $this->discovery->apply();

        // 404 is not in the fixture's templates (single, page)
        $GLOBALS['wp_stub_template'] = '404';

        $result = $this->discovery->handleTemplateInclude('/path/to/404.php');

        expect($result)->toBe('/path/to/404.php');
    });

    it('registers filter even when no items discovered', function () {
        $this->discovery->apply();

        $filters = wp_stub_get_calls('add_filter');

        // Still registers template_include even with no items (it checks at runtime)
        expect($filters)->toHaveCount(1);
    });

    it('tells the request context the template it matched and the controller that handles it', function () {
        $context = new RequestTrace();
        $discovery = testTemplateControllerDiscovery(requestTrace: $context);
        $discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(TemplateControllerFixture::class));
        $discovery->apply();

        $GLOBALS['wp_stub_template'] = 'page';

        // The controller then needs Timber::context(), which needs WordPress. The
        // match is recorded before it runs.
        expect(fn() => $discovery->handleTemplateInclude('/index.php'))->toThrow(Error::class);

        expect($context->toArray()['template'])->toBe('page');
        expect($context->toArray()['controller'])->toBe(TemplateControllerFixture::class);

        $GLOBALS['wp_stub_template'] = '404';
        $discovery->handleTemplateInclude('/index.php');

        expect($context->toArray()['template'])->toBe('404');
        expect($context->toArray()['controller'])->toBeNull();
    });

    // Note: Full handleTemplateInclude tests with controller execution require
    // Timber::context() which needs a full WordPress environment.
    // Integration tests should be done in a WordPress test suite.
});
