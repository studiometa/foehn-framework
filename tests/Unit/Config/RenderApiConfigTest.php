<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\RenderApiConfig;

describe('RenderApiConfig', function () {
    it('can be instantiated with defaults', function () {
        $config = new RenderApiConfig();

        expect($config->enabled)->toBeTrue();
        expect($config->templates)->toBe([]);
    });

    it('can be instantiated with custom values', function () {
        $config = new RenderApiConfig(enabled: false, templates: ['partials/*', 'blocks/*']);

        expect($config->enabled)->toBeFalse();
        expect($config->templates)->toBe(['partials/*', 'blocks/*']);
    });

    it('can be created from array', function () {
        $config = RenderApiConfig::fromArray([
            'enabled' => true,
            'templates' => ['components/*'],
        ]);

        expect($config->enabled)->toBeTrue();
        expect($config->templates)->toBe(['components/*']);
    });

    it('uses defaults when array is empty', function () {
        $config = RenderApiConfig::fromArray([]);

        expect($config->enabled)->toBeTrue();
        expect($config->templates)->toBe([]);
    });

    it('is readonly', function () {
        expect(RenderApiConfig::class)->toBeReadonly();
    });
});

describe('RenderApiConfig template matching', function () {
    it('rejects all templates when no patterns configured', function () {
        $config = new RenderApiConfig(templates: []);

        expect($config->isTemplateAllowed('partials/card'))->toBeFalse();
        expect($config->isTemplateAllowed('anything'))->toBeFalse();
    });

    it('matches exact template names', function () {
        $config = new RenderApiConfig(templates: ['partials/card']);

        expect($config->isTemplateAllowed('partials/card'))->toBeTrue();
        expect($config->isTemplateAllowed('partials/hero'))->toBeFalse();
    });

    it('matches wildcard patterns', function () {
        $config = new RenderApiConfig(templates: ['partials/*']);

        expect($config->isTemplateAllowed('partials/card'))->toBeTrue();
        expect($config->isTemplateAllowed('partials/hero'))->toBeTrue();
        expect($config->isTemplateAllowed('blocks/hero'))->toBeFalse();
    });

    it('does not match nested paths with single wildcard', function () {
        $config = new RenderApiConfig(templates: ['partials/*']);

        // Single * should not match path separators
        expect($config->isTemplateAllowed('partials/cards/small'))->toBeFalse();
    });

    it('matches multiple patterns', function () {
        $config = new RenderApiConfig(templates: ['partials/*', 'blocks/*', 'components/button']);

        expect($config->isTemplateAllowed('partials/card'))->toBeTrue();
        expect($config->isTemplateAllowed('blocks/hero'))->toBeTrue();
        expect($config->isTemplateAllowed('components/button'))->toBeTrue();
        expect($config->isTemplateAllowed('components/modal'))->toBeFalse();
    });

    it('matches patterns with prefix wildcard', function () {
        $config = new RenderApiConfig(templates: ['*/card']);

        expect($config->isTemplateAllowed('partials/card'))->toBeTrue();
        expect($config->isTemplateAllowed('blocks/card'))->toBeTrue();
        expect($config->isTemplateAllowed('partials/hero'))->toBeFalse();
    });
});
