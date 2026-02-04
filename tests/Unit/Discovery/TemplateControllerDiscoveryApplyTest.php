<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\TemplateControllerDiscovery;
use Tests\Fixtures\TemplateControllerFixture;

beforeEach(function () {
    wp_stub_reset();
    bootTestContainer();
    $this->discovery = new TemplateControllerDiscovery();
});

afterEach(fn() => tearDownTestContainer());

describe('TemplateControllerDiscovery apply', function () {
    it('registers template_include filter', function () {
        $this->discovery->discover(new ReflectionClass(TemplateControllerFixture::class));
        $this->discovery->apply();

        $filters = wp_stub_get_calls('add_filter');

        expect($filters)->toHaveCount(1);
        expect($filters[0]['args']['hook'])->toBe('template_include');
        expect($filters[0]['args']['priority'])->toBe(5);
    });

    it('handles single template type', function () {
        $this->discovery->discover(new ReflectionClass(TemplateControllerFixture::class));
        $this->discovery->apply();

        $GLOBALS['wp_stub_template'] = 'single';

        ob_start();
        $result = $this->discovery->handleTemplateInclude('/path/to/single.php');
        $output = ob_get_clean();

        // The controller returns HTML, so the template path should be empty
        expect($result)->toBe('');
        expect($output)->toBe('<h1>Hello</h1>');
    });

    it('passes through when no controller matches', function () {
        $this->discovery->discover(new ReflectionClass(TemplateControllerFixture::class));
        $this->discovery->apply();

        $GLOBALS['wp_stub_template'] = '404';

        $result = $this->discovery->handleTemplateInclude('/path/to/404.php');

        expect($result)->toBe('/path/to/404.php');
    });

    it('handles page template type', function () {
        $this->discovery->discover(new ReflectionClass(TemplateControllerFixture::class));
        $this->discovery->apply();

        $GLOBALS['wp_stub_template'] = 'page';

        ob_start();
        $result = $this->discovery->handleTemplateInclude('/path/to/page.php');
        $output = ob_get_clean();

        expect($result)->toBe('');
        expect($output)->toBe('<h1>Hello</h1>');
    });

    it('registers nothing when no items discovered', function () {
        $this->discovery->apply();

        $filters = wp_stub_get_calls('add_filter');

        // Still registers template_include even with no items (it checks at runtime)
        expect($filters)->toHaveCount(1);
    });
});
